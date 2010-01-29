<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Florian Bachmann <fbachmann@cross-content.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(t3lib_extMgm::extPath('feusermanagement') . 'class.Field.php');
require_once(t3lib_extMgm::extPath('feusermanagement') . 'class.registration_model.php');
require_once(t3lib_extMgm::extPath('feusermanagement') . 'class.registration_view.php');
require_once(t3lib_extMgm::extPath('feusermanagement') . 'lib_general.php');

/**
 * Plugin 'ccm_registration' for the 'feusermanagement' extension.
 *
 * @author	Florian Bachmann <fbachmann@cross-content.com>
 * @package	TYPO3
 * @subpackage	tx_feusermanagement
 */
class tx_feusermanagement_pi1 extends tslib_pibase {
	var $prefixId      = 'tx_feusermanagement_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_feusermanagement_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'feusermanagement';	// The extension key.
	var $errMsg='';
	var $adminError='';
	var $uid;
	var $pi_checkCHash = true;
	var $modelLib=null;
	var $viewLib=null;
	var $userMailTemplate='';
	var $adminMailTemplate='';
	var $requiredMarker='';
	var $templatefile='';
	var $requireUserConfirm=0;
	var $requireAdminConfirm=0;
	var $currStep=0;
	var $baseURL='';
	var $templateFileName='';
	
	function init() {
		$this->baseURL=getTSValue('config.baseURL',$GLOBALS['TSFE']->tmpl->setup);
		$this->requiredMarker=getTSValue('config.requiredMarker',$this->conf);
		$this->modelLib=t3lib_div::makeInstance('registration_model');
		$this->viewLib=t3lib_div::makeInstance('registration_view');
		if (getTSValue('config.userConfirmation',$this->conf)) $this->requireUserConfirm=1;
		if (getTSValue('config.adminConfirmation',$this->conf)) $this->requireAdminConfirm=1;
		$this->templateFileName=getTSValue('config.template',$this->conf);
		$this->templatefile = $this->cObj->fileResource($this->templateFileName);
		$this->userMailTemplate=$this->cObj->getSubpart($this->templatefile,"USER_MAIL_CONFIRMATION");
		$this->adminMailTemplate=$this->cObj->getSubpart($this->templatefile,"ADMIN_MAIL_USER_CONFIRMATION");
	}
	
	function main($content,$conf)	{
		global $TYPO3_CONF_VARS;
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj=1;
		
		$this->init();
		
		if (!$this->baseURL) return 'config.baseURL not set';
		if (!$this->templatefile) {
			return 'Template File: "'.$this->templateFileName.'" not found';
		}
		$start_registration=false;
		$checkInput=true;
		
		
		### SPRUNG AUF VORGÄNGERSEITE? ###
		if ($this->piVars['backlinkToStep']&&$GLOBALS["TSFE"]->fe_user->getKey('ses','ccm_reg_step')) { ###SESSION EXISTIERT, UND ER WILL ZURÜCK ###
			$back=$this->piVars["backlinkToStep"];
			$step=$GLOBALS["TSFE"]->fe_user->getKey('ses','ccm_reg_step');
			$back=(int)$back;
			if (($back>0) && ($back<$step)) {
				$GLOBALS["TSFE"]->fe_user->setKey('ses','ccm_reg_step',$back);
				$checkInput=false;
			}
		}
		if ($this->piVars["userConfirmationToken"]) {
			$content=$this->renderMailConfirmation($this->piVars["userConfirmationToken"]);
			return $this->pi_wrapInBaseClass($content);
		}
		if ($this->piVars["adminAction"]) {
			$content=$this->renderAdminConfirmation($this->piVars["adminAction"],$this->piVars["token"]);
			return $this->pi_wrapInBaseClass($content);
		}
		if (!$GLOBALS["TSFE"]->fe_user->getKey('ses','ccm_reg_step')) { ### new registration ###
			$start_registration=true;
			$GLOBALS["TSFE"]->fe_user->setKey('ses','ccm_reg_step',1);
		}
		else {
			### CHECK RECIEVED DATA ###
			$step=$GLOBALS["TSFE"]->fe_user->getKey('ses','ccm_reg_step');
			
			$checkInput&=($this->piVars['ccm_regstep']==$step);
			if (($checkInput)&&($this->validateInputLastStep($step))) {	
				$this->writeLastStepToSession($uid,$step);
				$GLOBALS["TSFE"]->fe_user->setKey('ses','ccm_reg_step',$step+1);
			} else {
				
			}
		}
		$step=$GLOBALS["TSFE"]->fe_user->getKey('ses','ccm_reg_step');
		$this->currStep=$step;
		### CHECK FOR REGISTRATION FINALIZED ###
		$lastStep=$this->getLastStepNr();
		$final=false;
		if ($step>$lastStep) {	
			$final=true;
		}
		
		### GET TEMPLATES ###
		
		$template=$this->cObj->getSubpart($this->templatefile,'###STEP_'.$step.'###');
		$errorTempl=$this->cObj->getSubpart($this->templatefile,'###ERROR_PART###');
		$finalTempl=$this->cObj->getSubpart($this->templatefile,'###FINAL_SCREEN###');
		$errorHTML=str_replace('###ERROR_MSG###',$this->errMsg,$errorTempl);
		$fields=$this->modelLib->getCurrentFields($this->conf['steps.'][$step.'.'],$this);
		
			### HOOK processFields ###
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['processFields'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['processFields'] as $userFunc) {
				$params = array(
					'uid' => $this->uid,
					'fields' => &$fields,
					'step' =>$step,
				);
				t3lib_div::callUserFunction($userFunc, $params, $this);
			}
		}
		
		### GET JS FIELDS ###
		$jsCode=array();
		foreach($fields as $field) {
			$js="";
			$js=$this->viewLib->getJS($field,$this);
			if (strlen($js)>0) {
				$jsCode[]=$js;
			}
		}
		
		### JS SUBMIT ###
		$fieldJSArr=array();
		// Für jedes feld die Prüfung vor nem Submit
		foreach($fields as $field) {
			$js='';
			$js=$this->viewLib->getFieldValidationJS($field,$this);
			if ($js) {
				$fieldJSArr[]=$js;
			}
		}
				
		$formJS=$this->viewLib->getFormJS($fieldJSArr,$fields,$step,$this);
		### GET HTML ###
		$markerArr=array();
		$htmlFields=array();
		$allFields=$this->modelLib->getAllFields($this);
		
		$markerArr["###SUBMIT###"]="<input type='submit' value='Absenden' />";
		$markerArr["###STEP###"]=$step." / ".$lastStep;

			###OLD VALUES###
		$markerArr=$this->viewLib->fillMarkers($allFields,$markerArr,$this);
		
			###ERROR_HTML###
		$errorHTML=str_replace("###ERROR_MSG###",$this->errMsg,$errorTempl);
		$markerArr["###ERROR###"]=($this->errMsg)?$errorHTML:"";
			### NAVIGATION BAR ###
		$navigation="";
		$backLinks=$this->getBacklinks($step);
		foreach($backLinks as $backlink) {
			$navigation.=$backlink;
		}
		$markerArr["###NAVIGATION###"]=$navigation;
		#t3lib_div::debug($markerArr);
			### HOOK fillMarker ###
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['fillMarker'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['fillMarker'] as $userFunc) {
				$params = array(
					'uid' => $this->uid,
					'markers' => $markerArr,
					'step' => $step,
				);
				if (is_array($tempMarkers = t3lib_div::callUserFunction($userFunc, $params, $this))) {
					$markerArr=array_merge($markerArr,$tempMarkers);
				}
			}
		}
		if (!$final) {
			$template=str_replace(array_keys($markerArr),$markerArr,$template);
		} else {
			$template=str_replace(array_keys($markerArr),$markerArr,$finalTempl);
			$this->userMailTemplate=str_replace(array_keys($markerArr),$markerArr,$this->userMailTemplate);
			$this->adminMailTemplate=str_replace(array_keys($markerArr),$markerArr,$this->adminMailTemplate);
			$this->createNewFEUser();
		}
		
		$js='
			<script type="text/javascript">
				'.implode(" ",$jsCode).'
				'.$formJS.'
			</script>
		';
		$GLOBALS['TSFE']->additionalHeaderData['feusermanagementjs']=$js;
		$content.=$template;
		if ($final) {
		### SESSION LÖSCHEN ###
			$GLOBALS["TSFE"]->fe_user->setKey('ses','ccm_reg_step',"0");
		}
		return $this->pi_wrapInBaseClass($content);
	}
	
	function validateInputLastStep($step) {
		
		$fields=$this->modelLib->getCurrentFields($this->conf["steps."][$step."."],$this);
		$valid=true;
		foreach($fields as $field) {
			if ($field->required) {
				$name=$field->name;
				$id=$field->htmlID;
				if (!(isset($this->piVars[$id]) && ($this->piVars[$id]))) {	
					$valid=$GLOBALS["TSFE"]->fe_user->getKey('ses',$this->prefixId.$field->htmlId);
					$this->errMsg=$this->prepareMessage(array($this->pi_getLL('not_enter','',FALSE),$field->label));
				}
				
			}
			
			if ($field->unique) {
				$id=$field->htmlID;
				
				$value=mysql_real_escape_string($this->piVars[$id]);
				
				$sql='SELECT * FROM fe_users WHERE '.$field->fe_user.'="'.$value.'"';
				
				$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
				$unique=true;
				while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$unique=false;
				}
				if (!$unique) {
					$valid=false;
					$this->errMsg=$this->prepareMessage(array($this->pi_getLL('unique_error','',FALSE),$field->label));
				}
			}
			
			if ($field->equal) {
				$ref=$field->equal;
				$id=$field->htmlID;
				$id2=$fields[$ref]->htmlID;
				if ($this->piVars[$id]!=$this->piVars[$id2]) {
					$valid=false;
					$this->errMsg=$this->prepareMessage(array($this->pi_getLL('equal_error','',FALSE),$field->label,$fields[$ref]->label));
				}
			}
			if ($field->validation) {
				switch ($field->validation) {
					case "email":
						$pattern = '/'.$this->viewLib->emailReg.'/';

						
						if (!preg_match($pattern,$this->piVars[$field->htmlID])) {
							
							$valid=false;
							$this->errMsg=$this->pi_getLL('email_error','',FALSE);
						}
						break;
					case "password":
						$pattern='/'.$this->viewLib->passwordReg.'/';
												
						if (!preg_match($pattern,$this->piVars[$field->htmlID])) {
							$valid=false;
							$this->errMsg=$this->pi_getLL('password_error','',FALSE);
						}
						break;
					case "regExp":
						
						$pattern = '/'.$field->regExp.'/';
						if (!preg_match($pattern,$this->piVars[$field->htmlID])) {
							$valid=false;
							$this->errMsg=$this->prepareMessage(array(pi_getLL('pattern_error','',FALSE),$field->label));
						}
						break;
				}
			}
		}
		### HOOK stepValidation ###
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['stepValidation'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['stepValidation'] as $userFunc) {
				$params = array(
					'step' => $step,
					'fields'=>$fields,
					'valid'=>$valid,
				);
				$valid=t3lib_div::callUserFunction($userFunc, $params, $this);
			}
		}
		return $valid;
	}
	
	function writeLastStepToSession($uid,$step) {
		$fields=$this->modelLib->getCurrentFields($this->conf['steps.'][$step.'.'],$this);
		foreach($fields as $field) {
			
			$name=$field->dbName;
			$id=$field->htmlID;
			if (isset($this->piVars[$id])) {
				$value=$this->piVars[$id];
				$GLOBALS["TSFE"]->fe_user->setKey('ses',$this->prefixId.$field->name,$value);
				### HOOK afterValueInsert ###
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['afterValueInsert'])) {
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['afterValueInsert'] as $userFunc) {
						$params = array(
							'field' => $field,
							'value'=>$value,
						);
						t3lib_div::callUserFunction($userFunc, $params, $this);
					}
				}
			}
		
		}
	}
	function getValueFromSession($field) {
		$sesVal=$GLOBALS["TSFE"]->fe_user->getKey('ses',$this->prefixId.$field->name);
		if ($sesVal) return $sesVal;
		if ($field->value) return $field->value; //Wert der übers Typoscript übergeben wurde, für z.B. Hidden-Fields
		return;
	}
	
	function createNewFEUser() {
		
		$allFields=$this->modelLib->getAllFields($this);
		$map=array();
		
		foreach($allFields as $field) {
			if ($field->fe_user) {
				$map[$field->fe_user]=mysql_real_escape_string($this->getValueFromSession($field));
			}
			if ($field->fe_user=='password') {
				if (getTSValue('config.useMD5',$this->conf)) {
					$map[$field->fe_user]=md5($map[$field->fe_user]);
				}
			}
		}
		
		$token=md5(rand());
		$map['registration_token']=$token;
		$keys=implode(",",array_keys($map));
		if (strlen($keys)>0) $keys=",".$keys;
		$values=implode("','",$map);
		if (strlen($values)>0) $values=",'".$values."'";

		
		$pid=getTSValue('config.usersFreshPid',$this->conf);
		$group=getTSValue('config.usersFreshGroup',$this->conf);
		if ((!isset($pid))) {
			
			$this->adminError=$this->pi_getLL('admin_error','',FALSE);
			
			return false;
		}
		$disabled=0;
		if (($this->requireUserConfirm) || ($this->requireAdminConfirm)) {
			$disabled=1;
		}
		$zeit=time();
		
		$sql="INSERT INTO fe_users (pid,usergroup,disable,tstamp,crdate".$keys.") VALUES('$pid','$group','$disabled','$zeit','$zeit'".$values.")";
		
		$GLOBALS['TYPO3_DB']->sql_query($sql);
		$id=$GLOBALS['TYPO3_DB']->sql_insert_id ();
		### HOOK afterregistration ###
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['afterUserCreate'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['afterUserCreate'] as $userFunc) {
				$params = array(
					'action' => 'new',
					'uid'=>$id,
				);
				t3lib_div::callUserFunction($userFunc, $params, $this);
			}
		}
		#t3lib_div::debug(array($this->requireUserConfirm,$this->requireAdminConfirm,$disabled));
		if (!$disabled) {
			if (getTSValue('config.autologin',$this->conf)) {
				$loginData = array( 'uname' => $map['username'], 'uident'=> $map['password'], 'status' =>'login' ); 
				$GLOBALS['TSFE']->fe_user->checkPid=0; 
				$info = $GLOBALS['TSFE']->fe_user->getAuthInfoArray(); 
				$user = $GLOBALS['TSFE']->fe_user->fetchUserRecord($info['db_user'],$loginData['uname']); 
				$login_success = $GLOBALS['TSFE']->fe_user->compareUident($user,$loginData); 
				if($login_success){ 
					$GLOBALS['TSFE']->fe_user->createUserSession($user); 
					$GLOBALS['TSFE']->loginUser = 1; 
					$GLOBALS['TSFE']->fe_user->start(); 
				}
				if ($redirPid=getTSValue('config.autologinRedirPid',$this->conf)) {
					#t3lib_div::debug('Location: '.$this->baseURL.$this->cObj->getTypoLink_URL($redirPid));
					header('Location: '.$this->baseURL.$this->cObj->getTypoLink_URL($redirPid));
				}
			}
		}
		
		if (getTSValue('config.userConfirmation',$this->conf)) $this->generateUserMailConfirmation($id);

	}
	

	function generateUserMailConfirmation($id) {
		$html=$this->userMailTemplate;
		$sql='SELECT * FROM fe_users WHERE uid='.$id;
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		$user = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$confirmLink=$this->baseURL.$this->cObj->getTypoLink_URL($GLOBALS['TSFE']->id,array($this->prefixId.'[userConfirmationToken]'=>$user['registration_token'],$this->prefixId.'[fe_user]'=>$id));
		$markerArr=array();
		$markerArr['###CONFIRMATION_LINK###']=$confirmLink;
		foreach($user as $key=>$value) {
			$markerArr['###FE_'.strtoupper($key).'###']=$value;
		}
		$html=str_replace(array_keys($markerArr),$markerArr,$html);
		
		$mailObj = t3lib_div::makeInstance('t3lib_htmlmail');
		$mailObj->start();
		$mailObj->recipient = $user["email"];
		$mailObj->subject = getTSValue('config.userMailSubject',$this->conf);
		$mailObj->from_email = getTSValue('config.mailFromEMail',$this->conf);
		$mailObj->from_name = getTSValue('config.mailFromName',$this->conf);
		$mailObj->addPlain($html);
		$mailObj->setHTML($mailObj->encodeMsg($html));
		$success=$mailObj->send($row["email"]);
		
		return $success;
	}
	function generateAdminMailUserConfirmation($row_feuser) {
		
		$markerArr=array();
		foreach ($row_feuser as $key=>$value) {
			$markerArr['###FE_'.strtoupper($key).'###']=$value;
		}
		$adminToken=$row_feuser['registration_token'];#md5($TYPO3_CONF_VARS['SYS']['encryptionKey'].$row['registration_token']);
		$confirmLink=$this->baseURL.$this->pi_getPageLink($GLOBALS['TSFE']->id,$target='',$urlParameters=array($this->prefixId.'[adminAction]'=>'confirm',$this->prefixId.'[token]'=>md5($adminToken),$this->prefixId.'[fe_user]'=>$row_feuser['uid']));
		$declineLink=$this->baseURL.$this->pi_getPageLink($GLOBALS['TSFE']->id,$target='',$urlParameters=array($this->prefixId.'[adminAction]'=>'decline',$this->prefixId.'[token]'=>md5($adminToken),$this->prefixId.'[fe_user]'=>$row_feuser['uid']));
		$markerArr["###ADMIN_ACCEPT###"]=$confirmLink;
		$markerArr["###ADMIN_DECLINE###"]=$declineLink;
		$disabled=0;
		if ($this->requireAdminConfirm) $disabled=1;

		$mailTemplate=$this->cObj->getSubpart($this->templatefile,'ADMIN_MAIL_USER_CONFIRMATION');
		
		$mailCont=str_replace(array_keys($markerArr),$markerArr,$mailTemplate);
		$adminAddr=getTSValue('config.adminMail',$this->conf);
		
		$adminAddr=getTSValue('config.adminMail',$this->conf);
		$adminSubject=getTSValue('config.adminMailSubject',$this->conf);
		$fromMail=getTSValue('config.mailFromEMail',$this->conf);
		$fromName=getTSValue('config.mailFromName',$this->conf);
		$mailObj = t3lib_div::makeInstance('t3lib_htmlmail');
		$mailObj->start();
		$mailObj->recipient = $adminAddr;
		$mailObj->subject = $adminSubject;
		$mailObj->from_email = $fromMail;
		$mailObj->from_name = $fromName;
		$mailObj->addPlain($mailCont);
		$mailObj->setHTML($mailObj->encodeMsg($mailCont));
		$success=$mailObj->send($adminAddr);		
		
	}
	function prepareMessage($arr) {
		if (is_array($arr)&&(count($arr)>0)) {
			$text=$arr[0];
			for ($i=1;$i<count($arr);$i++) {
				$text=str_replace("###".$i."###",$arr[$i],$text);
			}
		}
		return $text;
	}
	function getBacklinks($step) {
		$steps=array();
		
		$allSteps=getTSValue('steps',$this->conf);
		foreach ($allSteps as $key=>$value) {
			
			if(preg_match('/\A[0-9]+\.\z/',$key)) {
				$label=getTSValue('steps.'.$key.'label',$this->conf);
				$step=str_replace('.','',$key);
				if ($this->currStep>$step) {
					$html='<a href="'.$this->cObj->getTypoLink_URL($GLOBALS['TSFE']->id,array($this->prefixId.'[backlinkToStep]'=>$step)).'" class="oldstep">'.$label.'</a>';
				}
				if ($this->currStep==$step) {
					$html='<span class="currstep">'.$label.'</span>';
				}
				if ($this->currStep<$step) {
					$html='<span class="furturestep">'.$label.'</span>';
				}
				$steps[$key]=$html;
			}
		}
		return $steps;
		$inactiveTS=getTSValue('config.progressList.inactive.',$this->conf);
		$activeTS=getTSValue('config.progressList.active.',$this->conf);
		$currentTS=getTSValue('config.progressList.current.',$this->conf);
		$TSSteps=getTSValue('steps',$this->conf);
		
		foreach($TSSteps as $key=>$TSStep) {
			$key=$this->removeDot($key);
			$label=$key;
			if (array_key_exists("progressLabel",$TSStep)) {
				$label=$TSStep["progressLabel"];
			}
			$state="active";
			if ($key==$step) $state="current";
			if(is_array($TSCurrStep)) {
				$TSCurrStep=$this->conf["steps."][$step."."];
				if (array_key_exists("disallowBacklink",$TSCurrStep)) {
					$disallowList=explode(",",$TSCurrStep["disallowBacklink"]);
					foreach($disallowList as $disallow) {
						$disallow=trim($disallow);
						if (in_array($key,$disallow)) {
							$state="inactive";
						}
					}
				}
			}
		
			if ($key>$step) $state="inactive";
			if ($state=="current") {
				$html="<span>$label</span>";
				$html=$this->cObj->stdWrap($html,$currentTS);
			}
			if ($state=="active") {
				$html='<a href="'.$this->cObj->getTypoLink_URL($GLOBALS['TSFE']->id,array('backlinkToStep'=>$key,'no_cache'=>1)).'">'.$label.'</a>';//array('additionalParams'=>'&backlinkToStep='.$key,'no_cache'=>1)).'">'.$label.'</a>';
				$html=$this->cObj->stdWrap($html,$activeTS);
			}
			if ($state=="inactive") {
				$html="<span>$label</span>";
				$html=$this->cObj->stdWrap($html,$inactiveTS);
			}
			
			$steps[$key]=$html;
		}
		return $steps;
	}

	function getString($value) {
		if (strpos($value,"LL_user")===0 || strpos($value,"LL_field")===0) {
			$pos=max(strpos($value,"user"),strpos($value,"field"));
			$str=$this->pi_getLL(substr($value,$pos),'',FALSE);
		} else {
			$str=$value;
		}
		return $str;
	}
	function removeDot($key) {
		if ($dotpos=strpos($key,".")) {
			$key=substr($key,0,$dotpos);
		}
		return $key;
	}
	function getLastStepNr() {
		$steps=$this->conf["steps."];
		$lastStep=0;
		foreach($steps as $key=>$value) {
			if ($dotpos=strpos($key,".")) {
				$step=substr($key,0,$dotpos);
				if (is_numeric($step)) {
					$lastStep=max($lastStep,$step);
				}
			}
		}
		return $lastStep;
	}
	
	function renderMailConfirmation($token) {
		$token=mysql_real_escape_string($token);
		$feuser_uid=(int)$this->piVars['fe_user'];
		$sql='SELECT * FROM fe_users WHERE uid='.$feuser_uid.' AND registration_token="'.$token.'"';
		
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$pid=getTSValue('config.usersConfirmedPid',$this->conf);
			$group=getTSValue('config.usersConfirmedGroup',$this->conf);
			$token=md5(rand());
			$sql="UPDATE fe_users SET pid='$pid',usergroup='$group',disable='$disabled',registration_token='".$token."' WHERE uid=".$feuser_uid;
			$GLOBALS['TYPO3_DB']->sql_query($sql);
			$row['registration_token']=$token;
			$content=$this->cObj->getSubpart($this->templatefile,'AFTER_USER_MAIL_CONFIRMATION_HTML');
			foreach ($row as $key=>$value) {
				$markerArr['###FE_'.strtoupper($key).'###']=$value;
			}
			$content=str_replace(array_keys($markerArr),$markerArr,$content);
			if ($this->requireAdminConfirm) $this->generateAdminMailUserConfirmation($row);

		} else {
			$content="ungültiger Token";
		}
		return $content;
	}
	function renderAdminConfirmation($action,$token) {
		$user=(int)$this->piVars['fe_user'];
		$sql='SELECT * FROM fe_users WHERE uid='.$user;
		
		
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		if (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) && (md5($row['registration_token'])==$token)) {
			if ($action=='confirm') {
				$pid=$this->conf['config.']['usersAdminConfirmedPid'];
				$group=$this->conf['config.']['usersAdminConfirmedGroup'];
				$sql='UPDATE fe_users SET disable=0,usergroup='.$group.',pid='.$pid.' WHERE uid='.$user;
				$GLOBALS['TYPO3_DB']->sql_query($sql);
				$content='User Confirmed';
			}
			if ($action=='decline') {
				$pid=getTSValue('config.usersAdminConfirmedPid',$this->conf);
				$group=getTSValue('config.usersAdminConfirmedGroup',$this->conf);
				$sql='UPDATE fe_users SET deleted=1,pid='.$pid.' WHERE uid='.$user;
				$GLOBALS['TYPO3_DB']->sql_query($sql);
				$content='User Deleted';
			}
		} else {
			
			$content='ungültiger Token';
		}
		return $content;
	}
	
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/feusermanagement/pi1/class.tx_feusermanagement_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/feusermanagement/pi1/class.tx_feusermanagement_pi1.php']);
}

?>
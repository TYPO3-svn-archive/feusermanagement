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
require_once(t3lib_extMgm::extPath('fe_registration_process') . 'class.Field.php');
require_once(t3lib_extMgm::extPath('fe_registration_process') . 'class.registration_model.php');
require_once(t3lib_extMgm::extPath('fe_registration_process') . 'class.registration_view.php');
require_once(t3lib_extMgm::extPath('fe_registration_process') . 'lib_general.php');
//require_once('view.php');

/**
 * Plugin 'ccm_registration' for the 'fe_registration_process' extension.
 *
 * @author	Florian Bachmann <fbachmann@cross-content.com>
 * @package	TYPO3
 * @subpackage	tx_feregistrationprocess
 */
class tx_feregistrationprocess_pi1 extends tslib_pibase {
	var $prefixId      = 'tx_feregistrationprocess_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_feregistrationprocess_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'fe_registration_process';	// The extension key.
	var $errMsg="";
	var $adminError="";
	var $uid;
	var $pi_checkCHash = true;
	var $modelLib=null;
	var $viewLib=null;
	var $userMailTemplate="";
	var $adminMailTemplate="";
	var $requiredMarker="";
	var $templatefile="";
	var $requireUserConfirm=0;
	var $requireAdminConfirm=0;
	var $currStep=0;
	var $baseURL='';
	
	function main($content,$conf)	{
		global $TYPO3_CONF_VARS;
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj=1;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!
				
		$this->baseURL=getTSValue('config.baseURL',$GLOBALS['TSFE']->tmpl->setup);
		if (!$this->baseURL) return 'config.baseURL not set';
		
		$this->requiredMarker=getTSValue('config.requiredMarker',$conf);
		$this->modelLib=t3lib_div::makeInstance('registration_model');
		$this->viewLib=t3lib_div::makeInstance('registration_view');
		
		$banned=false;
		$start_registration=false;
		
		if ($this->conf["config."]["userConfirmation"]) $this->requireUserConfirm=1;
		if ($this->conf["config."]["adminConfirmation"]) $this->requireAdminConfirm=1;
		

		$checkInput=true;
		
		### Template ###
		$templateFile=getTSValue('config.template',$conf);
		if (!file_exists($templateFile)) {
			return "Template File: '".$templateFile."' not found";
		}

		$templatef = $this->cObj->fileResource($templateFile);
		$this->templatefile=$templatef;
		
		$this->userMailTemplate=$this->cObj->getSubpart($templatef,"USER_MAIL_CONFIRMATION");
		$this->adminMailTemplate=$this->cObj->getSubpart($templatef,"ADMIN_MAIL_USER_CONFIRMATION");
		
		
		### SPRUNG AUF VORGÄNGERSEITE? ###
		if ($_GET["backlinkToStep"]&&$GLOBALS["TSFE"]->fe_user->getKey("ses","ccm_reg_step")) { ###SESSION EXISTIERT, UND ER WILL ZURÜCK ###
			$back=$_GET["backlinkToStep"];
			$step=$GLOBALS["TSFE"]->fe_user->getKey("ses","ccm_reg_step");
			$back=(int)$back;
			if (($back>0) && ($back<$step)) {
				$GLOBALS["TSFE"]->fe_user->setKey("ses","ccm_reg_step",$back);
				$checkInput=false;
			}
		}
		if ($_GET["userConfirmationToken"]) {
			
			$content=$this->renderMailConfirmation($_GET["userConfirmationToken"]);
			return $this->pi_wrapInBaseClass($content);
		}
		if ($_GET["adminAction"]) {
			
			$content=$this->renderAdminConfirmation($_GET["adminAction"],$_GET["token"]);
			return $this->pi_wrapInBaseClass($content);
		}
		if (!$GLOBALS["TSFE"]->fe_user->getKey("ses","ccm_reg_step")) { ### new registration ###
			$start_registration=true;
			$GLOBALS["TSFE"]->fe_user->setKey("ses","ccm_reg_step",1);
		}
		else {
			### CHECK RECIEVED DATA ###
			$step=$GLOBALS["TSFE"]->fe_user->getKey("ses","ccm_reg_step");
			
			$checkInput&=($_POST['ccm_regstep']==$step);
			if (($checkInput)&&($this->validateInputLastStep($step))) {
				
				$this->writeLastStepToTable($uid,$step);
				$GLOBALS["TSFE"]->fe_user->setKey("ses","ccm_reg_step",$step+1);
			} else {
				
			}
			
		}
		$step=$GLOBALS["TSFE"]->fe_user->getKey("ses","ccm_reg_step");
		$this->currStep=$step;
		### CHECK FOR REGISTRATION FINALIZED ###
		$lastStep=$this->getLastStepNr();
		$final=false;
		if ($step>$lastStep) {	
			$final=true;
		}
		
		### GET TEMPLATES ###
		
		$template=$this->cObj->getSubpart($templatef,"STEP_".$step);
		$errorTempl=$this->cObj->getSubpart($templatef,"ERROR_PART");
		$finalTempl=$this->cObj->getSubpart($templatef,"FINAL_SCREEN");
		$errorHTML=str_replace("###ERROR_MSG###",$this->errMsg,$errorTempl);
		$fields=$this->modelLib->getCurrentFields($this->conf["steps."][$step."."],$this);
		
			### HOOK processFields ###
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['processFields'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['processFields'] as $userFunc) {
				$params = array(
					'uid' => $this->uid,
					'fields' => &$fields,
					'step' =>$step,
					'pi'=> 'tx_feregistrationprocess_pi1'
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
		$formJSarr=array();
		// Für jedes feld die Prüfung vor nem Submit
		foreach($fields as $field) {
			$js="";
			$js=$this->viewLib->getFormJS($field);
			if (strlen($js)>0) {
				$formJSarr[]=$js;
			}
		}
		$formJS='
			doSubmit=true;
			alertMessage="";
			'.implode(" ",$formJSarr).'
			
		';
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['formWrapper'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['formWrapper'] as $userFunc) {
				$params = array(
					'formJS' => &$formJS,
					'fields' => $fields,
					'step' =>$step
				);
				t3lib_div::callUserFunction($userFunc, $params, $this);
			}
		} else {
			$formJS.="if (!doSubmit) {
					alert(alertMessage);
				}";
		}
		$formWrapperJS='
			function '.$obj->prefixId.'_check_FormSubmit() {
				'.$formJS.'
				return doSubmit;
			}
		';
		
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
					'pi'=> 'tx_feregistrationprocess_pi1'
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
		$content.='
			<script type="text/javascript">
				'.implode(" ",$jsCode).'
				'.$formWrapperJS.'
			</script>
		';
		
		$content.=$template;
		
		if ($final) {
		### SESSION LÖSCHEN ###
			$GLOBALS["TSFE"]->fe_user->setKey("ses","ccm_reg_step","0");
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
				if (!(isset($_POST[$id]) && ($_POST[$id]))) {	
					$valid=$GLOBALS["TSFE"]->fe_user->getKey("ses",$this->prefixId.$field->htmlId);
				}
				
			}
			
			if ($field->unique) {
				$id=$field->htmlID;
				$value=mysql_real_escape_string($_POST[$id]);
				$sql="SELECT * FROM tx_feregistrationprocess_user_info WHERE type='".$field->dbName."' AND content='".$value."'";
				$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
				$unique=true;
				while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					if ($row["id"]!=$this->uid) $unique=false;
				}
				if (!$unique) {
					$valid=false;
					$this->errMsg=$this->prepareMessage(array($this->pi_getLL('unique_error','',FALSE),$field->name));
				}
			}
			if ($field->equal) {
				$ref=$field->equal;
				$id=$field->htmlID;
				$id2=$fields[$ref]->htmlID;
				if ($_POST[$id]!=$_POST[$id2]) {
					$valid=false;
					$this->errMsg=$this->prepareMessage(array($this->pi_getLL('equal_error','',FALSE),$field->name,$fields[$ref]->name));
				}
				#t3lib_div::debug($field);
			}
			if ($field->validation) {
				switch ($field->validation) {
					case "email":
						$pattern = "^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$";
						
						//$pattern="^[\\\\w-_\\.+]*[\\\\w-_\\.]\@([\\\\w]+\\\\.)+[\\\\w]+[\\\\w]$";
						
						if (!eregi($pattern,$_POST[$field->htmlID])) {
							
							$valid=false;
							$this->errMsg=$this->pi_getLL('email_error','',FALSE);
						}
						break;
					case "//password":
						$pattern="/^.*(?=.{6,})(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$/";
												
						if (!preg_match($pattern,$_POST[$field->htmlID])) {
							$valid=false;
							$this->errMsg=$this->pi_getLL('password_error','',FALSE);
						}
						break;
					case "regExp":
						
						$pattern = "";
						if (!eregi($pattern,$_POST[$field->htmlID])) {
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
					'uid' => $this->uid,
					'step' => $step,
					'fields'=>$fields,
					'valid'=>$valid,
					'pi'=> 'tx_feregistrationprocess_pi1'
				);
				$valid=t3lib_div::callUserFunction($userFunc, $params, $this);
			}
		}
		return $valid;
	}
	
	function writeLastStepToTable($uid,$step) {
		$fields=$this->modelLib->getCurrentFields($this->conf['steps.'][$step.'.'],$this);
		foreach($fields as $field) {
			if ($field->toDB) {
				$name=$field->dbName;
				$id=$field->htmlID;
				if (isset($_POST[$id])) {
					$value=$_POST[$id];
					
					
					$sql='DELETE FROM tx_feregistrationprocess_user_info WHERE id="'.mysql_real_escape_string($uid).'" AND type="'.mysql_real_escape_string($name).'"';
					$GLOBALS['TYPO3_DB']->sql_query($sql);
					$sql="INSERT INTO tx_feregistrationprocess_user_info (type,content,id,istemp) VALUES('".mysql_real_escape_string($name)."','".mysql_real_escape_string($value)."','".mysql_real_escape_string($uid)."','0')";
					$GLOBALS['TYPO3_DB']->sql_query($sql);
					### HOOK afterValueInsert ###
					if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['afterValueInsert'])) {
						foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['afterValueInsert'] as $userFunc) {
							$params = array(
								'uid' => $this->uid,
								'field' => $field,
								'value'=>$value,
								'pi'=> 'tx_feregistrationprocess_pi1'
							);
							t3lib_div::callUserFunction($userFunc, $params, $this);
						}
					}
				}
			}
		}
	}
	function getValueFromSession($field,$uid="") {
		$sesVal=$GLOBALS["TSFE"]->fe_user->getKey("ses",$this->prefixId.$field->name);
		if ($sesVal) return $sesVal;
		if ($field->value) return $field->value; //Wert der übers Typoscript übergeben wurde, für z.B. Hidden-Fields
		return;
	}
	
	function createNewFEUser() {
		
		$allFields=$this->modelLib->getAllFields($this);
		$map=array();
		if (is_array($this->conf["feuser_map."])) {
			foreach($this->conf["feuser_map."] as $key=>$value) {
				$map[$key]=$this->getValueFromSession($allFields[$value]);
				if ($key=='password') $map[$key]=encryptPW($map[$key],$this);
			}
		}
		
		$keys=implode(",",array_keys($map));
		if (strlen($keys)>0) $keys=",".$keys;
		$values=implode("','",$map);
		if (strlen($values)>0) $values=",'".$values."'";

		
		$pid=$this->conf["config."]["usersFreshPid"];
		$group=$this->conf["config."]["usersFreshGroup"];
		if ((!isset($pid))) {//||(!isset($usernameField))||(!isset($passwordField))) {
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

		$token=md5(rand());
		$sql="INSERT INTO tx_feregistrationprocess_user_info (id,type,content) VALUES('".$this->uid."','userconfirm_token','$token')";
		$GLOBALS['TYPO3_DB']->sql_query($sql);
		$token=md5(rand());
		$sql="INSERT INTO tx_feregistrationprocess_user_info (id,type,content) VALUES('".$this->uid."','adminconfirm_token','$token')";
		$GLOBALS['TYPO3_DB']->sql_query($sql);
		
		$sql="UPDATE tx_feregistrationprocess_user_info SET feuser_uid='$id' WHERE id='".$this->uid."'";
		$GLOBALS['TYPO3_DB']->sql_query($sql);		
		$this->generateUserMailConfirmation($id);

	}
	

	function generateUserMailConfirmation($id) {
		$html=$this->userMailTemplate;
		
		$sql="SELECT content FROM tx_feregistrationprocess_user_info WHERE feuser_uid='$id' AND type='userconfirm_token'";
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$token=$row["content"];
		
		$sql="SELECT * FROM fe_users WHERE uid='$id'";
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		
		$confirmLink=getTSValue('config.baseURL',$this->conf).$this->cObj->getTypoLink_URL($GLOBALS['TSFE']->id,array('userConfirmationToken'=>$token,'fe_user'=>$id));
		
		$html=str_replace("###CONFIRMATION_LINK###",$confirmLink,$html);
		
		$mailObj = t3lib_div::makeInstance('t3lib_htmlmail');
		$mailObj->start();
		$mailObj->recipient = $row["email"];
	   
		$mailObj->subject = 'Ihre Registrierung auf apotheken.de';
		$mailObj->from_email = 'noreply@apotheken.de';
		$mailObj->from_name = 'apotheken.de';
		$mailObj->addPlain($html);
		$mailObj->setHTML($mailObj->encodeMsg($html));
		$success=$mailObj->send($row["email"]);
		
		return $success;
	}
	function generateAdminMailUserConfirmation($fe_id,$id) {
		
		$html=$this->adminMailTemplate;
		
		$sql="SELECT content FROM tx_feregistrationprocess_user_info WHERE feuser_uid='$fe_id' AND type='adminconfirm_token'";
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$token=$row["content"];
		
		$allFields=$this->modelLib->getAllFields($this);
		$arr=array();
		foreach($allFields as $field) {
			if ($field->toDB) {
				$arr["###".$field->name."_VALUE###"]=$this->getValueFromDB($field,$id);
			}
		}
		
		$confirmLink=$this->baseURL.'index.php?id='.$GLOBALS['TSFE']->id.'&adminAction=confirm&token='.$token.'&fe_user='.$fe_id;
		$declineLink=$this->baseURL.'index.php?id='.$GLOBALS['TSFE']->id.'&adminAction=decline&token='.$token.'&fe_user='.$fe_id;
		$arr["###ADMIN_ACCEPT###"]=$confirmLink;
		$arr["###ADMIN_DECLINE###"]=$declineLink;
		$html=str_replace(array_keys($arr),$arr,$html);
		
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
		$mailObj->addPlain($html);
		$mailObj->setHTML($mailObj->encodeMsg($html));
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
		$sql="SELECT id,feuser_uid FROM tx_feregistrationprocess_user_info WHERE content='$token' AND type='userconfirm_token'";
		
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$id=$row["id"];
			$feuser_uid=$row["feuser_uid"];
			/*
			$arr=array();
			$fields=$this->modelLib->getAllFields($this);
			foreach($fields as $field){
				$arr["###".$field->name."_VALUE###"]=$this->getValueFromDB($field,$id);
			}
			
			$newToken=md5(rand());
			
			
			$arr["###ADMIN_ACCEPT###"]="http://no1.k1on.de/cms/index.php?id=38&admin_accept=$newToken";
			$arr["###ADMIN_DECLINE###"]="http://no1.k1on.de/cms/index.php?id=38&admin_decline=$newToken";
			$sql="INSERT INTO tx_feregistrationprocess_user_info (id,feuser_uid,type,content) VALUES('$id','$feuser_uid','admin_token','$newToken')";
			//t3lib_div::debug($sql);
			$GLOBALS['TYPO3_DB']->sql_query($sql);
			
			*/
			$pid=getTSValue('config.usersConfirmedPid',$this->conf);
			$group=getTSValue('config.usersConfirmedGroup',$this->conf);
			
			$disabled=0;
			if ($this->requireAdminConfirm) $disabled=1;
			$sql="UPDATE fe_users SET pid='$pid',usergroup='$group',disable='$disabled' WHERE uid='$feuser_uid'";
			
			$GLOBALS['TYPO3_DB']->sql_query($sql);
			$content="Noch hardgecodeter krempel";
			//t3lib_div::debug(array("y"=>$this->templatefile));
			//$mailTemplate=$this->cObj->getSubpart($this->templatefile,"ADMIN_MAIL_USER_CONFIRMATION");
			/*
			$mailCont=str_replace(array_keys($arr),$arr,$mailTemplate);
			$adminAddr=$this->conf["config."]["adminMail"];
			
			$mailObj = t3lib_div::makeInstance('t3lib_htmlmail');
			$mailObj->start();
			$mailObj->recipient = $adminAddr;
			$mailObj->subject = 'Neue Registrierung';
			$mailObj->from_email = 'noreply@apotheken.de';
			$mailObj->from_name = 'No Reply';
			$mailObj->addPlain(strip_tags($mailCont));
			$mailObj->setHTML($mailObj->encodeMsg($mailCont));
			$success=$mailObj->send($adminAddr);
			*/
			if ($this->requireAdminConfirm) $this->generateAdminMailUserConfirmation($feuser_uid,$id);
			
		} else {
			$content="ungültiger Token";
		}
		return $content;
	}
	function renderAdminConfirmation($action,$token) {
		if ($action=="confirm") {
			$token=mysql_real_escape_string($token);
			$sql="SELECT id,feuser_uid FROM tx_feregistrationprocess_user_info WHERE content='$token' AND type='adminconfirm_token'";
			$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
			if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$id=$row["id"];
				$feuser_uid=$row["feuser_uid"];
				$pid=$this->conf["config."]["usersAdminConfirmedPid"];
				$group=$this->conf["config."]["usersAdminConfirmedGroup"];
				$sql="UPDATE fe_users SET disable='0',usergroup='$group',pid='$pid' WHERE uid='$feuser_uid'";
				
				$GLOBALS['TYPO3_DB']->sql_query($sql);
				$content="User Confirmed";
			} else {
				$content="ungültiger Token";
			}

		}
		if ($action=="decline") {
			$token=mysql_real_escape_string($token);
			$sql="SELECT id,feuser_uid FROM tx_feregistrationprocess_user_info WHERE content='$token' AND type='adminconfirm_token'";
			$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
			if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$id=$row["id"];
				$feuser_uid=$row["feuser_uid"];
				$sql="UPDATE fe_users SET deleted='1',usergroup='$group',pid='$pid' WHERE uid='$feuser_uid'";
				$GLOBALS['TYPO3_DB']->sql_query($sql);
				$content="User Deleted";
			} else {
				$content="ungültiger Token";
			}
		}
				return $content;
	}
	
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/fe_registration_process/pi1/class.tx_feregistrationprocess_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/fe_registration_process/pi1/class.tx_feregistrationprocess_pi1.php']);
}

?>
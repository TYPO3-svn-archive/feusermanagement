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
#require_once(t3lib_extMgm::extPath('feusermanagement') . 'class.Field.php');
#require_once(t3lib_extMgm::extPath('feusermanagement') . 'class.registration_model.php');
#require_once(t3lib_extMgm::extPath('feusermanagement') . 'class.registration_view.php');
require_once(t3lib_extMgm::extPath('feusermanagement') . 'lib_general.php');
#require_once(t3lib_extMgm::extPath('feusermanagement') . 'class.registration_validation.php');

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
	var $validateLib=null;
	var $userMailTemplate='';
	var $adminMailTemplate='';
	var $userMailNotifyTemplate='';
	var $requiredMarker='';
	var $templatefile='';
	var $requireUserConfirm=0;
	var $requireAdminConfirm=0;
	var $currStep=0;
	var $baseURL='';
	var $templateFileName='';
		# default fe_user image folder, see: $TCA['fe_users']['columns']['image']['config']['uploadfolder']
		# if you change in TS, also change TCA
	var $uploadDir='uploads/pics/';
	var $errCount=0;

	function init() {
		$this->baseURL=getTSValue('config.baseURL',$GLOBALS['TSFE']->tmpl->setup);
		$this->requiredMarker=getTSValue('config.requiredMarker',$this->conf);
		$this->modelLib=t3lib_div::makeInstance('tx_feusermanagement_model');
		$this->viewLib=t3lib_div::makeInstance('tx_feusermanagement_view');
		$this->validateLib=t3lib_div::makeInstance('tx_feusermanagement_validation');
		if (getTSValue('config.userConfirmation',$this->conf)) $this->requireUserConfirm=1;
		if (getTSValue('config.adminConfirmation',$this->conf)) $this->requireAdminConfirm=1;
		$this->templateFileName=getTSValue('config.template',$this->conf);
		$this->templatefile = $this->cObj->fileResource($this->templateFileName);
		$this->userMailTemplate=$this->cObj->getSubpart($this->templatefile,"USER_MAIL_CONFIRMATION");
		$this->userMailNotifyTemplate=$this->cObj->getSubpart($this->templatefile,"USER_MAIL_NOTIFICATION");
		$this->adminMailTemplate=$this->cObj->getSubpart($this->templatefile,"ADMIN_MAIL_USER_CONFIRMATION");
		if ($uploadDir=getTSValue('config.upload_dir',$this->conf)) $this->uploadDir=$uploadDir;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$content: ...
	 * @param	[type]		$conf: ...
	 * @return	[type]		...
	 */
	function main($content,$conf)	{
		global $TYPO3_CONF_VARS;
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj=1;

		$this->init();
		#if (!$this->baseURL) return 'config.baseURL not set';
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
				$this->writeLastStepToSession($step);
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
		$finalTempl=$this->cObj->getSubpart($this->templatefile,'###FINAL_SCREEN###');
		$fields=$this->modelLib->getCurrentFields($this->conf['steps.'][$step.'.'],$this);

		### HOOK processFields ###
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['processFields'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['processFields'] as $userFunc) {
				$params = array(
					'fields' => &$fields,
					'step' =>$step,
				);
				t3lib_div::callUserFunction($userFunc, $params, $this);
			}
		}

		### GET JS FIELDS ###
		$jsCodeOnBlur=array();
		foreach($fields as $field) {
			$js="";
			$js=$this->viewLib->getOnBlurJS($field,$this);
			if (strlen($js)>0) {
				$jsCodeOnBlur[]=$js;
			}
		}
		$formJS=$this->viewLib->wrapFormJS($fields,$step,$this);
		### GET HTML ###
		$markerArr=array();
		$htmlFields=array();
		$allFields=$this->modelLib->getAllFields($this);
		
		$markerArr["###SUBMIT###"]='<input type="submit" value="'.$this->pi_getLL('submit_label','',FALSE).'" />';
		$markerArr["###STEP###"]=$step." / ".$lastStep;

		###OLD VALUES###
		$markerArr=$this->viewLib->fillMarkers($allFields,$markerArr,$this);
		
		### NAVIGATION BAR ###
		$navigation="";
		$backLinks=$this->getBacklinks($step);
		foreach($backLinks as $backlink) {
			$navigation.=$backlink;
		}
		$markerArr["###NAVIGATION###"]=$navigation;

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
		if ($final) {
			$content=str_replace(array_keys($markerArr),$markerArr,$finalTempl);
			$this->userMailTemplate=str_replace(array_keys($markerArr),$markerArr,$this->userMailTemplate);
			$this->adminMailTemplate=str_replace(array_keys($markerArr),$markerArr,$this->adminMailTemplate);
			$this->createNewFEUser();
			$GLOBALS["TSFE"]->fe_user->setKey('ses','ccm_reg_step',"0");
			$this->modelLib->clearValuesInSession($this);
		} else {
			$content=str_replace(array_keys($markerArr),$markerArr,$template);
		}

		$js='
			<script type="text/javascript">
				'.implode(" ",$jsCodeOnBlur).'
				'.$formJS.'
			</script>
		';
		$GLOBALS['TSFE']->additionalHeaderData['feusermanagementjs']=$js;
		
		return $this->pi_wrapInBaseClass($content);
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$step: ...
	 * @return	[type]		...
	 */
	function validateInputLastStep($step) {

		$fields=$this->modelLib->getCurrentFields($this->conf["steps."][$step."."],$this);
		$valid=true;
		foreach($fields as $field) {
			$valid=$this->validateLib->validateField($field,$this)&&$valid;
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

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$step: ...
	 * @return	[type]		...
	 */
	function writeLastStepToSession($step) {
		$fields=$this->modelLib->getCurrentFields($this->conf['steps.'][$step.'.'],$this);
		foreach($fields as $field) {

			
			$id=$field->htmlID;
			if (isset($this->piVars[$id])) {
				$value=$this->piVars[$id];
				
				$this->modelLib->saveValueToSession($field->name,$value,$this);
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
			if ($field->type=='upload') {
				$tmpFile=$_FILES['tx_feusermanagement_pi1']['tmp_name'][$field->htmlID];
				$origFile=$_FILES['tx_feusermanagement_pi1']['name'][$field->htmlID];
				if (!$tmpFile||!$origFile) {
					$this->modelLib->saveValueToSession($field->name,'',$this);
					continue;
				}
				$value=$tmpFile.chr(1).$origFile;
				$this->modelLib->saveValueToSession($field->name,$value,$this);
				
			}
		}
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$field: ...
	 * @return	[type]		...
	 */
	function getValuesFromUserMapString($string) {
		
		$allFields=$this->modelLib->getAllFields($this);
		$arr=explode('+',$string);
		$content='';
		foreach($arr as $key) {
			if (substr($key,0,1)=='"' && substr($key,strlen($key)-1)=='"') {
				$content.=mysql_real_escape_string(substr($key,1,strlen($key)-2));
			} else {
				if (array_key_exists($key,$allFields)) {
					$val=$this->getValueFromSession($allFields[$key]);
					if (is_array($val)) $val=implode(',',$val);
					$content.=$val;
				}
				else {
					return 'invalid configuration';
				}
			}
		}
		return $content;
	}
	function getValueFromSession($field,$dummy=0) {
		$sesVal=$this->modelLib->getValueFromSession($field->name,$this);
		
		if ($sesVal) return $sesVal;
		if ($field->value) return $field->value; //Wert der übers Typoscript übergeben wurde, für z.B. Hidden-Fields
		return;
	}

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function createNewFEUser() {

		$allFields=$this->modelLib->getAllFields($this);
		$map=array();
		$maparr=getTSValue('feuser_map',$this->conf);
		foreach($maparr as $fe_name=>$field_name) {
			$currField=$allFields[$field_name];
			// If Upload-File Special handling is needed
			if (is_object($currField)&&$currField->type=='upload') {
				$files=explode(chr(1),$this->getValueFromSession($allFields[$field_name]));
				if (count($files)==2) {
					$tempFilename=$files[0];
					$origFilename=$files[1];
					$path=t3lib_div::getIndpEnv('TYPO3_DOCUMENT_ROOT').'/'.$this->uploadDir;
					$newName=$this->modelLib->getFreeFilename($path,$origFilename,$this->conf['config.']['upload_file_prefix']);
					move_uploaded_file($tempFilename,$path.$newName);
					#$map[$fe_name]=$this->modelLib->secureDataBeforeInsertUpdate($this->uploadDir.$newName);
					$map[$fe_name]=$this->modelLib->secureDataBeforeInsertUpdate($newName);
				} 
				
				continue;
			}
			// get the Value - already formated correctly for the DB
			$map[$fe_name]=$this->modelLib->secureDataBeforeInsertUpdate($this->getValuesFromUserMapString($field_name),$this);
			if ($fe_name=='password') {
				// save cleartextpassword to Session
				$this->modelLib->saveValueToSession('password',$map['password'],$this);
				// If wanted - do md5-hashing
				if (getTSValue('config.useMD5',$this->conf)) {
					$map['password']=md5($map['password']);
				}
			}
		}

		if (getTSValue('config.autogenPwd',$this->conf)) {
			$pwd=rand(100000,999999);
			$clearTextPwd=$pwd;
			$this->modelLib->saveValueToSession('password',$clearTextPwd,$this);
			#$GLOBALS["TSFE"]->fe_user->setKey('ses',$this->prefixId.'password',$clearTextPwd);
			if (getTSValue('config.useMD5',$this->conf)) {
				$pwd=md5($pwd);
			}
			$map['password']=$pwd;
		}

		$token=md5(rand());
		$map['registration_token']=$token;
		$keys=implode(",",array_keys($map));
		if ($keys) $keys=','.$keys;
		$values=implode("','",$map);
		if ($values) $values=",'".$values."'";


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
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['feuser_write'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['feuser_write'] as $userFunc) {
				$params = array(
					'action' => 'new',
					'uid'=>$id,
				);
				t3lib_div::callUserFunction($userFunc, $params, $this);
			}
		}
		
		######## autologin / redirect #######
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
					$url=$this->baseURL.$this->cObj->getTypoLink_URL($redirPid);
					if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['finish_redirect'])) {
						foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['finish_redirect'] as $userFunc) {
							$params = array(
								'url' => &$url,
							);
							t3lib_div::callUserFunction($userFunc, $params, $this);
						}
					}
					header('Location: '.$url);
					
				}
			}
		}

		if (getTSValue('config.userConfirmation',$this->conf)) {
			$this->generateUserMailConfirmation($id);
		} else {
			//FIXME: Check Parameter 
			###if ($this->requireAdminConfirm) $this->generateAdminMailUserConfirmation($row); 
		}
		if (getTSValue('config.userNotify',$this->conf)) $this->generateUserMailNotify($id);
	}

	/**
	 * 
	 * @param unknown_type $id
	 * @return unknown_type
	 */
	function generateUserMailConfirmation($id) {
		$html=$this->userMailTemplate;
		$sql='SELECT * FROM fe_users WHERE uid='.$id;
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		$user = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$confirmLink=$this->baseURL.$this->cObj->getTypoLink_URL($GLOBALS['TSFE']->id,array($this->prefixId.'[userConfirmationToken]'=>$user['registration_token'],$this->prefixId.'[fe_user]'=>$id));
		$markerArr=array();
		#$markerArr['###CONFIRMATION_LINK###']=$confirmLink;
		$markerArr['###CONFIRMATION_LINK###']="<a href=".$confirmLink.">".$confirmLink."</a>";
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
		$success=$mailObj->send($user["email"]);

		return $success;
	}
	
	function generateUserMailNotify($id) {
		$html=$this->userMailNotifyTemplate;
		
		$sql='SELECT * FROM fe_users WHERE uid='.$id;
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		$user = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$confirmLink=$this->baseURL.$this->cObj->getTypoLink_URL($GLOBALS['TSFE']->id,array($this->prefixId.'[userConfirmationToken]'=>$user['registration_token'],$this->prefixId.'[fe_user]'=>$id));
		$markerArr=array();
		$markerArr=array_merge($markerArr,$this->viewLib->getFE_User_Marker($id));
		$markerArr['###FEUSER_PASSWORD###']=$this->modelLib->getValueFromSession('password',$this);
		#$markerArr['###FEUSER_PASSWORD###']=$GLOBALS["TSFE"]->fe_user->getKey('ses',$this->prefixId.'password');
		
		$html=str_replace(array_keys($markerArr),$markerArr,$html);

		$mailObj = t3lib_div::makeInstance('t3lib_htmlmail');
		$mailObj->start();
		$mailObj->recipient = $user['email'];
		$mailObj->subject = getTSValue('config.userMailSubject',$this->conf);
		$mailObj->from_email = getTSValue('config.mailFromEMail',$this->conf);
		$mailObj->from_name = getTSValue('config.mailFromName',$this->conf);
		$mailObj->addPlain($html);
		$mailObj->setHTML($mailObj->encodeMsg($html));
		$success=$mailObj->send($user['email']);
		
		return $success;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$row_feuser: ...
	 * @return	[type]		...
	 */
	function generateAdminMailUserConfirmation($row_feuser) {

		$markerArr=array();
		foreach ($row_feuser as $key=>$value) {
			$markerArr['###FE_'.strtoupper($key).'###']=$value;
		}
		$adminToken=$row_feuser['registration_token'];#md5($TYPO3_CONF_VARS['SYS']['encryptionKey'].$row['registration_token']);
		$confirmLink=$this->baseURL.$this->pi_getPageLink($GLOBALS['TSFE']->id,$target='',$urlParameters=array($this->prefixId.'[adminAction]'=>'confirm',$this->prefixId.'[token]'=>md5($adminToken),$this->prefixId.'[fe_user]'=>$row_feuser['uid']));
		$declineLink=$this->baseURL.$this->pi_getPageLink($GLOBALS['TSFE']->id,$target='',$urlParameters=array($this->prefixId.'[adminAction]'=>'decline',$this->prefixId.'[token]'=>md5($adminToken),$this->prefixId.'[fe_user]'=>$row_feuser['uid']));

		$markerArr["###ADMIN_ACCEPT###"]="<a href=".$confirmLink.">".$confirmLink."</a>";
		$markerArr["###ADMIN_DECLINE###"]="<a href=".$declineLink.">".$declineLink."</a>";
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

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$arr: ...
	 * @return	[type]		...
	 */
	function prepareMessage($arr) {
		if (is_array($arr)&&(count($arr)>0)) {
			$text=$arr[0];
			for ($i=1;$i<count($arr);$i++) {
				$text=str_replace("###".$i."###",$arr[$i],$text);
			}
		}
		return $text;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$step: ...
	 * @return	[type]		...
	 */
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
		//FIXME: mehrstufig grad nicht möglich
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

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$value: ...
	 * @return	[type]		...
	 */
	function getString($value) {
		if (strpos($value,"LL_user")===0 || strpos($value,"LL_field")===0) {
			$pos=max(strpos($value,"user"),strpos($value,"field"));
			$str=$this->pi_getLL(substr($value,$pos),'',FALSE);
		} else {
			$str=$value;
		}
		return $str;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$key: ...
	 * @return	[type]		...
	 */
	function removeDot($key) {
		if ($dotpos=strpos($key,".")) {
			$key=substr($key,0,$dotpos);
		}
		return $key;
	}

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
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

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$token: ...
	 * @return	[type]		...
	 */
	function renderMailConfirmation($token) {
		$token=mysql_real_escape_string($token);
		$feuser_uid=(int)$this->piVars['fe_user'];
		$sql='SELECT * FROM fe_users WHERE uid='.$feuser_uid.' AND registration_token="'.$token.'"';

		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$pid=getTSValue('config.usersConfirmedPid',$this->conf);
			$group=getTSValue('config.usersConfirmedGroup',$this->conf);
			$token=md5(rand());
			$disabled=0;
			if ($this->requireAdminConfirm) $disabled=1;
			//FIXME : disabled
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

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function clearSessionData() {
		$this->modelLib->clearValuesInSession($this);
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$action: ...
	 * @param	[type]		$token: ...
	 * @return	[type]		...
	 */
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
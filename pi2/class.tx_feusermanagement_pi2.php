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
 * Plugin 'ccm_registration_edit' for the 'fe_registration_process' extension.
 *
 * @author	Florian Bachmann <fbachmann@cross-content.com>
 * @package	TYPO3
 * @subpackage	tx_feregistrationprocess
 */
class tx_feusermanagement_pi2 extends tslib_pibase {
	var $prefixId      = 'tx_feusermanagement_pi2';		// Same as class name
	var $scriptRelPath = 'pi2/class.tx_feusermanagement_pi2.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'feusermanagement';	// The extension key.
	var $pi_checkCHash = true;
	var $feuser_uid='';
	var $baseURL='';
	var $requiredMarker='';
	var $modelLib=null;
	var $viewLib=null;
	var $templateFileName='';
	var $templatefile='';
	var $step=0;
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The		content that is displayed on the website
	 */
	function init() {
		$this->baseURL=getTSValue('config.baseURL',$GLOBALS['TSFE']->tmpl->setup);
		$this->requiredMarker=getTSValue('config.requiredMarker',$this->conf);
		$this->modelLib=t3lib_div::makeInstance('registration_model');
		$this->viewLib=t3lib_div::makeInstance('registration_view');
		$this->feuser_uid=$GLOBALS['TSFE']->fe_user->user['uid'];

		$this->templateFileName=getTSValue('config.template',$this->conf);
		$this->templatefile = $this->cObj->fileResource($this->templateFileName);
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$content: ...
	 * @param	[type]		$conf: ...
	 * @return	[type]		...
	 */
	function main($content,$conf)	{

		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj=1;

		$this->init();

		if (!$this->baseURL) return 'config.baseURL not set';
		$checkInput=true;

		if (!$this->feuser_uid) {
			return 'No user is logged in';
		}
		$step=$GLOBALS["TSFE"]->fe_user->getKey("ses","ccm_prof_step");

		### SPRUNG AUF VORGÄNGERSEITE? ###
		if ($this->piVars['backlinkToStep']&&$GLOBALS["TSFE"]->fe_user->getKey("ses","ccm_reg_step")) { ###SESSION EXISTIERT, UND ER WILL ZURÜCK ###
			$back=$this->piVars["backlinkToStep"];
			$step=$GLOBALS["TSFE"]->fe_user->getKey("ses","ccm_reg_step");
			$back=(int)$back;
			if (($back>0) && ($back<$step)) {
				$GLOBALS["TSFE"]->fe_user->setKey("ses","ccm_reg_step",$back);
				$checkInput=false;
			}
		}

		if (!$step) {
			$GLOBALS["TSFE"]->fe_user->setKey("ses","ccm_prof_step",1);
			$step=1;
			$checkInput=false;
		} else {
			$step=$GLOBALS["TSFE"]->fe_user->getKey("ses","ccm_prof_step");
			if (($checkInput)&&($this->validateInputLastStep($step))) {
				$this->writeLastStepToSession($step);
				$GLOBALS["TSFE"]->fe_user->setKey("ses","ccm_prof_step",$step+1);
				$step=$step+1;
			}
		}
		### CHECK FOR PROFILATION FINALIZED ###
		$lastStep=$this->getLastStepNr();
		$final=false;

		if ($step>$lastStep) {
			$final=true;
		}
		$this->step=$step;
		### GET TEMPLATES ###
		$template=$this->cObj->getSubpart($this->templatefile,"STEP_".$step);
		$errorTempl=$this->cObj->getSubpart($this->templatefile,"ERROR_PART");
		$finalTempl=$this->cObj->getSubpart($this->templatefile,"FINAL_SCREEN");
		$deleteTempl=$this->cObj->getSubpart($this->templatefile,"DELETED");
		$errorHTML=str_replace("ERROR_MSG",$this->errMsg,$errorTempl);
		$fields=$this->modelLib->getCurrentFields($this->conf["steps."][$step."."],$this,1);

		### HOOK processFields ###
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['processFields_pi2'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['processFields_pi2'] as $userFunc) {
				$params = array(
					'fields' => &$fields,
					'step' =>$step,
				);
				t3lib_div::callUserFunction($userFunc, $params, $this);
			}
		}

		if ($this->piVars['deleteAccount']) {
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['preDeleteAccount'])) {
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['preDeleteAccount'] as $userFunc) {
					$params = array(
						'uid'=>$this->feuser_uid
					);
					t3lib_div::callUserFunction($userFunc, $params, $this);
				}
			}

			$sql='DELETE FROM fe_users WHERE uid='.$GLOBALS['TSFE']->fe_user->user['uid'];
			$GLOBALS['TYPO3_DB']->sql_query($sql);
			$content=$deleteTempl;
			return $this->pi_wrapInBaseClass($content);
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
		### JS SUBMIT ###
		
		$formJS=$this->viewLib->wrapFormJS($fields,$step,$this);
	

		### GET HTML ###
		$markerArr=array();
		$htmlFields=array();
		$allFields=$this->modelLib->getAllFields($this,1);

		$markerArr["###SUBMIT###"]='<input type="submit" value="'.$this->pi_getLL('submit_label','',FALSE).'" />';
		$markerArr["###STEP###"]=$step." / ".$lastStep;

		$markerArr['###DELETE_URL###']=$this->cObj->typoLink_URL(array('parameter'=>$GLOBALS['TSFE']->id,'useCacheHash'=>true,'additionalParams'=>'&deleteAccount=1&tstamp='.time()));
			###OLD VALUES###
		$markerArr=$this->viewLib->fillMarkers($allFields,$markerArr,$this);
		$markerArr=array_merge($markerArr,$this->viewLib->getFE_User_Marker());


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
			$this->updateFEUser();
			$markerArr=array_merge($markerArr,$this->getFE_User_Marker());
			$template=str_replace(array_keys($markerArr),$markerArr,$finalTempl);
		}
		$content.='
			<script type="text/javascript">
				'.implode(" ",$jsCodeOnBlur).'
				'.$formJS.'
			</script>
		';

		$content.=$template;

		if ($final) {
		### SESSION LÖSCHEN ###
			$GLOBALS["TSFE"]->fe_user->setKey("ses","ccm_prof_step","0");
		}
		return $this->pi_wrapInBaseClass($content);
	}

	

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$field: ...
	 * @return	[type]		...
	 */
	function get_fe_fieldname($field) {
		if (is_array($this->conf["feuser_map."])) {
			foreach($this->conf["feuser_map."] as $key=>$value) {
				if($value==$field->dbName) {
					return $key;
				}
			}
		}
		return "";
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$step: ...
	 * @return	[type]		...
	 */
	function writeLastStepToSession($step) {

		$fields=$this->modelLib->getCurrentFields($this->conf["steps."][$step."."],$this);
		foreach($fields as $field) {

			$name=$field->dbName;
			$id=$field->htmlID;
			if (isset($this->piVars[$id])) {
				$value=$this->piVars[$id];
				$GLOBALS["TSFE"]->fe_user->setKey('ses',$this->prefixId.$field->name,$value);
				### HOOK afterValueInsert ###
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['afterValueInsert_pi2'])) {
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['afterValueInsert_pi2'] as $userFunc) {
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

	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function updateFEUser() {
		$allFields=$this->modelLib->getAllFields($this);

		$map=array();

		$maparr=getTSValue('feuser_map',$this->conf);
		foreach($maparr as $fe_name=>$field_name) {
			$map[$fe_name]=mysql_real_escape_string($this->getValueFromSession($allFields[$field_name]));
			if ($fe_name=='password') {
				if (getTSValue('config.useMD5',$this->conf)) {
					$map['password']=md5($map['password']);
				}
			}
		}

		foreach ($map as $key=>$value) {
			$updateStr.=(strlen($updateStr))?',':'';
			$updateStr.=$key.'="'.$value.'"';
		}

		$sql="UPDATE fe_users SET ".$updateStr." WHERE uid='".$this->feuser_uid."'";
		$GLOBALS['TYPO3_DB']->sql_query($sql);

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['feuser_write'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['feuser_write'] as $userFunc) {
				$params = array(
					'action' => 'update',
				);
				t3lib_div::callUserFunction($userFunc, $params, $this);
			}
		}
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
	 * @param	[type]		$value: ...
	 * @return	[type]		...
	 */
	function getString($value) {
		if (strpos($value,"LL_user")===0) {
			$str=$this->pi_getLL(substr($value,strpos($value,"user")),'',FALSE);
		} else {
			$str=$value;
		}
		return $str;
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
	 * @param	[type]		$field: ...
	 * @return	[type]		...
	 */
	function getValueFromSession($field) {
		$sesVal=$GLOBALS["TSFE"]->fe_user->getKey('ses',$this->prefixId.$field->name);
		if ($sesVal) return $sesVal;
		if ($field->value) return $field->value; //Wert der übers Typoscript übergeben wurde, für z.B. Hidden-Fields

		// TODO: Aus der fe_user tablle wert auslesen

		return '';
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$step: ...
	 * @return	[type]		...
	 */
	function getBacklinks($step) {
		$steps=array();
		if (is_array($this->conf["config."])) {
			if (is_array($this->conf["config."]["progressList."])) {
				//t3lib_div::debug($this->conf["config."]["progressList."]);
				if (array_key_exists("inactive.",$this->conf["config."]["progressList."])) {
					$inactiveTS=$this->conf["config."]["progressList."]["inactive."];
				}
				if (array_key_exists("active.",$this->conf["config."]["progressList."])) {
					$activeTS=$this->conf["config."]["progressList."]["active."];
				}
				if (array_key_exists("current.",$this->conf["config."]["progressList."])) {
					$currentTS=$this->conf["config."]["progressList."]["current."];
				}
			}
		}
		foreach($this->conf["steps."] as $key=>$TSStep) {
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
			//t3lib_div::debug(array($currentTS,$activeTS,$inactiveTS));
			if ($key>$step) $state="inactive";
			if ($state=="current") {
				$html="<span>$label</span>";
				$html=$this->cObj->stdWrap($html,$currentTS);
			}
			if ($state=="active") {
				$html="<a href='index.php?id=38&backlinkToStep=$key'>$label</a>";
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
	 * @param	[type]		$step: ...
	 * @return	[type]		...
	 */
	function validateInputLastStep($step) {

		$fields=$this->modelLib->getCurrentFields($this->conf["steps."][$step."."],$this);
		$valid=true;
		foreach($fields as $field) {
			if ($field->required) {
				$name=$field->name;
				$id=$field->htmlID;
				if (!(isset($this->piVars[$id]) && ($this->piVars[$id]))) {
					/*
					$value=$_POST[$id];
					$valueEsc=mysql_real_escape_string($value);
					$dbNameEsc=mysql_real_escape_string($field->dbName);
					$sql="SELECT * FROM tx_feregistrationprocess_user_info WHERE type='".$dbNameEsc."' AND content='".$valueEsc."'";
					$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
					$found=false;
					while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						$found=true;
					}
					*/
					if (true||!$found) {
						$this->errMsg=$this->prepareMessage(array($this->pi_getLL('not_enter','',FALSE),$field->label));
						t3lib_div::debug(array($field,$this->errMsg));
						$valid=false;
					}
				}

			}

			if ($field->unique) {
				$id=$field->htmlID;
				$value=mysql_real_escape_string($this->piVars[$id]);
				$sql='SELECT * FROM fe_users WHERE '.$field->fe_user.'="'.$value.'" AND NOT uid="'.$this->feuser_uid.'"';
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
					$this->errMsg=$this->prepareMessage(array($this->pi_getLL('equal_error','',FALSE),$field->name,$fields[$ref]->name));
				}
			}
			if ($field->onBlurValidation) {
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
					'uid' => $this->uid,
					'step' => $step,
					'fields'=>$fields,
					'valid'=>$valid
				);
				$valid=t3lib_div::callUserFunction($userFunc, $params, $this);
			}
		}
		return $valid;
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/feusermanagement/pi2/class.tx_feusermanagement_pi2.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/feusermanagement/pi2/class.tx_feusermanagement_pi2.php']);
}

?>
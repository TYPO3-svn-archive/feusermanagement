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
	var $fe_uid="";
	var $baseURL='';
	var $requiredMarker='';
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content,$conf)	{
		
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->requiredMarker=getTSValue('config.requiredMarker',$this->conf);
		$this->baseURL=getTSValue('config.baseURL',$GLOBALS['TSFE']->tmpl->setup);
		if (!$this->baseURL) return 'config.baseURL not set';
		
		$this->modelLib=t3lib_div::makeInstance('registration_model');
		$this->viewLib=t3lib_div::makeInstance('registration_view');
		
		$checkInput=true;
		
		$fe_uid=$GLOBALS['TSFE']->fe_user->user['uid'];
		$this->fe_uid=$fe_uid;
		if (!$fe_uid) {
			return 'No user is logged in';
		}
		
		### Template ###
		$confArr=t3lib_div::getIndpEnv("TYPO3_DOCUMENT_ROOT");
		$templateFile=getTSValue('config.template',$this->conf);
		if (!file_exists($templateFile)) {
			return "Template File: '".$templateFile."' not found";
		}
		$templatef = $this->cObj->fileResource($templateFile);
		
		
		$this->templatefile=$templatef;
		//t3lib_div::debug(array($templateFile,$templatef));
		$step=$GLOBALS["TSFE"]->fe_user->getKey("ses","ccm_prof_step");
		
		### SPRUNG AUF VORGÄNGERSEITE? ###
		if ($_GET["backlinkToStep"]&&$step) { ###SESSION EXISTIERT, UND ER WILL ZURÜCK ###
			$back=$_GET["backlinkToStep"];
			$step=$GLOBALS["TSFE"]->fe_user->getKey("ses","ccm_prof_step");
			$back=(int)$back;
			if (($back>0) && ($back<$step)) {
				$GLOBALS["TSFE"]->fe_user->setKey("ses","ccm_prof_step",$back);
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
				$this->writeLastStepToTable($step);
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
		### GET TEMPLATES ###
		$template=$this->cObj->getSubpart($templatef,"STEP_".$step);
		$errorTempl=$this->cObj->getSubpart($templatef,"ERROR_PART");
		$finalTempl=$this->cObj->getSubpart($templatef,"FINAL_SCREEN");
		$deleteTempl=$this->cObj->getSubpart($templatef,"DELETED");
		$errorHTML=str_replace("ERROR_MSG",$this->errMsg,$errorTempl);
		$fields=$this->modelLib->getCurrentFields($this->conf["steps."][$step."."],$this);
		
		
		if ($_GET['deleteAccount']) {
			
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['preDeleteAccount'])) {
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['preDeleteAccount'] as $userFunc) {
					$params = array(
						'uid'=>$this->fe_uid
					);
					t3lib_div::callUserFunction($userFunc, $params, $this);

				}
			}
			
			$sql='DELETE FROM fe_users WHERE uid='.$fe_uid;
			$GLOBALS['TYPO3_DB']->sql_query($sql);
			$content=$deleteTempl;
			return $this->pi_wrapInBaseClass($content);
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
			function ccm_check_FormSubmit() {
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
		$markerArr["###FORM_BEGIN###"]="<form name='ccm_reg_form' action='".$this->pi_linkTP_keepPIvars_url()."' method='POST' onSubmit='return ccm_check_FormSubmit();'>";
		$markerArr["###FORM_END###"]="</form>";
		$markerArr['###DELETE_URL###']=$this->cObj->typoLink_URL(array('parameter'=>$GLOBALS['TSFE']->id,'useCacheHash'=>true,'additionalParams'=>'&deleteAccount=1&tstamp='.time()));
			###OLD VALUES###
		$markerArr=$this->viewLib->fillMarkers($allFields,$markerArr,$this);
		$markerArr=array_merge($markerArr,$this->getFE_User_Marker());

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
			
			$template=str_replace(array_keys($markerArr),$markerArr,$finalTempl);
			$this->updateFEUser();
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
			$GLOBALS["TSFE"]->fe_user->setKey("ses","ccm_prof_step","0");
		}
		return $this->pi_wrapInBaseClass($content);
	}
	function getFE_User_Marker() {
		$arr=array();
		foreach ($GLOBALS['TSFE']->fe_user->user as $attrib=>$value) {
			$arr["###FEUSER_".$attrib."###"]=$value;
		}
		return $arr;
	}
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
	function writeLastStepToTable($step) {
		
		$fields=$this->modelLib->getCurrentFields($this->conf["steps."][$step."."],$this);
		foreach($fields as $field) {
			if ($field->toDB) {
				$name=$field->dbName;
				$id=$field->htmlID;
				if (isset($_POST[$id])) {
					$value=$_POST[$id];
					
					$sql="DELETE FROM tx_feregistrationprocess_user_info WHERE feuser_uid='".$this->fe_uid."' AND type='$name'";
					$GLOBALS['TYPO3_DB']->sql_query($sql);
					$sql="INSERT INTO tx_feregistrationprocess_user_info (type,content,feuser_uid,istemp) VALUES('".$name."','".$value."','".$this->fe_uid."','0')";
					
					$GLOBALS['TYPO3_DB']->sql_query($sql);
					### HOOK afterValueInsert ###
					if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['afterValueInsert'])) {
						foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['afterValueInsert'] as $userFunc) {
							$params = array(
								'fe_uid' => $this->fe_uid,
								'field' => $field,
								'value'=>$value,
							);
							t3lib_div::callUserFunction($userFunc, $params, $this);
						}
					}
				}
			}
		}
	}
	function updateFEUser() {
		$allFields=$this->modelLib->getAllFields($this);
		$updateStr="";
		if (is_array($this->conf["feuser_map."])) {
			foreach($this->conf["feuser_map."] as $key=>$value) {
				$show=0;
				if ($key=="tx_personalheader_apo_id") $show=1;
				//if ($show) t3lib_div::debug(array($key,$value,$this->getValueFromDB($allFields[$value])));
				$field=$allFields[$value];
				if ($field->type=='password') {
					//t3lib_div::debug(array('a'));
					$val=$this->getValueFromDB($allFields[$value]);
					$id=$field->htmlID;
					//t3lib_div::debug(array($id,$_POST[$id]));
					if ($_POST[$id]) {
						//Passwortfeld, und Passwort neu gesetzt
						$val=encryptPW($_POST[$id],$this);
						if (strlen($updateStr)>0) $updateStr.=", ";
						$updateStr.= $key."='".$val."'";
						//t3lib_div::debug(array('b'));
					} elseif ($val) {
						//t3lib_div::debug(array('c'));
						//Passwortfeld, und Passwort nicht gesetzt => altes aus der DB
						/*
						if (strlen($updateStr)>0) $updateStr.=", ";
						$val=encryptPW($map[$key],$this);
						$updateStr.= $key."='".$val."'";
						*/
					}
					
					
				} else {
					if (strlen($updateStr)>0) $updateStr.=", ";
					if ($field->type=='checkbox') {
						if ($_POST[$field->htmlID]) {
							$updateStr.=$key."='1'";
						} else {
							$updateStr.=$key."='0'";
						}
					} else {
						if ($_POST[$field->htmlID]) {
							$updateStr.=$key."='".$_POST[$field->htmlID]."'";
						}
						else $updateStr.= $key."='".$this->getValueFromDB($allFields[$value])."'";
					}
					
					
				}
			}
		}
		
		$sql="UPDATE fe_users SET ".$updateStr." WHERE uid='".$this->fe_uid."'";
		
		$GLOBALS['TYPO3_DB']->sql_query($sql);
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
	function removeDot($key) {
		if ($dotpos=strpos($key,".")) {
			$key=substr($key,0,$dotpos);
		}
		return $key;
	}
	function getString($value) {
		if (strpos($value,"LL_user")===0) {
			$str=$this->pi_getLL(substr($value,strpos($value,"user")),'',FALSE);
		} else {
			$str=$value;
		}
		return $str;
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
	function getValueFromDB($field) {
		$fe_uid=$this->fe_uid;
		if (!($field->toDB)) return "";
		
		/*
		$returnVal="";
		$sql="SELECT content FROM tx_feregistrationprocess_user_info WHERE feuser_uid='".$fe_uid."' AND type='".$field->dbName."'";
		if ($res = $GLOBALS['TYPO3_DB']->sql_query($sql)) {
			if ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$returnVal=$row["content"];
			}
		}*/
		//t3lib_div::debug(array($sql,$returnVal));
		if (true) {
			$fe_name=$this->get_fe_fieldname($field);
			if ($fe_name) {
				$sql='SELECT '.$fe_name.' FROM fe_users WHERE uid="'.$fe_uid.'"';
				
				$res = $GLOBALS['TYPO3_DB']->sql_query($sql);
				if ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$returnVal=$row[$fe_name];
				}
				//t3lib_div::debug(array($sql,$returnVal));
			}
			
		}
		//t3lib_div::debug(array($sql,$returnVal));
		//t3lib_div::debug(array($field->name,$returnVal,$sql));
		return $returnVal;
	}
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
	function validateInputLastStep($step) {
		
		$fields=$this->modelLib->getCurrentFields($this->conf["steps."][$step."."],$this);
		$valid=true;
		foreach($fields as $field) {
			if ($field->required) {
				$name=$field->name;
				$id=$field->htmlID;
				if (!(isset($_POST[$id]) && ($_POST[$id]))) {
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
						$valid=false;
					}
				}
				
			}
			
			if ($field->unique) {
				$id=$field->htmlID;
				$value=$_POST[$id];
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
			}
			if ($field->onBlurValidation) {
				switch ($field->onBlurValidation) {
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
						//$pattern=preg_quote($pattern);
						//$pattern="^[a-zA-Z0-9]{8,12}$";
						//t3lib_div::debug(array($pattern,$_POST[$field->htmlID]));
						
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
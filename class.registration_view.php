<?php
	
class registration_view {
	var $emailReg='^[\\w-_\.+]*[\\w-_\.]\@([\\w-_]+\\.)+[\\w]+[\\w]$';
	var $passwordReg='^.*(?=.{6,})(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$';
	function getFieldValidationJS($field,$obj) {
		if (!$field->jsvalidation) return;
		
		$js='';
		$validations=split(',',$field->validation);
		foreach($validations as $validation) {
			switch ($field->validation) {
				case 'email':
					$reg=str_replace(chr(92),chr(92).chr(92),$this->emailReg);
					$js.='
						var '.$field->htmlID.'_val=document.getElementById("'.$field->htmlID.'").value;
						var emailReg = "'.$reg.'";
						var regex = new RegExp(emailReg);
						if (!regex.test('.$field->htmlID.'_val)) {
							doSubmit=false;
							alertMessage="'.$obj->pi_getLL('email_error','',FALSE).'";
						} 
						
					';
					break;
				case 'password':
					$reg=str_replace(chr(92),chr(92).chr(92),$this->passwordReg);
					$js.='
						var '.$field->htmlID.'_val=document.getElementById("'.$field->htmlID.'").value;
						var pwdReg = "'.$reg.'";
						var regex = new RegExp(pwdReg);
						if (!regex.test('.$field->htmlID.'_val)) {
							doSubmit=false;
							alertMessage="'.$obj->pi_getLL('password_error','',FALSE).'";
						}
					';
					break;
				
				case 'regExp':
					$reg=str_replace(chr(92),chr(92).chr(92),$field->regExp);
					$js.='
						var '.$field->htmlID.'_val=document.getElementById("'.$field->htmlID.'").value;
						var userReg'.$field->htmlID.' = "'.$reg.'";
						var regex = new RegExp(userReg'.$field->htmlID.');
						if (!regex.test('.$field->htmlID.'_val)) {
							doSubmit=false;
							alertMessage="'.$obj->pi_getLL('email_error','',FALSE).'";
						} 
						
					';
					break;	
			}
		}
		if ($field->equal) {
			$equalField=$obj->modelLib->getField($field->equal,$obj);
			$js='
				if (document.getElementById("'.$field->htmlID.'").value!=document.getElementById("'.$equalField->htmlID.'").value) {
					doSubmit=false;
					alertMessage="'.$obj->prepareMessage(array($obj->pi_getLL('equal_error','',FALSE),$field->label,$equalField->label)).'";
				}
			';
		}
		if ($field->requires) {
			$reqField=$obj->modelLib->getField($field->requires,$obj);
				$js='
					if (document.getElementById("'.$field->htmlID.'").value && !document.getElementById("'.$reqField->htmlID.'").value) {
						doSubmit=false;
						alertMessage="'.$obj->prepareMessage(array($obj->pi_getLL('required_error','',FALSE),$field->label,$reqField->label)).'";
					}
				';
		}
		if ($field->required) {
			if ($field->type=='checkbox') {
				$js.='
					if (!(document.getElementById("'.$field->htmlID.'").checked)) {
						doSubmit=false;
						alertMessage="'.$field->notCheckedMessage.'";
					}
				';
			} else {
				$js.='
					if (!((document.getElementById("'.$field->htmlID.'").value.length)>0)) {
						doSubmit=false;
						alertMessage="'.$field->notCheckedMessage.'";
					}
				';
			}
		}
		return $js;
	}
	function getFormJS($fieldJSArr,$fields,$step,$obj) {
		$formJS='
function '.$obj->prefixId.'_check_FormSubmit() {
	doSubmit=true;
	alertMessage="";
	//<!--FIELD_JS_START-->
	###FORM_VALIDATING###
	//<!--FIELD_JS_END-->
	if (!doSubmit) {
		###ERROR_ACTION###
	}
	return doSubmit;
}		';
		$submitErrorAction=getTSValue('config.formNoSubmitAction',$obj->conf);
		$formJSMarker=array();
		$formJSMarker['###FORM_VALIDATING###']=implode("\n",$fieldJSArr);
		$formJSMarker['###ERROR_ACTION###']=$submitErrorAction;
		$formJS=str_replace(array_keys($formJSMarker),$formJSMarker,$formJS);
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['formSubmitJS'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['formSubmitJS'] as $userFunc) {
				$params = array(
					'formJS' => &$formJS,
					'functionName' => $obj->prefixId.'_check_FormSubmit',
					'fields' => $fields,
					'step' =>$step
				);
				t3lib_div::callUserFunction($userFunc, $params, $obj);
			}
		}
		return $formJS;
	}
	function getJS($field,$obj) {
		$trueAction="";
		$falseAction="";
		
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['js_actions'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['js_actions'] as $userFunc) {
				
				$params = array(
					'trueAction'=>&$trueAction,
					'falseAction'=>&$falseAction,
					'field'=>$field
				);
				$js=t3lib_div::callUserFunction($userFunc, $params, $obj);
			}
		}
		switch ($field->onBlurValidation) {
			case "email":
				if (!($falseAction)) $falseAction="alert(".$obj->pi_getLL('email_error_value_js','',FALSE).");";
				$js='
				function test'.$field->tempID.'(src) {
				     var emailReg = "^[\\\\w-_\\.+]*[\\\\w-_\\.]\@([\\\\w-_]+\\\\.)+[\\\\w]+[\\\\w]$";
				     var regex = new RegExp(emailReg);
				     if (regex.test(src)) {
						'.$trueAction.'
					 } else if (src.length>0){
						'.$falseAction.'
					 }
				  }
				';
				break;
			case "password":
				if (!($falseAction)) $falseAction="alert('".$obj->pi_getLL('password_error','',FALSE)."');";
				$js='
				function test'.$field->tempID.'(src) {
				     var emailReg = "/^.*(?=.{6,})(?=.*\\\\d)(?=.*[a-z])(?=.*[A-Z]).*$/"; 
				     var regex = new RegExp(emailReg);
				     if (regex.test(src)) {
						'.$trueAction.'
					 } else if (src.length>0){
						'.$falseAction.'
					 }
				  }
				';
				$js='function test'.$field->tempID.'(src) {}';
				//$js="";
			
				break;
			case "regExp":
				$js='
				function test'.$field->tempID.'(src) {
				     var emailReg = "'.$field->onBlurCode.'";
				     var regex = new RegExp(emailReg);
				     if (regex.test(src)) {
						'.$trueAction.'
					 } else {
						'.$falseAction.'
					 }
				  }
				';
				break;
			case "user":
				$js=$field->onBlurCode;
				break;
			case "hook":
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['fieldOnBlur'])) {
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['fieldOnBlur'] as $userFunc) {
						$params = array(
							'field'=>$field,
						);
						$js=t3lib_div::callUserFunction($userFunc, $params, $obj);
					}
				} else {
					$js="";
				}
			
			break;
		}
		return $js;
	}
	function fillMarkers($allFields,$markerArr,$obj) {
		foreach($allFields as $field) {
			$temp="";
			$onBlur="";
			if (!$field->value) {
				$field->value=$obj->getValueFromSession($field);
			}
			if ($field->onBlurValidation) $onBlur=" onblur='test".$field->tempID."(this.value)' ";
			switch ($field->type) {
				case "text":
					if ($_POST[$field->htmlID]) {
						$field->value=$_POST[$field->htmlID];
					}
					else {
						$sql="SELECT content FROM tx_feregistrationprocess_user_info WHERE id='".$this->uid."' AND type='".$field->dbName."'";
						if ($res=$GLOBALS['TYPO3_DB']->sql_query($sql)) {
							$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
						}
					}
					$temp='<input type="text" name="'.$obj->prefixId.'['.$field->htmlID.']" value="'.$field->value.'" '.$onBlur.'  id="'.$field->htmlID.'" title="'.$field->tooltip.'" />';
					break;
				case "textarea":
					$temp='<textarea name="'.$obj->prefixId.'['.$field->htmlID.']" id="'.$field->htmlID.'" title="'.$field->tooltip.'" >'.$field->value.'</textarea>';
					break;
				case "dropdown":
					$temp='<select name="'.$obj->prefixId.'['.$field->htmlID.']" id="'.$field->htmlID.'" title="'.$field->tooltip.'">';
					foreach ($field->list as $arr) {
						$x='<option value="'.$arr["value"].'">'.$obj->getString($arr["label"]).'</option>';
						$temp.=$obj->cObj->stdWrap($x,$arr);
					}
					$temp.='</select>';
					break;
				case "radio":
					$temp="";
					foreach ($field->list as $arr) {
						$x='<input type="radio" name="'.$obj->prefixId.'['.$field->htmlID.']" id="'.$field->htmlID.'" value="'.$arr["value"].'"  />'.$obj->getString($arr["label"]);
						$temp.=$obj->cObj->stdWrap($x,$arr);
					}
					break;
				case "checkbox":
					$checked="";
					if ($_POST[$field->htmlID]||($obj->getValueFromDB($field))) $checked="checked";
					$temp='<input type="checkbox" name="'.$obj->prefixId.'['.$field->htmlID.']" id="'.$field->htmlID.'" value="1" "'.(($field->value)?'checked':'').'" title="'.$field->tooltip.'" '.$checked.' />';
					break;
				case "password":
					if ($obj->prefixId!="tx_feregistrationprocess_pi3") $field->value="";
					$temp='<input type="password" name="'.$obj->prefixId.'['.$field->htmlID.']" '.$onBlur.' title="'.$field->tooltip.'" value="" id="'.$field->htmlID.'" />';
					break;
				case "hidden":
					if ($_POST[$field->htmlID]) $field->value=$_POST[$field->htmlID];
					$temp='<input type="hidden"  name="'.$obj->prefixId.'['.$field->htmlID.']" value="'.$field->value.'" />';
					break;
			}
			$temp=$obj->cObj->stdWrap($temp,$field->TS);
			$markerArr["###".$field->markerName."###"]=$temp;
			$markerArr["###".$field->markerName."_LABEL###"]=$field->label;
			$markerArr["###".$field->markerName."_REQUIRED###"]=($field->required)?$obj->requiredMarker:"";
			$markerArr["###".$field->markerName."_ERROR###"]="<div id='".$field->errField."'></div>";
			$htmlFields[$field->markerName]=$temp;
			
			$value=$obj->getValueFromSession($field);
			$markerArr["###".$field->markerName."_VALUE###"]=$value;
		
		}
		$markerArr["###GENERAL_REQUIRED###"]=$obj->requiredMarker;
		$markerArr["###FORM_BEGIN###"]="<form name='".$obj->prefixId."reg_form' action='".$obj->baseURL.$obj->cObj->getTypoLink_URL($GLOBALS['TSFE']->id)."' method='POST' onSubmit='return ".$obj->prefixId."_check_FormSubmit();'>";
		$markerArr["###FORM_END###"]='<input type="hidden" name="'.$obj->prefixId.'[ccm_regstep]" value="'.$obj->currStep.'"></form>';
		return $markerArr;
	}
}
?>
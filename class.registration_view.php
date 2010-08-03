<?php

class tx_feusermanagement_view {
	var $emailReg='^[\\w-_\.+]*[\\w-_\.]\@([\\w-_]+\\.)+[\\w]+[\\w]$';
	var $passwordReg='^.*(?=.{6,})(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$';
	var $userNameReg='^[^A-Z]$';
	private function getFieldValidationJS($field,$obj) {
		if (!$field->jsvalidation) return;

		$js='';
		$validations=explode(',',$field->validation);
		foreach($validations as $validation) {
			switch ($validation) {
				case 'email':
					$reg=str_replace(chr(92),chr(92).chr(92),$this->emailReg);
					$js.='
						var '.$field->htmlID.'_val=document.getElementById("'.$field->htmlID.'").value;
						var regex = new RegExp("'.$reg.'");
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
						var regex = new RegExp("'.$reg.'");
						if (!regex.test('.$field->htmlID.'_val)) {
							doSubmit=false;
							alertMessage="'.$obj->pi_getLL('password_error','',FALSE).'";
						}
					';
					break;
				case 'username':
					$reg=str_replace(chr(92),chr(92).chr(92),$this->userNameReg);
					$js.='
						var '.$field->htmlID.'_val=document.getElementById("'.$field->htmlID.'").value;
						var regex = new RegExp("'.$reg.'");
						if (!regex.test('.$field->htmlID.'_val)) {
							doSubmit=false;
							alertMessage="'.$obj->prepareMessage(array($obj->pi_getLL('username_error','',FALSE),$field->label)).'";
						}
					';
					break;
				case 'regExp':
					$reg=str_replace(chr(92),chr(92).chr(92),$field->regExp);
					$js.='
						var '.$field->htmlID.'_val=document.getElementById("'.$field->htmlID.'").value;
						var regex = new RegExp("'.$reg.'");
						if (!regex.test('.$field->htmlID.'_val)) {
							doSubmit=false;
							alertMessage="'.$obj->prepareMessage(array($obj->pi_getLL('pattern_error','',FALSE),$field->label)).'";
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
			} elseif($field->type=='radio') {
				$js.='
					doSubmit=false;
					alertMessage="'.$field->notCheckedMessage.'"
					elements=document.getElementsByName("'.$obj->prefixId.'['.$field->htmlID.']");
					for (i=0;i<elements.length;i++) {
						if(elements[i].checked) doSubmit=true;
						alertMessage="";
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

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$fieldJSArr: ...
	 * @param	[type]		$fields: ...
	 * @param	[type]		$step: ...
	 * @param	[type]		$obj: ...
	 * @return	[type]		...
	 */
	function wrapFormJS($fields,$step,$obj) {
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
		$fieldJSArr=array();
		foreach($fields as $field) {
			$js='';
			$js=$this->getFieldValidationJS($field,$obj);
			if ($js) {
				$fieldJSArr[]=$js;
			}
		}


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
					'fieldJSArr' => $fieldJSArr,
					'fields' => $fields,
					'step' =>$step
				);
				t3lib_div::callUserFunction($userFunc, $params, $obj);
			}
		}
		return $formJS;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$field: ...
	 * @param	[type]		$obj: ...
	 * @return	[type]		...
	 */
	function getOnBlurJS($field,$obj) {
		$trueAction='';
		$falseAction='';
		$js='';
		// THIS HOOK IS FOR CHANGING THE TRUEACTION AND FALSEACTION
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
		if ($field->onBlurValidation&&$field->validation) {
			if ($field->equal) {
				/*
				$equalField=$obj->modelLib->getField($field->equal,$obj);
				if (!($falseAction)) $falseAction="alert(".$obj->prepareMessage(array($obj->pi_getLL('equal_error','',FALSE),$field->label,$equalField->label)).");";
				$js.='
				function test'.$field->htmlID.'(value) {
					if (document.getElementById("'.$field->htmlID.'").value!=document.getElementById("'.$equalField->htmlID.'").value) {
						'.$falseAction.'
					}
				}
				';
				*/
			}
			$validations=explode(',',$field->validation);
			#foreach ($validations as $validation) {
			if ($validation=$validations[0]) {
				switch ($validation) {
					case 'email':
						if (!($falseAction)) $falseAction="alert(".$obj->pi_getLL('email_error_value_js','',FALSE).");";
						$reg=str_replace(chr(92),chr(92).chr(92),$this->emailReg);
						$js.='
						function test'.$field->htmlID.'(value) {
						     var regex = new RegExp("'.$reg.'");
						     if (regex.test(value)) {
								'.$trueAction.'
							 } else if (value.length>0){
								'.$falseAction.'
							 }
						  }
						';
						break;
					case 'password':
						$reg=str_replace(chr(92),chr(92).chr(92),$this->passwordReg);
						if (!($falseAction)) $falseAction="alert('".$obj->pi_getLL('password_error','',FALSE)."');";
						$js.='
						function test'.$field->htmlID.'(value) {
						     var regex = new RegExp("'.$reg.'");
						     if (regex.test(value)) {
								'.$trueAction.'
							 } else if (value.length>0){
								'.$falseAction.'
							 }
						  }
						';

						break;
					case 'regExp':
						$reg=str_replace(chr(92),chr(92).chr(92),$field->regExp);
						if (!($falseAction)) $falseAction="alert('".$obj->prepareMessage(array($obj->pi_getLL('pattern_error','',FALSE),$field->label))."');";
						$js.='
						function test'.$field->htmlID.'(value) {
						     var regex = new RegExp("'.$reg.'");
						     if (regex.test(value)) {
								'.$trueAction.'
							 } else {
								'.$falseAction.'
							 }
						  }
						';
						break;
					case 'user':
						$js=$field->onBlurCode;
						break;
					case 'username':
						$reg=str_replace(chr(92),chr(92).chr(92),$this->userNameReg);
						if (!($falseAction)) $falseAction="alert('".$obj->prepareMessage(array($obj->pi_getLL('username_error','',FALSE),$field->label))."');";
						$js.='
						function test'.$field->htmlID.'(value) {
						     var regex = new RegExp("'.$reg.'");
						     if (regex.test(value)) {
								'.$trueAction.'
							 } else {
								'.$falseAction.'
							 }
						  }
						';
						break;
					case 'hook':
						if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['fieldOnBlur'])) {
							foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['fieldOnBlur'] as $userFunc) {
								$params = array(
									'field'=>$field,
								);
								$js.=t3lib_div::callUserFunction($userFunc, $params, $obj);
							}
						}

					break;
				}
			}
		}
		return $js;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$allFields: ...
	 * @param	[type]		$markerArr: ...
	 * @param	[type]		$obj: ...
	 * @return	[type]		...
	 */
	function fillMarkers(&$allFields,$markerArr,$obj) {
		
		foreach($allFields as $field) {
			
			$temp='';
			$onBlur='';
			if (!$field->value) {
				$field->value=$obj->piVars[$field->htmlID];
				if (!$field->value) $field->value=$obj->getValueFromSession($field);
			}
			#$stdWrapConf=getTSValue('value.stdWrap',$field->TS);
			$stdWrapConf=getTSValue('value',$field->TS);
			$field->value=$obj->cObj->stdWrap($field->value,$stdWrapConf);
			
			$field->label=$obj->cObj->stdWrap($field->label,$field->TS['label.']);
			if ($field->type=='hidden') {
				#t3lib_div::debug($field);
				#t3lib_div::debug($stdWrapConf);
			}
			if ($field->onBlurValidation) $onBlur=" onblur='test".$field->htmlID."(this.value)' ";
			switch ($field->type) {
				case "text":

					$temp='<input type="text" name="'.$obj->prefixId.'['.$field->htmlID.']" value="'.$field->value.'" '.$onBlur.'  id="'.$field->htmlID.'" title="'.$field->tooltip.'" />';
					break;
				case "textarea":
					$temp='<textarea name="'.$obj->prefixId.'['.$field->htmlID.']" id="'.$field->htmlID.'" title="'.$field->tooltip.'" >'.$field->value.'</textarea>';
					break;
				case "dropdown":
					$temp='<select name="'.$obj->prefixId.'['.$field->htmlID.']" id="'.$field->htmlID.'" title="'.$field->tooltip.'">';
					if ($field->includeEmptyOption) {
						$emptyLabel=$obj->pi_getLL('emptyOptionLabel');
						if ($field->emptyLabel) $emptyLabel=$field->emptyLabel;
						$temp.='<option value="0">'.$emptyLabel.'</option>';
					}
					foreach ($field->list as $arr) {
						$selected=($field->value==$arr['value'])?'selected="selected"':'';
						$temp.='<option value="'.$arr["value"].'" '.$selected.'>'.$obj->getString($arr["label"]).'</option>';
					}
					$temp.='</select>';
					break;
				case "multiple":
					$temp='<select name="'.$obj->prefixId.'['.$field->htmlID.'][]" multiple="multiple" id="'.$field->htmlID.'" title="'.$field->tooltip.'">';
					if ($field->includeEmptyOption) {
						$emptyLabel=$obj->pi_getLL('emptyOptionLabel');
						if ($field->emptyLabel) $emptyLabel=$field->emptyLabel;
						$temp.='<option value="0">'.$emptyLabel.'</option>';
					}
					foreach ($field->list as $arr) {
						if (!is_array($field->value)) $field->value=array();
						$selected=(in_array($arr['value'],$field->value))?'selected="selected"':'';
						$temp.='<option value="'.$arr["value"].'" '.$selected.'>'.$obj->getString($arr["label"]).'</option>';
					}
					$temp.='</select>';
				break;
				case "radio":
					$temp='';
					$count=0;
					foreach ($field->list as $arr) {
						$checked='';
						if ($obj->piVars[$field->htmlID]==$arr['value']||($obj->getValueFromSession($field)==$arr['value'])) $checked='checked="checked"';
						$temp.='<input type="radio" name="'.$obj->prefixId.'['.$field->htmlID.']" id="'.$field->htmlID.'_'.$count.'" value="'.$arr["value"].'" '.$checked.'  />'.$obj->getString($arr["label"]);
						$count++;
					}
					break;
				case 'upload':
					$temp='<input type="file" name="'.$obj->prefixId.'['.$field->htmlID.']" />';
					break;
				case "checkbox":
					$checked="";
					if ($obj->piVars[$field->htmlID]||($obj->getValueFromSession($field))) $checked='checked="checked"';
					$temp='<input type="checkbox" name="'.$obj->prefixId.'['.$field->htmlID.']" id="'.$field->htmlID.'" value="1"  title="'.$field->tooltip.'" '.$checked.' />';
					break;
				case "password":
					if ($obj->prefixId!="tx_feregistrationprocess_pi3") $field->value="";
					$temp='<input type="password" name="'.$obj->prefixId.'['.$field->htmlID.']" '.$onBlur.' title="'.$field->tooltip.'" value="" id="'.$field->htmlID.'" />';
					break;
				case "hidden":
					if ($obj->piVars[$field->htmlID]) $field->value=$obj->piVars[$field->htmlID];
					$temp='<input type="hidden"  name="'.$obj->prefixId.'['.$field->htmlID.']" value="'.$field->value.'" />';
					break;
			}
			$temp=$obj->cObj->stdWrap($temp,$field->TS);
			$markerArr["###".$field->markerName."###"]=$temp;
			$markerArr["###".$field->markerName."_LABEL###"]=$field->label;
			$markerArr["###".$field->markerName."_REQUIRED###"]=($field->required)?$obj->requiredMarker:"";
			
			$markerArr["###".$field->markerName."_ERROR###"]='';
			$globalErrMsgCount=array_key_exists('globalErrMsgCount',$obj->conf['config.'])?getTSValue('config.globalErrMsgCount',$obj->conf):5;
			
			if (count($field->errMessages) && $globalErrMsgCount>$obj->errCount) {
				$obj->errCount++;
				$errString='';
				if (array_key_exists('errMsgCount',$field->TS)) {
					$errCount=$field->TS['errMsgCount'];
				} else {
					if (array_key_exists('errMsgCount',$obj->conf['config.'])) {
						$errCount=getTSValue('config.errMsgCount',$obj->conf);
					} else {
						$errCount=1;
					}
				}
				
				$errCount=(int)$errCount;
				for ($i=0;$i<count($field->errMessages);$i++) {
					if ($i<$errCount) {
						$msg=$field->errMessages[$i];
						$wrapConf=(is_array($field->TS['errMsgItemWrap.']))?$field->TS['errMsgItemWrap.']:getTSValue('config.errMsgItemWrap.',$obj->conf);
						$errString.=$obj->cObj->stdWrap($msg,$wrapConf);
					}
				}
				
				$wrapConf=(is_array($field->TS['errMsgWrap.']))?$field->TS['errMsgWrap.']:getTSValue('config.errMsgWrap.',$obj->conf);
				
				$errString=$obj->cObj->stdWrap($errString,$wrapConf);
				$markerArr['###'.$field->markerName.'_ERROR###']='<div id="'.$field->errField.'">'.$errString.'</div>';
			}
			$htmlFields[$field->markerName]=$temp;

			$value=$obj->getValueFromSession($field);
			$markerArr["###".$field->markerName."_VALUE###"]=$value;

		}
		$markerArr["###GENERAL_REQUIRED###"]=$obj->requiredMarker;
		
		$markerArr["###FORM_BEGIN###"]="<form name='".$obj->prefixId."reg_form' action='".$obj->baseURL.$obj->cObj->getTypoLink_URL($GLOBALS['TSFE']->id)."' method='POST' enctype='multipart/form-data' onSubmit='return ".$obj->prefixId."_check_FormSubmit();'>";
		$markerArr["###FORM_END###"]='<input type="hidden" name="'.$obj->prefixId.'[ccm_regstep]" value="'.$obj->currStep.'"></form>';
		return $markerArr;
	}
	/**
	 * [Describe function...]
	 *
	 * @return	[type]		...
	 */
	function getFE_User_Marker($uid=0) {
		$arr=array();
		if (!$uid) $uid=$GLOBALS['TSFE']->fe_user->user['uid'];
		$uid=(int)$uid;
		$sql='SELECT * FROM fe_users WHERE uid='.$uid;
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			foreach ($row as $key=>$value) {
				$arr["###FEUSER_".strtoupper($key)."###"]=$value;
			}
		}

		return $arr;
	}
}
?>
<?php
	
				
	class registration_model {
		function getCurrentFields($TSstep,$obj) {
			$i=0;
			$TSfields=$TSstep["fields."];
			$fields=array();
			if (!is_array($TSfields)) return $fields;
			foreach($TSfields as $key=>$TSAttributes) {
				$i++;
				$name=$obj->removeDot($key);
				$field=new Field();
				$field->name=$name;
				$field->label=$name;
				$field->markerName=strtoupper($name);
				$field->tooltip=$name;
				$field->errField="ccm_reg_err_".$name;
				$field->htmlID="ccm_reg_elem".$name;
				if (array_key_exists("type",$TSAttributes)) $field->type=$TSAttributes["type"];
				if ($field->type=="dropdown"||$field->type=="radio") {
					if (array_key_exists("options.",$TSAttributes)&&is_array($TSAttributes["options."])) $TSOptions=$TSAttributes["options."];
					foreach($TSOptions as $key=>$TSoption) {
						
						if (is_array($TSoption)) {
							if (array_key_exists("label",$TSoption)&&array_key_exists("value",$TSoption)) {
								$field->list[]=$TSoption;
							}	
						}
					}
				}
				$field->notCheckedMessage=($field->type=="checkbox")?("'".$obj->prepareMessage(array($obj->pi_getLL('email_error','',FALSE),$field->name))):$obj->prepareMessage(array($obj->pi_getLL('not_enter','',FALSE),$field->name));
				#$field->emailErrorMessage=
				//$field->toDB=getTSValue();
				
				if (array_key_exists("required",$TSAttributes)) $field->required=$TSAttributes["required"];
				if (array_key_exists("requires",$TSAttributes)) $field->requires=$TSAttributes["requires"];
				
				if (array_key_exists("additionalData",$TSAttributes)) $field->additionalData=$TSAttributes["additionalData"];
				if (array_key_exists("validation",$TSAttributes)) $field->validation=$TSAttributes["validation"];
				if (array_key_exists("jsvalidation",$TSAttributes)) $field->jsvalidation=$TSAttributes["validation"];
				if (array_key_exists("markerName",$TSAttributes)) $field->markerName=$TSAttributes["markerName"];	
				if (array_key_exists("notCheckedMessage",$TSAttributes)) $field->notCheckedMessage=$TSAttributes["notCheckedMessage"];	
				if (array_key_exists("value",$TSAttributes)) $field->value=$TSAttributes["value"];	
				if (array_key_exists("label",$TSAttributes)) $field->label=$obj->getString($TSAttributes["label"]);
				if (array_key_exists("tooltip",$TSAttributes)) $field->tooltip=$obj->getString($TSAttributes["tooltip"]);
				if (array_key_exists("unique",$TSAttributes)) $field->unique=$TSAttributes["unique"];	
				if (array_key_exists("equal",$TSAttributes)) $field->equal=$TSAttributes["equal"];	
				$field->TS=$TSAttributes;
				$field->tempID=$i;
				$field->fe_user=(string)getTSValue('feuser_map.'.$field->name,$obj->conf);
				$fields[$name]=$field;
				
				
			}
			
			return $fields;
		}
		function getAllFields($obj) {
			$allFields=array();
			$count=$obj->getLastStepNr();
			for ($i=0;$i<=$count;$i++) {
				$allFields=array_merge($allFields,$this->getCurrentFields($obj->conf["steps."][$i."."],$obj));
			}
			return $allFields;
		}
		function getField($name,$obj) {
			$fields=$this->getAllFields($obj);
			if (array_key_exists($name,$fields)) return $fields[$name];
			return "";
		}
	}
?>
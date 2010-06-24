<?php

	
	class registration_model {
		var $fields=array();
		/**
		 * [Describe function...]
		 *
		 * @param	[type]		$obj: ...
		 * @param	[type]		$load_data: ...
		 * @return	[type]		...
		 */	
		function getDataMap(&$obj) {
			$maparr=getTSValue('feuser_map.',$obj->conf);
			$map=array();
			foreach($maparr as $key=>$value) {
				$map[(string)$value]=$key;
			}
			return $map;
		}
		function getCurrentFields($TSstep,$obj,$load_data=0) {
			
			#t3lib_div::debug($obj);
			$i=0;
			$TSfields=$TSstep["fields."];
			$fields=array();
			if (!is_array($TSfields)) return $fields;
			$dataMap=$this->getDataMap($obj);
			
			foreach($TSfields as $key=>$TSAttributes) {
				
				$i++;
				$name=$obj->removeDot($key);
				if (array_key_exists($name,$this->fields)) {
				
					$fields[$name]=&$this->fields[$name];
					continue;
				}
				
				$htmlPrefix=($obj->conf['config.']['html_prefix'])?$obj->conf['config.']['html_prefix']:'ccm_reg_';
				$field=new Field();
				$field->name=$name;
				$field->label=$name;
				$field->dbName=$dataMap[$name];
				$field->markerName=strtoupper($name);
				$field->tooltip=$name;
				$field->list=array();
				$field->errField=$htmlPrefix.'err_'.$name;
				$field->htmlID=$htmlPrefix.'field_'.$name;
				if (array_key_exists('type',$TSAttributes)) $field->type=$TSAttributes['type'];
				if ($field->type=='dropdown'||$field->type=='radio') {
					if (array_key_exists('options.',$TSAttributes)&&is_array($TSAttributes['options.'])) {
						$TSOptions=$TSAttributes['options.'];
						foreach($TSOptions as $key=>$TSoption) {

							if (is_array($TSoption)) {
								if (array_key_exists('label',$TSoption)&&array_key_exists('value',$TSoption)) {
									$field->list[]=$TSoption;
								}
							}
						}

					}
					if (!count($field->list)) {
						if ($TSRelation=$TSAttributes['relation.']) {
							
							if (($table=$TSRelation['table']) && ($valueField=$TSRelation['value_field']) && ($labelField=$TSRelation['label_field'])) {
								if ($where=$TSRelation['where']) $where=' AND '.$where;
								$efFields=$obj->cObj->enableFields($table);
								$order=$TSRelation['order_field'];
								if ($order) $order=' ORDER BY '.$order;
								$sql='SELECT '.$valueField.','.$labelField.' FROM '.$table.' WHERE 1=1 '.$where.' '.$efFields.$order;
								$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
								while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
									$field->list[]=array('label'=>$row[$labelField],'value'=>$row[$valueField]);
								}
							}
						}
					}
				}
				
				if (array_key_exists('emptyLabel',$TSAttributes)) $field->emptyLabel=$TSAttributes['emptyLabel'];
				if (array_key_exists('fileSize',$TSAttributes)) $field->filesize=$TSAttributes['fileSize'];
				if (array_key_exists('fileTypes',$TSAttributes)) $field->filesize=$TSAttributes['fileTypes'];
				if (array_key_exists("required",$TSAttributes)) $field->required=$TSAttributes["required"];
				if (array_key_exists("requires",$TSAttributes)) $field->requires=$TSAttributes["requires"];
				if (array_key_exists("includeEmptyOption",$TSAttributes)) $field->includeEmptyOption=$TSAttributes["includeEmptyOption"];
				if (array_key_exists("additionalData",$TSAttributes)) $field->additionalData=$TSAttributes["additionalData"];
				if (array_key_exists("validation",$TSAttributes)) $field->validation=$TSAttributes["validation"];
				if (array_key_exists("jsvalidation",$TSAttributes)) $field->jsvalidation=$TSAttributes["jsvalidation"];
				if (array_key_exists("onBlurValidation",$TSAttributes)) $field->onBlurValidation=$TSAttributes["onBlurValidation"];
				if (array_key_exists("markerName",$TSAttributes)) $field->markerName=$TSAttributes["markerName"];
				if (array_key_exists("notCheckedMessage",$TSAttributes)) $field->notCheckedMessage=$TSAttributes["notCheckedMessage"];
				if (array_key_exists("value",$TSAttributes)) $field->value=$TSAttributes["value"];
				if (array_key_exists("label",$TSAttributes)) $field->label=$obj->getString($TSAttributes["label"]);
				if (array_key_exists("tooltip",$TSAttributes)) $field->tooltip=$obj->getString($TSAttributes["tooltip"]);
				if (array_key_exists("unique",$TSAttributes)) $field->unique=$TSAttributes["unique"];
				if (array_key_exists("equal",$TSAttributes)) $field->equal=$TSAttributes["equal"];
				if (array_key_exists("regExp",$TSAttributes)) $field->regExp=$TSAttributes["regExp"];
				if ($load_data) {
					if (!$field->value) $field->value=$obj->getValueFromSession($field);
					if (!$field->value && $obj->prefixId=='tx_feusermanagement_pi2') {
						//load Data From fe_user

						$maparr=getTSValue('feuser_map',$obj->conf);
						foreach($maparr as $fe_name=>$field_name) {
							if ($field_name==$field->name) {
								$sql='SELECT '.$fe_name.' FROM fe_users WHERE uid="'.$obj->feuser_uid.'"';
								$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
								if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
									$field->value=$row[$fe_name];
								}
							}
						}

						if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['loadData'])) {
							foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['loadData'] as $userFunc) {

								$params = array(
									'obj'=>&$obj,
									'field'=>&$field,
								);
								t3lib_div::callUserFunction($userFunc, $params, $this);
							}
						}
					}
				}
				$field->notCheckedMessage=($field->type=="checkbox")?("'".$obj->prepareMessage(array($obj->pi_getLL('email_error','',FALSE),$field->label))):$obj->prepareMessage(array($obj->pi_getLL('not_enter','',FALSE),$field->label));
				$field->TS=$TSAttributes;
				$field->tempID=$i;
				$this->fields[$name]=$field;
				
				$fields[$name]=&$this->fields[$name];
				

			}
			
			
			return $fields;
		}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$obj: ...
	 * @param	[type]		$load_data: ...
	 * @return	[type]		...
	 */
		function getAllFields($obj,$load_data=0) {
			$allFields=array();
			$count=$obj->getLastStepNr();
			for ($i=0;$i<=$count;$i++) {
				#t3lib_div::debug($this->getCurrentFields($obj->conf["steps."][$i."."],$obj,$load_data));
				$allFields=array_merge($allFields,$this->getCurrentFields($obj->conf["steps."][$i."."],$obj,$load_data));
			}
			return $allFields;
		}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$name: ...
	 * @param	[type]		$obj: ...
	 * @return	[type]		...
	 */
		function getField($name,$obj) {
			$fields=$this->getAllFields($obj);
			if (array_key_exists($name,$fields)) return $fields[$name];
			return "";
		}
		function getValueFromSession($key,$obj) {
			$sesArr=$GLOBALS["TSFE"]->fe_user->getKey('ses',$obj->prefixId);
			if (is_array($sesArr)) return $sesArr[$key];
			return false;
		}
		function saveValueToSession($key,$value,$obj) {
			$sesArr=$GLOBALS["TSFE"]->fe_user->getKey('ses',$obj->prefixId);
			if (!is_array($sesArr)) $sesArr=array();
			$sesArr[$key]=$value;
			$GLOBALS["TSFE"]->fe_user->setKey('ses',$obj->prefixId,$sesArr);
		}
		function clearValuesInSession($obj) {
			$GLOBALS['TSFE']->fe_user->setKey('ses',$obj->prefixId,false);
		}
		function secureDataBeforeInsertUpdate($value,$obj=null) {
			if (is_object($obj)) {
				if ($obj->conf['config.']['utf8_encodeBeforeInsert']) $value=utf8_encode($value);
				if ($obj->conf['config.']['utf8_decodeBeforeInsert']) $value=utf8_decode($value);
				if ($obj->conf['config.']['removeXSS']) $value=t3lib_div::removeXSS($value);
			}
			$retValue=mysql_real_escape_string($value);
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['feusermanagement']['secure_data'])) {
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['feusermanagement']['secure_data'] as $userFunc) {
					$params = array(
						'value' => $value,
					);
					$retValue=t3lib_div::callUserFunction($userFunc, $params, $this);
				}
			}
			return $retValue;
		}
		function getFreeFilename($path,$filename,$prefix) {
			
			$file_ext=substr($filename,$dotpos=strrpos($filename,'.'));
			$subfilename=substr($filename,0,$dotpos);
			$counter='';
			while(file_exists($path.$prefix.$subfilename.$counter.$file_ext)) {
				$counter++;
			}
			$newFileName=$prefix.$subfilename.$counter.$file_ext;
			return $newFileName;
		}
	}
?>
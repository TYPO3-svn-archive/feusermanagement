<?php
	
	class registration_validation {
		function validateField(&$field,&$obj) {
			$valid=true;
			if ($field->required) {
				$valid=$valid&&$this->validateRequire($field,$obj);
			}
			if ($field->type=='upload') {
				$valid=$valid&&$this->validateFileUpload($field,$obj);
			}
			if ($field->unique) {
				$valid=$valid&&$this->validateUnique($field,$obj);
			}
			if ($field->equal) {
				$valid=$valid&&$this->validateEquality($field,$obj);
			}
			if ($field->validation) {
				switch ($field->validation) {
					case "email":
						$pattern = '/'.$obj->viewLib->emailReg.'/';
						if (!preg_match($pattern,$obj->piVars[$field->htmlID])) {

							$valid=false;
							$field->errMessages[]=$obj->pi_getLL('email_error','',FALSE);
						}
						break;
					case "password":
						$pattern='/'.$obj->viewLib->passwordReg.'/';

						if (!preg_match($pattern,$obj->piVars[$field->htmlID])) {
							$valid=false;
							$field->errMessages[]=$obj->pi_getLL('password_error','',FALSE);
						}
						break;
					case "regExp":

						$pattern = '/'.$field->regExp.'/';
						if (!preg_match($pattern,$obj->piVars[$field->htmlID])) {
							$valid=false;
							$field->errMessages[]=$obj->prepareMessage(array($obj->pi_getLL('pattern_error','',FALSE),$field->label));
						}
						break;
				}
			}
			
			return $valid;
		}
		private function validateUnique(&$field,&$obj) {
			$valid=true;
			$value=mysql_real_escape_string($obj->piVars[$field->htmlID]);

			$maparr=getTSValue('feuser_map',$obj->conf);
			$uniqueDBFields=array();
			foreach($maparr as $fe_name=>$field_name) {
				if ($field_name==$field->name) {
					$uniqueDBFields[]=$fe_name;
				}
			}
			$unique=true;
			foreach ($uniqueDBFields as $db_name) {
				$efFields=$obj->cObj->enableFields('fe_users');
				$filterOwnUser='';
				if ($obj->prefixId=='tx_feusermanagement_pi2') {
					$filterOwnUser=' AND NOT uid="'.$GLOBALS['TSFE']->fe_user->user['uid'].'"';
				}
				$sql='SELECT * FROM fe_users WHERE '.$db_name.'="'.$value.'" '.$efFields.$filterOwnUser;
				$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
				if ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$unique=false;
				}
			}
			if (!$unique) {
				$valid=false;
				$field->errMessages[]=$obj->prepareMessage(array($obj->pi_getLL('unique_error','',FALSE),$field->label));
			}
			return $valid;
		}
		private function validateFileUpload(&$field,&$obj) {
			$valid=true;
			$size=$_FILES[$obj->prefixId]['size'][$field->htmlID];
			$filename=$_FILES[$obj->prefixId]['name'][$field->htmlID];
			$allowedExt=t3lib_div::trimExplode(',',$field->filetypes);
			if (!in_array('*',$allowedExt)) {
				$fileext=substr($filename,strrpos($filename,'.'));
				if (!in_array($fileext,$allowedExt)) {
					$valid=false;
					$field->errMessages[]=$obj->prepareMessage(array($obj->pi_getLL('wrong_filetype','',FALSE),$field->label,implode(',',$allowedExt)));
				}
			}
			if ($size>$field->filesize) {
				$valid=false;
				$field->errMessages[]=$obj->prepareMessage(array($obj->pi_getLL('wrong_filesize','',FALSE),$field->label,$field->filesize));
			}
			if (!isset($_FILES[$obj->prefixId]['tmp_name'][$field->htmlID])) $valid=false;
			if (strpos($filename,'|')!==false) $valid=false;
			return $valid;
		}
		private function validateEquality(&$field,&$obj) {
			$valid=true;
			$fields=$obj->modelLib->getAllFields($obj);
			$ref=$field->equal;
			$id2=$fields[$ref]->htmlID;
			
			if ($obj->piVars[$field->htmlID]!=$obj->piVars[$id2]) {
				$valid=false;
				$field->errMessages[]=$obj->prepareMessage(array($obj->pi_getLL('equal_error','',FALSE),$field->label,$fields[$ref]->label));
			}
			return $valid;
		}
		private function validateRequire(&$field,&$obj) {
			$valid=true;
			if (!(isset($obj->piVars[$field->htmlID]) && ($obj->piVars[$field->htmlID]) )&& !($obj->getValueFromSession($field))) {
				if ($field->type=='upload') {
					if (isset($_FILES[$obj->prefixId][$field->name][$field->htmlID])) {
						
					} else {
						$valid=false;
						$field->errMessages[]=$obj->prepareMessage(array($obj->pi_getLL('not_enter_file','',FALSE),$field->label));
						
					}
				} else {
					
					$valid=$obj->modelLib->getValueFromSession($field->htmlId,$obj);
					$field->errMessages[]=$obj->prepareMessage(array($obj->pi_getLL('not_enter','',FALSE),$field->label));
					
				}
			}
			return $valid;
		}
	}

?>
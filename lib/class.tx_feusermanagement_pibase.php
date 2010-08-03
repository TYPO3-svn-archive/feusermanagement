<?php
class tx_feusermanagement_pibase extends tslib_pibase {
	var $modelLib=null;
	var $viewLib=null;
	var $validateLib=null;
	var $templateFileName='';
	var $templatefile='';
	var $currStep=0;
	
	protected function init() {
		$this->baseURL=getTSValue('config.baseURL',$GLOBALS['TSFE']->tmpl->setup);
		$this->requiredMarker=getTSValue('config.requiredMarker',$this->conf);
		$this->modelLib=t3lib_div::makeInstance('tx_feusermanagement_model');
		$this->viewLib=t3lib_div::makeInstance('tx_feusermanagement_view');
		$this->validateLib=t3lib_div::makeInstance('tx_feusermanagement_validation');
		$this->templateFileName=getTSValue('config.template',$this->conf);
		$this->templatefile = $this->cObj->fileResource($this->templateFileName);
		if ($uploadDir=getTSValue('config.upload_dir',$this->conf)) $this->uploadDir=$uploadDir;
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
	 * @param	[type]		$key: ...
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
	
	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$step: ...
	 * @return	[type]		...
	 */
	function writeLastStepToSession($step,$dontWriteEmptyPassword=false) {
		$fields=$this->modelLib->getCurrentFields($this->conf['steps.'][$step.'.'],$this);
		foreach($fields as $field) {			
			$id=$field->htmlID;
			if (isset($this->piVars[$id]) || $field->type=="checkbox") {
				$value=$this->piVars[$id];
				if (!$value && $dontWriteEmptyPassword && $field->type=='password') continue;
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
}

?>
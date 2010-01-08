<?php
	function escapeAllInputs() {
		foreach($_POST as $key=>$value) {
			if(strpos($key,"ccm_reg_elem")===0) {
				$_POST[$key]=mysql_real_escape_string($value);
			}
		}
	}
	function encryptPW($pw_orig,$obj) {
		$retpw=$pw_orig;
		if (getTSValue('config.useMD5',$obj->conf)) {
			$retpw=md5($pw_orig);
		}
		
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['encryptPW'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['encryptPW'] as $userFunc) {
				$params = array(
					'pw'=> $pw_orig
				);
				$retpw=t3lib_div::callUserFunction($userFunc, $params, $obj);
			}
		}
		return $retpw;
	}
	function getTSValue($key,$conf) {
		$subkeys=explode(".",$key);
		$arr=$conf;
		$max=count($subkeys);
		for($i=0;$i<$max;$i++) {
			$key=$subkeys[$i];
			if ($i<($max-1)) $key.='.';
			if (array_key_exists($key,$arr)) {
				$arr=$arr[$key];
			} else {
				if (array_key_exists($key.'.',$arr)) {
					$arr=$arr[$key.'.'];
				} else {
					return false;
				}
			}
		}
		return $arr;
	}
?>
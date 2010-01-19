<?
class tx_feumajax {
    
    function cli_main() {
		
		$confVars=unserialize($GLOBALS["TYPO3_CONF_VARS"]['EXT']['extConf']['feusermanagement']);
		tslib_eidtools::connectDB();
		$key=mysql_real_escape_string($_GET["reg_db_key"]);
		$value=mysql_real_escape_string($_GET["reg_db_value"]);
		if ($key&&$value) $unique=true;
		
		$fieldarray=array();
		$sql='DESCRIBE fe_users';
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$fieldarray[]=$row['Field'];
		}
		if (!in_array($key,$fieldarray)) return 0;
		
		
		$sql='SELECT * FROM fe_users WHERE '.$key.'="'.$value.'"';
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		
		if ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			//value exists in DB
			//check if given to the current user
			$user=$GLOBALS['TSFE']->fe_user->user['username'];
			if ($user) {
				if ($GLOBALS['TSFE']->fe_user->user[$key]==$value) {
						//given to current user
				} else {
					$unique=false;
				}
			} else {
				$unique=false;
			}
		}
		if ($unique) {
			echo 1;
		} else {
			echo 0;
		}
	}
}
$cliObj = t3lib_div::makeInstance('tx_feumajax');
$cliObj->cli_main();	
		
?>
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


/**
 * Plugin 'ccm_registration_ajax' for the 'fe_registration_process' extension.
 *
 * @author	Florian Bachmann <fbachmann@cross-content.com>
 * @package	TYPO3
 * @subpackage	tx_feregistrationprocess
 */
class tx_feregistrationprocess_pi2 extends tslib_pibase {
	var $prefixId      = 'tx_feregistrationprocess_pi2';		// Same as class name
	var $scriptRelPath = 'pi2/class.tx_feregistrationprocess_pi2.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'fe_registration_process';	// The extension key.
	var $pi_checkCHash = true;
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content,$conf)	{
		//t3lib_div::debug($conf);
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj=1;
		$key=mysql_real_escape_string($_GET["reg_db_key"]);
		$value=mysql_real_escape_string($_GET["reg_db_value"]);
		/*
		//SUCHE IN DER FE_REGISTRATION DATENBANK
		$sql="SELECT * FROM tx_feregistrationprocess_user_info WHERE type='".$key."' AND content='".$value."'";
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		$unique=true;
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			if ($row["id"]!=$this->uid) $unique=false;
		}
		*/
		//SUCHE IN DER FE_USER
		$unique=true;
		if (true||$unique) {
			//$map=$this->conf["feuser_map."];
			//foreach($map as $fe_name=>$reg_key) {
			//	if ($key==$reg_key) {
					//$sql='SELECT * FROM fe_users WHERE '.$fe_name.'="'.$value.'"';
					
					$sql='SELECT * FROM fe_users WHERE '.$key.'="'.$value.'"';
					$this->ccmlog($sql);
					$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
					if ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						$this->ccmlog('a');
						$user=$GLOBALS['TSFE']->fe_user->user['username'];
						if ($user) {
							$this->ccmlog('b');
							if ($GLOBALS['TSFE']->fe_user->user[$key]==$value) {
								$this->ccmlog('c');
							} else {
								$unique=false;
								$this->ccmlog('d');
							}
						} else {
							$unique=false;
							$this->ccmlog('e');
						}
					}
//				}
			//}
		}
		
		if ($unique) return "unique";
		return "0";
	}
	function ccmlog($content,$cat='') {
		if ($cat) $cat='_'.$cat;
		$sql='INSERT INTO tx_ccmlog_log (description,text,timestamp) VALUES("profil_unique_test'.$cat.'","'.mysql_real_escape_string($content).'","'.time().'")';
		//$GLOBALS['TYPO3_DB']->sql_query($sql);
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/fe_registration_process/pi2/class.tx_feregistrationprocess_pi2.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/fe_registration_process/pi2/class.tx_feregistrationprocess_pi2.php']);
}

?>
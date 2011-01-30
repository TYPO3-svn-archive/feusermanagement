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
require_once(t3lib_extMgm::extPath('feusermanagement') . 'lib_general.php');

/**
 * Plugin 'ccm_registration_edit' for the 'fe_registration_process' extension.
 *
 * @author	Florian Bachmann <fbachmann@cross-content.com>
 * @package	TYPO3
 * @subpackage	tx_feregistrationprocess
 */
class tx_feusermanagement_pi3 extends tx_feusermanagement_pibase {
	var $prefixId      = 'tx_feusermanagement_pi3';		// Same as class name
	var $scriptRelPath = 'pi3/class.tx_feusermanagement_pi3.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'feusermanagement';	// The extension key.
	var $pi_checkCHash = true;



	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$content: ...
	 * @param	[type]		$conf: ...
	 * @return	[type]		...
	 */
	 // NOT YET IMPLEMENTED
	function main($content,$conf)	{

		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_USER_INT_obj=1;
		
		if ($GLOBALS['TSFE']->fe_user->user['uid']) {
			
			$template=$this->cObj->fileResource($this->conf['templateFile']);
			
			
			if ($this->piVars['action']=='delete') {
				$subpart='FINISH_PAGE';
				
				$allow=true;
				$alternativeSubpart='';
				$markerArr=array();
				// HOOK
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['allowDeleteAcc'])) {
					foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['allowDeleteAcc'] as $userFunc) {
						$params = array(
							'subpart'=>&$subpart,
							'markerArr'=>&$markerArr
						);
						$allow=t3lib_div::callUserFunction($userFunc, $params, $this);
					}
				}
				$template=$this->cObj->getSubpart($template,$subpart);
				if ($allow) {
					$sql='DELETE FROM fe_users WHERE uid='.$GLOBALS['TSFE']->fe_user->user['uid'];
					$GLOBALS['TYPO3_DB']->sql_query($sql);
				}
				
			} else {
				$template=$this->cObj->getSubpart($template,"DELETE_FORM");
			}
			
			$content=$template;
			
		}
		return $this->pi_wrapInBaseClass($content);
	}

	
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/feusermanagement/pi2/class.tx_feusermanagement_pi2.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/feusermanagement/pi2/class.tx_feusermanagement_pi2.php']);
}

?>

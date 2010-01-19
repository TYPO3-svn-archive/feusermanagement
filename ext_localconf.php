<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

  ## Extending TypoScript from static template uid=43 to set up userdefined tag:
t3lib_extMgm::addTypoScript($_EXTKEY,'editorcfg','
	tt_content.CSS_editor.ch.tx_feusermanagement_pi1 = < plugin.tx_feusermanagement_pi1.CSS_editor
',43);


t3lib_extMgm::addPItoST43($_EXTKEY,'pi1/class.tx_feusermanagement_pi1.php','_pi1','list_type',0);


  ## Extending TypoScript from static template uid=43 to set up userdefined tag:
t3lib_extMgm::addTypoScript($_EXTKEY,'editorcfg','
	tt_content.CSS_editor.ch.tx_feusermanagement_pi3 = < plugin.tx_feusermanagement_pi3.CSS_editor
',43);


t3lib_extMgm::addPItoST43($_EXTKEY,'pi3/class.tx_feusermanagement_pi3.php','_pi3','list_type',0);

$TYPO3_CONF_VARS['FE']['eID_include']['FEUSERMANAGEMENT']='EXT:feusermanagement/class.tx_feumajax.php';
?>
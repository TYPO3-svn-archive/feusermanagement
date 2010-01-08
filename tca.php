<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA["tx_feregistrationprocess_user_info"] = array (
	"ctrl" => $TCA["tx_feregistrationprocess_user_info"]["ctrl"],
	"interface" => array (
		"showRecordFieldList" => "hidden,feuser_uid,type,content,id,istemp"
	),
	"feInterface" => $TCA["tx_feregistrationprocess_user_info"]["feInterface"],
	"columns" => array (
		'hidden' => array (		
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		"feuser_uid" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:fe_registration_process/locallang_db.xml:tx_feregistrationprocess_user_info.feuser_uid",		
			"config" => Array (
				"type"     => "input",
				"size"     => "4",
				"max"      => "4",
				"eval"     => "int",
				"checkbox" => "0",
				"range"    => Array (
					"upper" => "1000",
					"lower" => "10"
				),
				"default" => 0
			)
		),
		"type" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:fe_registration_process/locallang_db.xml:tx_feregistrationprocess_user_info.type",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"max" => "50",	
				"eval" => "trim",
			)
		),
		"content" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:fe_registration_process/locallang_db.xml:tx_feregistrationprocess_user_info.content",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"max" => "200",	
				"eval" => "trim",
			)
		),
		"id" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:fe_registration_process/locallang_db.xml:tx_feregistrationprocess_user_info.id",		
			"config" => Array (
				"type"     => "input",
				"size"     => "4",
				"max"      => "4",
				"eval"     => "int",
				"checkbox" => "0",
				"range"    => Array (
					"upper" => "1000",
					"lower" => "10"
				),
				"default" => 0
			)
		),
		"istemp" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:fe_registration_process/locallang_db.xml:tx_feregistrationprocess_user_info.istemp",		
			"config" => Array (
				"type"     => "input",
				"size"     => "4",
				"max"      => "4",
				"eval"     => "int",
				"checkbox" => "0",
				"range"    => Array (
					"upper" => "1000",
					"lower" => "10"
				),
				"default" => 0
			)
		),
	),
	"types" => array (
		"0" => array("showitem" => "hidden;;1;;1-1-1, feuser_uid, type, content, id, istemp")
	),
	"palettes" => array (
		"1" => array("showitem" => "")
	)
);



$TCA["tx_feregistrationprocess_temp"] = array (
	"ctrl" => $TCA["tx_feregistrationprocess_temp"]["ctrl"],
	"interface" => array (
		"showRecordFieldList" => "hidden,temp_user_id,timestamp,banned_till,ip"
	),
	"feInterface" => $TCA["tx_feregistrationprocess_temp"]["feInterface"],
	"columns" => array (
		'hidden' => array (		
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		"temp_user_id" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:fe_registration_process/locallang_db.xml:tx_feregistrationprocess_temp.temp_user_id",		
			"config" => Array (
				"type"     => "input",
				"size"     => "4",
				"max"      => "4",
				"eval"     => "int",
				"checkbox" => "0",
				"range"    => Array (
					"upper" => "1000",
					"lower" => "10"
				),
				"default" => 0
			)
		),
		"timestamp" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:fe_registration_process/locallang_db.xml:tx_feregistrationprocess_temp.timestamp",		
			"config" => Array (
				"type"     => "input",
				"size"     => "12",
				"max"      => "20",
				"eval"     => "datetime",
				"checkbox" => "0",
				"default"  => "0"
			)
		),
		"banned_till" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:fe_registration_process/locallang_db.xml:tx_feregistrationprocess_temp.banned_till",		
			"config" => Array (
				"type"     => "input",
				"size"     => "12",
				"max"      => "20",
				"eval"     => "datetime",
				"checkbox" => "0",
				"default"  => "0"
			)
		),
		"ip" => Array (		
			"exclude" => 1,		
			"label" => "LLL:EXT:fe_registration_process/locallang_db.xml:tx_feregistrationprocess_temp.ip",		
			"config" => Array (
				"type" => "input",	
				"size" => "30",	
				"max" => "15",	
				"eval" => "trim",
			)
		),
	),
	"types" => array (
		"0" => array("showitem" => "hidden;;1;;1-1-1, temp_user_id, timestamp, banned_till, ip")
	),
	"palettes" => array (
		"1" => array("showitem" => "")
	)
);
?>
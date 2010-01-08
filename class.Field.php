<?

class Field {
		public $name;
		public $htmlID;
		public $dbName;
		public $markerName;
		public $label="";
		public $type="text";
		public $additionalData="";
		public $required=0;
		public $value;
		public $toDB=1;
		public $onBlurValidation;
		public $onBlurCode="";
		public $tempID=0;
		public $unique=0;
		public $equal="";
		public $tooltip="";
		public $list=array();
		public $notCheckedMessage="";
		public $TS=array();
		public $errField="";
		public $fe_user="";
		public $requires="";
		public $emailErrorMessage="";
	}
?>
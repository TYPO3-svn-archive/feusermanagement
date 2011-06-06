<?php
class tx_feusermanagement_mailer {
	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$row_feuser: ...
	 * @return	[type]		...
	 */
	public static function generateAdminMailUserConfirmation($row_feuser,&$obj) {

		$markerArr=array();
		foreach ($row_feuser as $key=>$value) {
			$markerArr['###FE_'.strtoupper($key).'###']=$value;
		}
		$adminToken=$row_feuser['registration_token'];#md5($TYPO3_CONF_VARS['SYS']['encryptionKey'].$row['registration_token']);
		$confirmLink=$obj->baseURL.$obj->pi_getPageLink($GLOBALS['TSFE']->id,$target='',$urlParameters=array($obj->prefixId.'[adminAction]'=>'confirm',$obj->prefixId.'[token]'=>md5($adminToken),$obj->prefixId.'[fe_user]'=>$row_feuser['uid']));
		$declineLink=$obj->baseURL.$obj->pi_getPageLink($GLOBALS['TSFE']->id,$target='',$urlParameters=array($obj->prefixId.'[adminAction]'=>'decline',$obj->prefixId.'[token]'=>md5($adminToken),$obj->prefixId.'[fe_user]'=>$row_feuser['uid']));
		$confirmText=$obj->pi_getLL('confirm_label','CONFIRM');
		$declineText=$obj->pi_getLL('decline_label','DECLINE');

		$markerArr["###ADMIN_ACCEPT###"]="<a href=".$confirmLink.">".$confirmText."</a>";
		$markerArr["###ADMIN_DECLINE###"]="<a href=".$declineLink.">".$declineText."</a>";
		$disabled=0;
		if ($obj->requireAdminConfirm) $disabled=1;
		$mailTemplate=$obj->cObj->getSubpart($obj->templatefile,'ADMIN_MAIL_USER_CONFIRMATION');
		$mailCont=str_replace(array_keys($markerArr),$markerArr,$mailTemplate);
		$adminAddr=getTSValue('config.adminMail',$obj->conf);
		$adminAddr=getTSValue('config.adminMail',$obj->conf);
		$adminSubject=getTSValue('config.adminMailSubject',$obj->conf);
		$fromMail=getTSValue('config.mailFromEMail',$obj->conf);
		$fromName=getTSValue('config.mailFromName',$obj->conf);
		$mailObj = t3lib_div::makeInstance('t3lib_htmlmail');
		$mailObj->start();
		$mailObj->recipient = $adminAddr;
		$mailObj->subject = $adminSubject;
		$mailObj->from_email = $fromMail;
		$mailObj->from_name = $fromName;
		$mailObj->addPlain($mailCont);
		$mailObj->setHTML($mailObj->encodeMsg($mailCont));
		$success=$mailObj->send($adminAddr);
	}
	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$row_feuser: ...
	 * @return	[type]		...
	 */
	public static function generateUserMailConfirmation($id,&$obj) {
		$html=$obj->userMailTemplate;
		$sql='SELECT * FROM fe_users WHERE uid='.$id;
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		$user = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$confirmLink=$obj->baseURL.$obj->cObj->getTypoLink_URL($GLOBALS['TSFE']->id,array($obj->prefixId.'[userConfirmationToken]'=>$user['registration_token'],$obj->prefixId.'[fe_user]'=>$id));
		$confirmText=$obj->pi_getLL('confirm_label','CONFIRM');
		$markerArr=array();
		$markerArr['###CONFIRMATION_LINK###']='<a href="'.$confirmLink.'">'.$confirmText.'</a>';
		foreach($user as $key=>$value) {
			$markerArr['###FE_'.strtoupper($key).'###']=$value;
		}
		
		### HOOK editConfirmationMail ###
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['editConfirmationMail'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$obj->extKey]['editConfirmationMail'] as $userFunc) {
				$params = array(
					'markerArr' => &$markerArr,
					'user' =>&$user,
				);
				$dontSendConfirmationMail=t3lib_div::callUserFunction($userFunc, $params, $obj);
			}
		}
		
		if (!$dontSendConfirmationMail) {
			$html=str_replace(array_keys($markerArr),$markerArr,$html);
			$mailObj = t3lib_div::makeInstance('t3lib_htmlmail');
			$mailObj->start();
			$mailObj->recipient = $user["email"];
			$mailObj->subject = getTSValue('config.userMailSubject',$obj->conf);
			$mailObj->from_email = getTSValue('config.mailFromEMail',$obj->conf);
			$mailObj->from_name = getTSValue('config.mailFromName',$obj->conf);
			$mailObj->addPlain($html);
			$mailObj->setHTML($mailObj->encodeMsg($html));
			$success=$mailObj->send($user["email"]);
		}
		return $success;
	}
	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$row_feuser: ...
	 * @return	[type]		...
	 */
	function generateUserMailNotify($id,$obj) {
		$html=$obj->userMailNotifyTemplate;
		
		$sql='SELECT * FROM fe_users WHERE uid='.$id;
		$res=$GLOBALS['TYPO3_DB']->sql_query($sql);
		$user = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$confirmLink=$obj->baseURL.$obj->cObj->getTypoLink_URL($GLOBALS['TSFE']->id,array($obj->prefixId.'[userConfirmationToken]'=>$user['registration_token'],$obj->prefixId.'[fe_user]'=>$id));
		$markerArr=array();
		$markerArr=array_merge($markerArr,$obj->viewLib->getFE_User_Marker($id));
		$markerArr['###FEUSER_PASSWORD###']=$obj->modelLib->getValueFromSession('password',$obj);
		
		$html=str_replace(array_keys($markerArr),$markerArr,$html);

		$mailObj = t3lib_div::makeInstance('t3lib_htmlmail');
		$mailObj->start();
		$mailObj->recipient = $user['email'];
		$mailObj->subject = getTSValue('config.userMailSubject',$obj->conf);
		$mailObj->from_email = getTSValue('config.mailFromEMail',$obj->conf);
		$mailObj->from_name = getTSValue('config.mailFromName',$obj->conf);
		$mailObj->addPlain($html);
		$mailObj->setHTML($mailObj->encodeMsg($html));
		$success=$mailObj->send($user['email']);
		
		return $success;
	}
}

?>
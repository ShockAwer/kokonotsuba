<?php
/*
mod_recaptcha_v2.php
*/

class mod_recaptcha_v2 extends ModuleHelper {
	private $KEY_PUBLIC   = 'SITE KEY';
	private $KEY_PRIVATE  = 'SECRET KEY';
	private $reCaptcha;

	public function getModuleName(){
		return 'mod_recaptcha_v2 : reCAPTCHA v2';
	}

	public function getModuleVersionInfo(){
		return '7th.Alpha.0 (v150127)';
	}

	public function autoHookHead(&$head, $isReply){
		$head.="<script src='https://www.google.com/recaptcha/api.js?hl=en-US'></script>";
	}

	/* 在頁面附加 reCAPTCHA 功能 */
	public function autoHookPostForm(&$txt){
		$txt .= '<tr><th class="postblock">Verification</th><td>'.'<div class="g-recaptcha" data-sitekey="'.$this->KEY_PUBLIC.'"></div>'.'</td></tr>';
	}
	
	function validateCaptcha($privatekey, $response) {
	    $responseData = json_decode(file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$privatekey.'&response='.$response));
	    return $responseData->success;
    }

	/* 在接收到送出要求後馬上檢查是否正確 */
	public function autoHookRegistBegin(&$name, &$email, &$sub, &$com, $upfileInfo, $accessInfo){
		if (valid() >= LEV_MODERATOR ) return; //no captcha for admin mode
		$resp = $this->validateCaptcha('SECRET KEY', $_POST['g-recaptcha-response']);
		if($resp == null){ error('reCAPTCHA failed！You are not acting like a human!'); } // 檢查
	}
}

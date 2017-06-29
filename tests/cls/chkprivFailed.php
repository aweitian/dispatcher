<?php
namespace app\modules\def\chkpriv;
class chkprivFailed {
	public static function checkPrivilege() {
		return false;
	}
	public function welcomeAction() {
		echo 'welcome.';
	}
	public function testAction() {
		echo 'hi.';
	}
}
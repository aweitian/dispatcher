<?php
namespace app\modules\def\chkpriv;
class chkprivFailed {
	public static function checkPrivilege() {
		return false;
	}
	public function welcomeAction() {
		return 'welcome.';
	}
	public function testAction() {
		return'hi.';
	}
}
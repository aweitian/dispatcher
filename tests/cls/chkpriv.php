<?php
namespace app\modules\def\chkpriv;
class chkpriv {
	public static function checkPrivilege() {
		return true;
	}
	public function welcomeAction() {
		return 'welcome.';
	}
	public function testAction() {
		return 'hi.';
	}
}
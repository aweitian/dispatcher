<?php
namespace app\modules\def\chkpriv;
class chkpriv {
	public static function checkPrivilege() {
		return true;
	}
	public function welcomeAction() {
		echo 'welcome.';
	}
	public function testAction() {
		echo 'hi.';
	}
}
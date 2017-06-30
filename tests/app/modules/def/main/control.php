<?php
namespace app\modules\def\main;
class control {
	public static function checkPrivilege() {
		return true;
	}
	public function welcomeAction() {
		return 'main-welcome.';
	}
	public function testAction() {
		return'main-hi.';
	}
}
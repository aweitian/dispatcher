<?php
namespace app\modules\m2\m1\main;
class control{
	public static function checkPrivilege() {
		return true;
	}
	public function welcomeAction() {
		return ':main-welcome.';
	}
	public function testAction() {
		return':main-hi.';
	}
}
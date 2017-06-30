<?php
namespace app\modules\m2\m1\balabala;
class control{
	public static function checkPrivilege() {
		return true;
	}
	public function welcomeAction() {
		return 'balabala:main-welcome.';
	}
	public function testAction() {
		return'balabala:main-hi.';
	}
}
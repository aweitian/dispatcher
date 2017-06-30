<?php
namespace app\modules\def\chkpriv;
class testArg {
	public static function checkPrivilege() {
		return true;
	}
	public function welcomeAction($a,$b) {
		return $a.'-'.$b;
	}
	public function testAction(array $c) {
		return count($c);
	}
}

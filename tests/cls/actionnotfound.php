<?php
namespace app\modules\def\chkpriv;
class control{
	public static function checkPrivilege() {
		return true;
	}
	public function hook(){
		return '!';
	}
}
<?php
class DispatcherTest extends PHPUnit_Framework_TestCase {
	public function testPass() {
		$log = new \Tian\Dispatcher\McaDispatcher();
		$log->setLogger(new \Tian\Logger\MemoryLogger());
		$log->setConfig([
			'module_loc' => __DIR__.'/cls',
			'loc_pattern' => '{moduleloc}/chkpriv.php',
			'control_pattern' => 'chkpriv',
			'namespace_pattern' => 'app\modules\def\chkpriv'
		]);
		
		$this->assertTrue($log->dispatch('chkpriv'));
		$this->assertEquals("welcome.", $log->invoke());
		$this->assertTrue($log->dispatch('chkpriv', 'test'));
		$this->assertEquals("hi.", $log->invoke());
	}
	public function testBlock() {
		$log = new \Tian\Dispatcher\McaDispatcher();
		$log->setLogger(new \Tian\Logger\MemoryLogger());
		$log->setConfig([
			'module_loc' => __DIR__.'/cls',
			'control_pattern' => 'chkprivFailed',
			'loc_pattern' => '{moduleloc}/chkprivFailed.php',
			'namespace_pattern' => 'app\modules\def\chkpriv'
		]);
		$this->assertFalse($log->dispatch('chkprivFailed'));
		$this->assertFalse($log->dispatch('chkprivFailed', 'test'));
	}
	public function testArg() {
		$log = new \Tian\Dispatcher\McaDispatcher();
		$log->setLogger(new \Tian\Logger\MemoryLogger());
		$log->setConfig([
				'module_loc' => __DIR__.'/cls/nonexist',
				'control_pattern' => 'testArg',
				'loc_pattern' => '{moduleloc}/testArg.php',
				'namespace_pattern' => 'app\modules\def\chkpriv'
		]);
		$log->setModuleArr([
				"def" => __DIR__.'/cls'
		]);
		$this->assertTrue($log->dispatch('testArg'));
		$this->assertEquals("a-bb", $log->invoke('a','bb'));
		$this->assertTrue($log->dispatch('testArg', 'test'));
		$this->assertEquals(3, $log->invoke([1,2,4]));
	}
	public function testActionNotFound() {
		$log = new \Tian\Dispatcher\McaDispatcher();
		$log->setLogger(new \Tian\Logger\MemoryLogger());
		$log->setModuleLoc(__DIR__.'/cls')
		->setLocPattern('{moduleloc}/actionnotfound.php')
		->setNamespacePattern('app\modules\def\chkpriv')
		->setActionNotFound('hook');
		
		$this->assertTrue($log->dispatch());
		$this->assertEquals("!", $log->invoke());
	}
	public function testDefMain() {
		$log = new \Tian\Dispatcher\McaDispatcher();
		$log->setLogger(new \Tian\Logger\MemoryLogger());
		$log->setConfig([
			'module_loc' => __DIR__.''
		]);
		$this->assertTrue($log->dispatch());
		$this->assertEquals("main-welcome.", $log->invoke());
		$this->assertTrue($log->dispatch('','test'));
		$this->assertEquals("main-hi.", $log->invoke());
	}
	public function testModule() {
		$map = [
				'm2/m1' => __DIR__
		];
		$log = new \Tian\Dispatcher\McaDispatcher();
		$log->setLogger(new \Tian\Logger\MemoryLogger());
		$log->setModuleArr($map);
		$this->assertTrue($log->dispatch('','',['m2','m1']));
		$this->assertEquals(":main-welcome.", $log->invoke());
		$this->assertTrue($log->dispatch('','test',['m2','m1']));
		$this->assertEquals(":main-hi.", $log->invoke());
		
// 		$log->dispatch('balabala','',['m2','m1']);
// 		echo $log->logger->getLog();exit;
		
		$this->assertTrue($log->dispatch('balabala','',['m2','m1']));
		$this->assertEquals("balabala:main-welcome.", $log->invoke());
		$this->assertTrue($log->dispatch('balabala','test',['m2','m1']));
		$this->assertEquals("balabala:main-hi.", $log->invoke());
	}
}

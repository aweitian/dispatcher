<?php
class DispatcherTest extends PHPUnit_Framework_TestCase {
	public function testPass() {
		$log = new \Tian\Dispatcher\McaDispatcher();
		$log->setLogger(new \Tian\Logger\MemoryLogger());
		$log->setConfig([
			'module_loc' => __DIR__.'/cls',
			'loc_pattern' => '{moduleloc}/chkpriv.php',
			'namespace_pattern' => 'app\modules\def\chkpriv'
		]);
		
		if($log->dispatch([], 'chkpriv', ''))
		{
			//$log->invoke();
		}
		else 
		{
			//echo $log->logger->getLog();
		}
		
		if($log->dispatch([], 'chkpriv', 'test'))
		{
			//$log->invoke();
			//echo $log->logger->getLog();
		}
		else
		{
			//echo $log->logger->getLog();
		}
	}
	public function testBlock() {
		$log = new \Tian\Dispatcher\McaDispatcher();
		$log->setLogger(new \Tian\Logger\MemoryLogger());
		$log->setConfig([
			'module_loc' => __DIR__.'/cls',
			'loc_pattern' => '{moduleloc}/chkprivFailed.php',
			'namespace_pattern' => 'app\modules\def\chkpriv'
		]);
		
		if($log->dispatch([], 'chkprivFailed', ''))
		{
			$log->invoke();
		}
		//echo $log->logger->getLog();
		
		if($log->dispatch([], 'chkprivFailed', 'test'))
		{
			$log->invoke();
		}
		echo $log->logger->getLog();

	}
}

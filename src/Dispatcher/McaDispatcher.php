<?php

/**
 * @Author: awei.tian
 * @Date: 2016年12月7日
 * @Desc: 
 * 		本类的实质就是利用反射，按一定的规则NEW一个对象，在NEW之前执行静态权限检查函数
 * 		如果通过，则NEW对象，并调用相应的方法
 * 
 * 
 * 		关于MODULE LOCATION查找方法
 * 			1) 网址中的MODULE数组JOIN("/"),如果结果为空,第三步
 *			2) 然后查表,如果表为空,第三步，如果找到相应的KEY，返回对应的VALUE，没有找到第三步
 * 			3) 检查MODULE_LOC变量，如果为空，异常（找不到模块目录）
 * 			4) 返回MODULE_LOC
 * 
 * 如果模块为数组，模块名为aa/bb形式,namespace_pattern使用时，换成aa\bb
 * 如果模块为空时，则使用默认值	default_module_name
 * 
 * 默认情况下，ACTION的参数为空，如果需要参数，调用setActionArgs,
 * 所以，每个模块的ACTION的参数由每个模板自己定义
 * 
 */
namespace Tian\Dispatcher;

class McaDispatcher {
	use \Tian\LoggerTrait;
	/**
	 * 权限检查函数名
	 *
	 * @var string
	 */
	protected $privChk = "checkPrivilege";
	/**
	 * IActionNotFound接口名
	 *
	 * @var string
	 */
	protected $no_act = "Action_not_found";
	
	/**
	 * 可用变量{control}
	 *
	 * @var string
	 */
	protected $control_pattern = "control";
	/**
	 * 可用变量{action}
	 *
	 * @var string
	 */
	protected $action_pattern = "{action}Action";
	protected $default_control = "main";
	protected $default_action = "welcome";
	protected $default_module_name = "def";
	protected $numeric_action_support = true;
	protected $numeric_action_prefix = "_"; // 如果numericSupport为真，并且action_prefix为空才使用它
	protected $numeric_control_support = true;
	protected $numeric_control_prefix = "_"; // 如果numericSupport为真，并且control_prefix为空才使用它
	/**
	 * 可用变量{moduleloc},{control},{module_name}
	 *
	 * @var string
	 */
	protected $loc_pattern = "{moduleloc}/app/modules/{module_name}/{control}/control.php";
	/**
	 * 可用变量{control},{module_name}
	 *
	 * @var string
	 */
	protected $namespace_pattern = "\app\modules\{module_name}\{control}";
	/**
	 *
	 * @var string : module loc
	 */
	protected $module_loc = null;
	protected $module_arr = array ();
	public $module = null;
	public $control = null;
	public $action = null;
	
	/**
	 *
	 * @var \ReflectionClass
	 */
	private $rc;
	private $rc_action;
	private $rc_arg = array ();
	/**
	 *
	 * @param array $conf
	 *        	[
	 *        	"privChk" => "checkPrivilege",
	 *        	"no_act" => "Action_not_found",
	 *        	"control_pattern" => "control",
	 *        	"action_pattern" => "{action}Action",
	 *        	"numeric_control_support" => true,
	 *        	"numeric_control_prefix" => "_",
	 *        	"numeric_action_support" => true,
	 *        	"numeric_action_prefix" => "_",
	 *        	"default_control" => "main",
	 *        	"default_action" => "welcome",
	 *        	"default_module_name" => "def",
	 *        	"loc_pattern" => "{moduleloc}/app/modules/{module_name}/{control}/control.php",
	 *        	"namespace_pattern" => "\app\modules\{module_name}\{control}",
	 *        	"module_loc" => "/webroot/www/balbal", 进行 rtrim('/') 只去右边
	 *        	"module_arr" => [
	 *        	"aaa/bbb" => "/webrot/www/balalba" rtrim('/') 只去右边
	 *        	],
	 *        	
	 *        	]
	 */
	public function __construct(array $conf = []) {
		$this->setConfig ( $conf );
	}
	/**
	 * 默认值：checkPrivilege
	 * 这个静态方法必须存在，否则会派遣失败
	 * @param string $static_method_name
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setPrivChk($static_method_name) {
		return $this->setConfig ( [
				'privChk' => $static_method_name
		] );
	}
	/**
	 * 默认值:Action_not_found
	 * 如果方法没有找到就执行的方法名
	 * @param string $method_name
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setActionNotFound($method_name) {
		return $this->setConfig ( [
				'no_act' => $method_name
		] );
	}
	/**
	 * 默认值:control,可用变量{control}
	 * 设置control方法名的规则,这里的名称前面加上NAMESPACE就是完整的类名
	 * @param string $pattern
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setControlPattern($pattern) {
		return $this->setConfig ( [
				'control_pattern' => $pattern
		] );
	}
	/**
	 * 默认值:{action}Action,可用变量{action}
	 * 设置ACTION方法名的规则
	 * @param string $pattern
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setActionPattern($pattern) {
		return $this->setConfig ( [
				'action_pattern' => $pattern
		] );
	}
	
	/**
	 * 默认值:true
	 * 如果Control设置为全数字，是否使用加前缀的方法
	 * @param bool $v
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setNumericControlSupport($v) {
		return $this->setConfig ( [
				'numeric_control_support' => $v
		] );
	}
	/**
	 * 默认值: _
	 * 如果Control设置为全数字，方法名加的前缀
	 * @param string $v
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setNumericControlPrefix($v) {
		return $this->setConfig ( [
				'numeric_control_prefix' => $v
		] );
	}
	/**
	 * 默认值:true
	 * 如果ACTION设置为全数字，是否使用加前缀的方法
	 * @param bool $v
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setNumericActionSupport($v) {
		return $this->setConfig ( [
				'numeric_action_support' => $v
		] );
	}
	/**
	 * 默认值: _
	 * 如果ACTION设置为全数字，方法名加的前缀
	 * @param string $v
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setNumericActionPrefix($v) {
		return $this->setConfig ( [
				'numeric_action_prefix' => $v
		] );
	}
	/**
	 * 默认值 :main
	 * 设置如果CONTROL为空，用什么代替
	 * @param string $name
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setDefaultControl($name) {
		return $this->setConfig ( [
				'default_control' => $name
		] );
	}
	/**
	 * 默认值 :welcome
	 * 设置如果ACTION为空，用什么代替
	 * @param string $name
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setDefaultAction($name) {
		return $this->setConfig ( [
				'default_action' => $name
		] );
	}
	/**
	 * 默认值 :def
	 * 设置如果模块为空数组，模块名用什么
	 * @param string $name
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setDefaultModuleName($name) {
		return $this->setConfig ( [
				'default_module_name' => $name
		] );
	}
	/**
	 * 默认值为NULL
	 * 设置模块的目录路径
	 * @param string $dir
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setModuleLoc($dir) {
		return $this->setConfig ( [
				'module_loc' => $dir
		] );
	}
	/**
	 * 默认值：\app\modules\{module_name}\{control}
	 * 可用变量可用变量{control},{module_name}
	 * @param string $pattern
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setNamespacePattern($pattern) {
		return $this->setConfig ( [
				'namespace_pattern' => $pattern
		] );
	}
	/**
	 * 默认值:{moduleloc}/app/modules/{module_name}/{control}/control.php
	 * 可用变量{moduleloc},{control},{module_name}
	 * 设置类文件路径规则,
	 * 1. moduleloc文件路径/webroot/www/v
	 * 2. control EG:main
	 * 3. module_name 模块名
	 *
	 * @param string $pattern
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setLocPattern($pattern) {
		// $this->loc_pattern = $pattern;
		return $this->setConfig ( [
				'loc_pattern' => $pattern
		] );
	}
	/**
	 * 默认值:[]
	 * 设置MODULE LOC变量的映射表，这个表的优先级比MOD LOC变量优先级高
	 * MAP的结构为模块名 => 路径,模块名为解析过后的模块名
	 * @param array $map
	 * @return \Tian\Dispatcher\McaDispatcher
	 */
	public function setModuleArr(array $map) {
		return $this->setConfig ( [
				'module_arr' => $map
		] );
	}
	
	/**
	 *
	 * @see \Tian\Dispatcher\McaDispatcher::__construct
	 * @param array $conf 
	 * @return \Tian\Dispatcher\McaDispatcher       	
	 */
	public function setConfig(array $conf) {
		$allowCnf = array (
				"privChk",
				"no_act",
				"control_pattern",
				"action_pattern",
				"numeric_control_support",
				"numeric_control_prefix",
				"numeric_action_support",
				"numeric_action_prefix",
				"default_control",
				"default_action",
				"loc_pattern",
				"namespace_pattern",
				"module_loc",
				"module_arr",
				"namespace_pattern" 
		);
		foreach ( $conf as $key => $val ) {
			if (property_exists ( $this, $key ) && in_array ( $key, $allowCnf )) {
				$this->{$key} = $val;
			}
		}
		return $this;
	}
	public function getModuleName() {
		if (is_array ( $this->module )) {
			$ret = implode ( '/', $this->module );
			if ($ret == '')
				return $this->default_module_name;
			return $ret;
		}
		return null;
	}
	public function setActionArgs(array $arg = array()) {
		$this->rc_arg = $arg;
	}
	/**
	 *
	 * @param string $c        	
	 * @param string $a        	
	 * @param array $m        	
	 * @return boolean
	 */
	public function dispatch($c = '', $a = '', array $m = []) {
		return $this->probe ( $m, $c, $a );
	}
	/**
	 * 调用ACTION
	 */
	public function invoke() {
		$this->setActionArgs ( func_get_args () );
		$controller = $this->rc->newInstance ();
		$method = $this->rc->getMethod ( $this->rc_action );
		return $method->invokeArgs ( $controller, $this->rc_arg );
	}
	/**
	 *
	 * @param array $m        	
	 * @param string $c        	
	 * @param string $a        	
	 * @return boolean
	 */
	private function probe(array $m, $c, $a) {
		$this->module = $m;
		! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://prepare to dispatch raw data: module:" . (var_export ( $m, true )) . " control:$c action:$a" );
		
		$ml = $this->findModLoc ();
		if ($ml === false) {
			! is_null ( $this->logger ) && $this->logger->error ( 'find module location failed.' );
			return false;
		}
		// findmodloc保证了它现在的正确
		$mn = $this->getModuleName ();
		$control = $this->filterControl ( $c );
		! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://prepare to dispatch actual control:$control" );
		
		if ($control === false) {
			! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://control numeric is not support yet" );
			return false;
		}
		$control_suffix = strtr ( $this->control_pattern, array (
				"{control}" => $control 
		) );
		
		$namespace = strtr ( $this->namespace_pattern, array (
				'{module_name}' => str_replace ( '/', '\\', $mn ),
				'{control}' => $control 
		) );
		$control_suffix_ns = $namespace . "\\" . $control_suffix;
		
		$controlLoc = strtr ( $this->loc_pattern, array (
				'{module_name}' => $mn,
				'{moduleloc}' => $ml,
				'{control}' => $control 
		) );
		// var_dump($controlLoc);exit;
		! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://prepare to check class exists named $control_suffix_ns" );
		if (! class_exists ( $control_suffix_ns, false )) {
			
			! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://class not found,finding file in $controlLoc" );
			if (file_exists ( $controlLoc )) {
				
				! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://control file found, require the control file at $controlLoc" );
				require_once ($controlLoc);
			}
			
			! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://recheck class exists" );
			if (! class_exists ( $control_suffix_ns, false )) {
				
				! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://trigger control not exist" );
				return false;
			} else {
				
				! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://trigger control exist" );
				return $this->searchAction ( $control_suffix_ns, $a );
			}
		} else {
			
			! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://trigger control exist" );
			return $this->searchAction ( $control_suffix_ns, $a );
		}
		return false;
	}
	private function searchAction($controller, $action) {
		! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://init control class" );
		$rc = new \ReflectionClass ( $controller );
		
		// 权限检查
		
		if (! $rc->hasMethod ( $this->privChk ) or ! $rc->getMethod ( $this->privChk )->isStatic ()) {
			
			! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://controller not function " . $this->privChk );
			return false;
		}
		$privilege = call_user_func_array ( $controller . "::" . $this->privChk, [ ] );
		
		! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://execute the check privilege function" );
		if ($privilege !== true) {
			! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://blocked by the privilege check" );
			return false;
		} else {
			! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://pass the privilege check" );
			$action = $this->filterAction ( $action );
			! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://prepare to dispatch actual action:$action" );
			
			$action_suffix = strtr ( $this->action_pattern, array (
					"{action}" => $action 
			) );
			! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://assign the action:$action_suffix" );
			// action存在
			! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://check action exists" );
			if ($rc->hasMethod ( $action_suffix )) {
				! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://action found,and invoked,dispatch end." );
				$this->rc = $rc;
				$this->rc_action = $action_suffix;
				return true;
				// ACTION不存在，但实现了iActionNotFound接口
			} elseif ($rc->hasMethod ( $this->no_act )) {
				! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://action not found,but the control has {$this-> no_act} method." );
				$action = $this->no_act;
				$this->rc_action = $action;
				! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://action is assigned to $action" );
				$this->rc = $rc;
				return true;
			} else {
				! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://action not found,and the control no implemtns the IActionNotFound interface,dispatch end" );
				return false;
			}
		}
	}
	private function filterControl($control) {
		if ($control == "") {
			return $this->default_control;
		} else if (preg_match ( "/^[0-9]/", $control )) {
			if ($this->numeric_control_support) {
				if ($this->control_prefix == "" && $this->numeric_control_prefix == "") {
					return false;
				} else {
					if ($this->control_prefix != "") {
						return $control;
					} else {
						return $this->numeric_control_prefix . $control;
					}
				}
			}
			return false;
		} else {
			return $control;
		}
	}
	private function filterAction($action) {
		if ($action == "") {
			return $this->default_action;
		} else if (preg_match ( "/^[0-9]/", $action )) {
			if ($this->numeric_action_support) {
				if ($this->action_prefix == "" && $this->numeric_action_prefix == "") {
					return false;
				} else {
					if ($this->action_prefix != "") {
						return $action;
					} else {
						return $this->numeric_action_prefix . $action;
					}
				}
			}
			return false;
		} else {
			return $action;
		}
	}
	private function findModLoc() {
		$ma = $this->module_arr;
		$ma_str = $this->getModuleName ();
		if (is_null ( $ma_str )) {
			! is_null ( $this->logger ) && $this->logger->error ( 'invalid module' );
			return false;
		}
		if ($ma_str != "" && array_key_exists ( $ma_str, $this->module_arr )) {
			! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://module loc:" . $this->module_arr [$ma_str] );
			return $this->module_arr [$ma_str];
		}
		if ($this->module_loc == "") {
			! is_null ( $this->logger ) && $this->logger->error ( "MODULE LOCATION NOT FOUND" );
			return false;
		}
		! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://module loc:" . $this->module_loc );
		return $this->module_loc;
	}
}

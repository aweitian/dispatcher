<?php

/**
 * @Author: awei.tian
 * @Date: 2016年12月7日
 * @Desc: 
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
	protected $control_pattern = "{control}";
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
	 * 可用变量{moduleloc},{control}
	 *
	 * @var string
	 */
	protected $loc_pattern = "{moduleloc}/{control}/control.php";
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
	 *        	"control_pattern" => "{control}",
	 *        	"action_pattern" => "{action}Action",
	 *        	"numeric_control_support" => true,
	 *        	"numeric_control_prefix" => "_",
	 *        	"numeric_action_support" => true,
	 *        	"numeric_action_prefix" => "_",
	 *        	"default_control" => "main",
	 *        	"default_action" => "welcome",
	 *        	"default_module_name" => "def",
	 *        	"loc_pattern" => "{moduleloc}/{control}/{control_suffix}.php",
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
	 *
	 * @see \Tian\Dispatcher\McaDispatcher::__construct
	 * @param array $conf        	
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
				"namespace_pattern" 
		);
		foreach ( $conf as $key => $val ) {
			if (property_exists ( $this, $key ) && in_array ( $key, $allowCnf )) {
				$this->{$key} = $val;
			}
		}
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
	 * @param array $m        	
	 * @param string $c        	
	 * @param string $a        	
	 * @return boolean
	 */
	public function dispatch(array $m, $c, $a) {
		return $this->probe ( $m, $c, $a );
	}
	/**
	 * 调用ACTION
	 */
	public function invoke() {
		$controller = $this->rc->newInstance ();
		$method = $this->rc->getMethod ( $this->rc_action );
		$method->invokeArgs ( $controller, $this->rc_arg );
		return;
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
		! is_null ( $this->logger ) && $this->logger->debug ( "dispatching://prepare to dispatch control:$c,action:$a" );
		
		$ml = $this->findModLoc ();
		if ($ml === false) {
			! is_null ( $this->logger ) && $this->logger->error ( 'find module location failed.' );
			return false;
		}
		// findmodloc保证了它现在的正确
		$mn = $this->getModuleName ();
		$control = $this->filterControl ( $c );
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
				'{moduleloc}' => $ml,
				'{control}' => $control 
		) );
// 		var_dump($controlLoc);exit;
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

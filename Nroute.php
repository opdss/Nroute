<?php
/**
 * DocParser.php for Nroute.
 * @author SamWu
 * @date 2018/1/30 12:27
 * @copyright istimer.com
 */
namespace Opdss\Nroute;

use Slim\App;

class Nroute
{
	/**
	 * 注释上的路由规则
	 * 比如：@pattern /api/users
	 * @var string
	 */
	private $pattern = 'pattern';
	/**
	 * 注释上的路由路由方法
	 * 比如：@method GET|POST
	 * @var string
	 */
	private $method = 'method';
	/**
	 * 注释上的路由路由中间件
	 * @var string
	 */
	private $middleware = 'middleware';
	/**
	 * 注释上的路由参数分隔符号，如上面你的路由方法
	 * @var string
	 */
	private $delimiter = '|';
	/**
	 * 注释上的路由名称
	 * @var string
	 */
	private $name = 'name';
	/**
	 * 注释上的路由名称连接符
	 * @var string
	 */
	private $dash = '.';
	/**
	 * 缓存目录
	 * @var string
	 */
	private $cacheDir = '';
	/**
	 * 强制使用缓存
	 * 注意更新了注释路由的时候，得把缓存手动删除
	 * @var bool
	 */
	private $forceUseCache = false;
	/**
	 * 缓存前缀
	 * @var string
	 */
	private $cachePre = 'Nroute_';

	private $_routes;

	public function __construct(array $config = array())
	{
		foreach ($config as $k => $v) {
			if (property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		if (!empty($this->cacheDir)) {
			$this->cacheDir = rtrim($this->cacheDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
			if (!is_writable($this->cacheDir)) {
				throw new \Exception('缓存目录不可写');
			}
		}
	}

	public static function factory(array $config = array())
	{
		return new static($config);
	}

	/**
	 * 注册路由
	 * @param App $app
	 * @param $maps
	 * @return bool
	 * @throws \Exception
	 */
	public function register(App $app, $maps)
	{
		$_routes = [];
		if ($this->forceUseCache) {
			if (!$this->cacheDir) {
				throw new \Exception('未设置路由缓存目录');
			}
			$cacheName = $this->cacheDir . $this->cachePre.md5(serialize($maps));
			if (file_exists($cacheName)) {
				$_routes = unserialize(file_get_contents($cacheName));
			} else {
				foreach ($maps as $k => $v) {
					$_routes = array_merge($_routes, $this->readDocRoutes($k, $v));
				}
				file_put_contents($cacheName, serialize($_routes));
			}
		} else {
			foreach ($maps as $k => $v) {
				$_routes = array_merge($_routes, $this->readDocRoutes($k, $v));
			}
		}
		$this->_routes = $_routes;
		return $this->injection($app, $_routes);
	}

	/**
	 * 将路由映射到slim app
	 * @param App $app
	 * @param array $routes
	 * @return bool
	 */
	public function injection(App $app, array $routes)
	{
		foreach ($routes as $route) {
			$r = $app->map($route->methods, $route->pattern, $route->callable)->setName($route->name);
			if (!empty($route->middleware)) {
				foreach ($route->middleware as $middleware) {
					class_exists($middleware) AND $r->add($middleware);
				}
			}
		}
		$app->getContainer()->offsetSet('routes', $routes);
		return true;
	}

	/**
	 * 获取路由
	 * @return mixed
	 */
	public function routes()
	{
		return $this->_routes;
	}

	/**
	 * 读取注释路由
	 * @param $ctrlDir
	 * @param $namespace
	 * @return array|mixed
	 */
	public function readDocRoutes($ctrlDir, $namespace)
	{
		$ctrlDir = rtrim($ctrlDir, DIRECTORY_SEPARATOR);
		$namespace = rtrim($namespace, '\\');

		if ($this->cacheDir) {
			$cache = $this->cacheDir.$this->cachePre.md5($ctrlDir. $namespace);
			if ($cache && !self::isModify($ctrlDir, $this->cacheDir)) {
				return unserialize(file_get_contents($cache));
			}
		}

		$data = array();
		$files = self::getFileNames($ctrlDir, 2);

		$call = function ($string) {
			return explode($this->delimiter, trim($string));
		};
		DocParser::setGlobalHandler($this->method, $call);
		DocParser::setGlobalHandler($this->middleware, $call);
		foreach ($files as $file) {
			//$className = $namespace . str_replace(DIRECTORY_SEPARATOR, '\\', str_replace($ctrlDir, '', substr($file, 0, strpos($file, '.'))));
			$_file = substr($file, 0, strpos($file, '.'));
			$className = $namespace .'\\' .str_replace(DIRECTORY_SEPARATOR, '\\', $_file);
			//类不存在直接抛弃
			if (!class_exists($className)) continue;
			$classRef = new \ReflectionClass($className);
			$classDoc = DocParser::factory($classRef->getDocComment());
			//获取类公开方法
			$methods = $classRef->getMethods(\ReflectionMethod::IS_PUBLIC);
			foreach ($methods as $method) {
				//去除父类方法
				if ($method->class !== $className) continue;
				$methodDoc = DocParser::factory($method->getDocComment());
				$pattern = $methodDoc->getParam($this->pattern);
				//没有设置匹配路由的话，直接抛弃
				if (!$pattern) continue;
				$middleware = $methodDoc->getParam($this->middleware) ?: $classDoc->getParam($this->middleware) ?: array();
				$r = new Route();
				$r->title = $methodDoc->getShortDesc();
				$r->description = $methodDoc->getDesc();
				$r->pattern = $pattern;
				$r->methods = $methodDoc->getParam($this->method) ?: array('get'); //默认get
				$r->callable = $className . ':' . $method->getName();
				$r->name = $methodDoc->getParam($this->name) ?: implode($this->dash, explode(DIRECTORY_SEPARATOR, $_file.DIRECTORY_SEPARATOR.$method->name));
				$r->middleware = $middleware;
				$data[] = $r;
			}

			if ($this->cacheDir) {
				file_put_contents($cache, serialize($data));
			}
		}
		return $data;
	}

	/**
	 * 获取目录包含文件列表
	 * @param $source_dir
	 * @param bool $full_path 是否返回全路径，0:只有文件名，1：全路径，2：相对于初始路径的相对路径
	 * @param int $depth 递归深度 默认0:所有
	 * @param bool $_recursion 内部递归调用参数，外部调用不用管
	 * @return array|bool
	 */
	public static function getFileNames($source_dir, $full_path = 0, $depth = 0, $_recursion = false)
	{
		static $_filedata = array();
		static $pre = '';
		static $_depth = 0;
		if ($fp = @opendir($source_dir)) {
			if ($_recursion === false) {
				$_filedata = array();
				$source_dir = rtrim(realpath($source_dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
				$pre = $source_dir;
				$_depth = 0;
			}
			$_depth++;
			while (false !== ($file = readdir($fp))) {
				if (is_dir($source_dir . $file) && $file[0] !== '.') {
					if ($depth && $_depth >= $depth) {
						continue;
					}
					self::getFileNames($source_dir . $file . DIRECTORY_SEPARATOR, $full_path, $depth, TRUE);
				} elseif ($file[0] !== '.') {
					switch ((int)$full_path) {
						case 1:
							$_filedata[] = $source_dir . $file;
							break;
						case 2:
							$_filedata[] = str_replace($pre, '', $source_dir . $file);
							break;
						default:
							$_filedata[] = $file;
							break;
					}
				}
			}
			closedir($fp);
			return $_filedata;
		}
		return false;
	}

	/**
	 * 检查文件是否跟上一次有变化
	 * @param $file 支持文件或者文件夹
	 * @param string $cacheDir 结果存放的缓存路径
	 * @return bool
	 */
	public static function isModify($file, $cacheDir)
	{
		if (!is_writable($cacheDir)) {
			return true;
		}
		$cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
		if (is_dir($file)) {
			$type = 'dir';
			$files = self::getFileNames($file, 1);
		} elseif (file_exists($file)) {
			$type = 'file';
			$files = array($file);
		} else {
			return true;
		}
		$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'modify_' . $type . '_' . md5($file);
		$cacheData = file_exists($cacheFile) ? unserialize(file_get_contents($cacheFile)) : array();
		$cache = array();
		$flag = false;
		foreach ($files as $f) {
			$cache[$f] = md5_file($f);
			if ($flag) {
				continue;
			}
			if (!isset($cacheData[$f]) || $cacheData[$f] !== $cache[$f]) {
				$flag = true;
			}
		}
		if ($flag) {
			file_put_contents($cacheFile, serialize($cache));
		}
		return $flag;
	}

}
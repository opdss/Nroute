<?php
/**
 * Nroute.php for Nroute.
 * @author 阿新 <opdss@qq.com>
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
	 * 如: user.article.list
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

	/**
	 * 附属信息，由方法@名称 取得
	 * @var array
	 */
	private $info = array();

	private $_routes = [];

	private $_maps = [];

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
	 * @param $params  string | array
	 */
	public function attachInfo($params)
	{
		if (is_array($params)) {
			$this->info = array_merge($this->info, $params);
		} else {
			array_push($this->info, $params);
		}
		return $this;
	}

	/**
	 * 注册路由
	 * @param App $app
	 * @param $maps
	 * @return bool
	 * @throws \Exception
	 */
	public function setCtrl(...$params)
	{
		if (count($params) != 1) {
			$this->_maps[$params[0]] = $params[1];
		} elseif (is_array($params[0])){
			$this->_maps = array_merge($this->_maps, $params[0]);
		}
		return $this;
	}

	/**
	 * 注册路由
	 * @param App $app
	 * @param $maps
	 * @return bool
	 * @throws \Exception
	 */
	public function register(App $app, array $maps)
	{
		return $this->setCtrl($maps)->run($app);
	}

	/**
	 * 强制更新路由缓存，在forceUseCache=true 的时候使用
	 * @return mixed
	 * @throws \Exception
	 */
	public function forceUpdate()
	{
		return $this->getRoutes(true, false);
	}

	/**
	 * 执行映射路由
	 * @param App $app
	 * @return bool
	 * @throws \Exception
	 */
	public function run(App $app)
	{
		return $this->injection($app, $this->getRoutes());
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
			$r = $app->map($route['methods'], $route['pattern'], $route['callable'])->setName($route['name']);
			if (!empty($route['middleware'])) {
				foreach ($route['middleware'] as $middleware) {
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
	public function getRoutes($forceUpdate = false, $useCache = true)
	{
		if ($forceUpdate || empty($this->_routes)) {
			$maps = $this->_maps;
			if ($forceUpdate || $useCache && $this->forceUseCache) {
				if (!$this->cacheDir) {
					throw new \Exception('未设置路由缓存目录');
				}
				$cacheName = $this->cacheDir . $this->cachePre . md5(serialize($maps));
				if (!$forceUpdate && file_exists($cacheName)) {
					$this->_routes = unserialize(file_get_contents($cacheName));
				} else {
					foreach ($maps as $k => $v) {
						$this->_routes = array_merge($this->_routes, $this->readDocRoutes($k, $v));
					}
					file_put_contents($cacheName, serialize($this->_routes));
				}
			} else {
				foreach ($maps as $k => $v) {
					$this->_routes = array_merge($this->_routes, $this->readDocRoutes($k, $v, $useCache));
				}
			}
		}
		return $this->_routes;
	}

	/**
	 * 读取注释路由
	 * @param $ctrlDir
	 * @param $namespace
	 * @return array|mixed
	 */
	public function readDocRoutes($ctrlDir, $namespace, $useCache = true)
	{
		$ctrlDir = rtrim($ctrlDir, DIRECTORY_SEPARATOR);
		$namespace = rtrim($namespace, '\\');

		//设置了缓存目录的话，优先读取缓存
		if ($useCache && $this->cacheDir) {
			$cache = $this->cacheDir.$this->cachePre.md5($ctrlDir. $namespace);
			if (file_exists($cache) && !self::isModify($ctrlDir, $this->cacheDir)) {
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
				//自己本身没有设置中间件，会取类的
				$middleware = $methodDoc->getParam($this->middleware) ?: $classDoc->getParam($this->middleware) ?: array();
				$r = array(
					'title' => $methodDoc->getShortDesc(),
					'description' => $methodDoc->getDesc(),
					'pattern' => $pattern,
					'methods' => $methodDoc->getParam($this->method) ?: array('get'), //默认get
					'callable' => $className . ':' . $method->getName(),
					'name' => $methodDoc->getParam($this->name) ?: strtolower(implode($this->dash, explode(DIRECTORY_SEPARATOR, $_file.DIRECTORY_SEPARATOR.$method->name))),
					'middleware' => $middleware,
					'info' => []
				);
				if (!empty($this->info)) {
					foreach ($this->info as $info) {
						$r['info'][$info] = $methodDoc->getParam($info);
					}
				}
				$data[] = $r;
			}
		}
		//写入缓存缓存
		if ($useCache && $this->cacheDir) {
			file_put_contents($cache, serialize($data));
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
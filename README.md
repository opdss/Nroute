# Nroute
slim框架的注释路由器

适用于psr4规范的 控制器->方法 类型路由

如我的控制器目录是 APP_DIR . 'Controllers'， 其命名空间是 App\Controllers， 结构如下
* APP_DIR
    * Controllers
        * User.php
        * Article.php
        
User.php 内容如下：
```
namespace App\Controllers;

use App\Models\Users;
use Slim\Http\Request;
use Slim\Http\Response;

class User
{
	/**
	 * 首页
	 *
	 * @pattern /users
	 * @method get
	 * @middleware \App\Middleware\Auth
	 * @param Request $request
	 * @param Response $response
	 * @param array $args
	 * @return Response
	 */
	public function index(Request $request, Response $response, array $args)
	{
	    return Users::all();
	}
}
```

然后在index.php 加入如下代码：

```
$config = array('cacheDir'=>CACHE_DIR, 'forceUseCache'=> true);
\Opdss\Nroute\Nroute::factory($config)->register($app, array(APP_DIR . 'Controllers' => 'App\\Controllers'));
```

/users 路由就会自动读取注册了

forceUseCache参数

forceUseCache参数可以加速路由注册执行的速度，略去了繁琐的文档扫描。
但是当使用了forceUseCache参数的时候，注册器会检测是否有缓存，有则直接读取，将不再判断扫描是否有更新。
所以生产环境使用了这个参数的时候，需要更新路由时可以使用forceUpdate() 方法强制扫描刷新路由缓存。
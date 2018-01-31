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
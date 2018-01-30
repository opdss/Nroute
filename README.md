# Nroute
slim框架的注释路由器

适用于psr4规范的 控制器->方法 类型路由

在index.php 加入如下代码：
````$config = array('cacheDir'=>CACHE_DIR, 'forceUseCache'=> true);
\App\Libraries\Nroute\Nroute::factory($config)->register($app, array(APP_DIR . 'Controllers' => 'App\\Controllers'));


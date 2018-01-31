<?php
/**
 * DocParser.php for Nroute.
 * @author SamWu
 * @date 2018/1/30 16:24
 * @copyright istimer.com
 */
 namespace Opdss\Nroute;

 class Route
 {
 	public $title = '';
 	public $description = '';
 	public $methods = [];
 	public $pattern = '';
 	public $callable = '';
 	public $name = '';
 	public $middleware = [];
 }
<?php

namespace Tests;

use Exception;
use Gvera\Cache\Cache;
use Gvera\Helpers\config\Config;
use Gvera\Helpers\fileSystem\FileManager;
use Gvera\Helpers\http\HttpRequest;
use Gvera\Helpers\http\HttpRequestValidator;
use Gvera\Helpers\routes\RouteManager;
use Gvera\Helpers\validation\ValidationService;
use PHPUnit\Framework\TestCase;

class RouteManagerTest extends TestCase
{
    /**
     * @test
     * @throws \Gvera\Exceptions\InvalidArgumentException
     * @throws \Gvera\Exceptions\NotFoundException
     */
    public function testRouteManager()
    {

        define('CONFIG_ROOT', __DIR__."/../config/");
        $_SERVER['REQUEST_METHOD'] = HttpRequest::GET;
        $config = new Config();
        $config->overrideKey('cache_type', 'files');
        $config->overrideKey('files_cache_path', __DIR__."/../var/cache/files/");
        Cache::setConfig($config);
        $fileManager = new FileManager($config);
        if (file_exists(__DIR__.'/../var/cache/files/gv_cache_files_gv_routes')) {
            $fileManager->removeFromFileSystem(__DIR__.'/../var/cache/files/gv_cache_files_gv_routes');
        }
        $request = new HttpRequest($fileManager, new HttpRequestValidator(new ValidationService()));
        $routeManager = new RouteManager($request, CONFIG_ROOT . "routes.yml");

        $this->assertNotFalse($routeManager->getRoute('/settings/opcache'));
        $this->assertFalse($routeManager->getRoute('/invalid/route'));
        $this->assertFalse($routeManager->getRoute('/asdas'));

        $routeManager->addRoute('test1', 'GET', '/new/test', 'test->test');
        $this->assertNotFalse('/new/test');
        $this->assertTrue(count($routeManager->getExcludeDirectories()) === 2);
        $_SERVER['REQUEST_METHOD'] = HttpRequest::GET;
        $this->assertNotFalse($routeManager->getRoute('/examples/qwe/3/2'));

        //checking if it hits the cache
        $routeManager = new RouteManager($request, CONFIG_ROOT . "routes.yml");
        $this->assertNotEmpty($routeManager->getExcludeDirectories());

        $this->expectException(Exception::class);
        $routeManager->addRoute('opcache', "asd", "asd", "asd");

    }
}
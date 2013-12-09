<?php
/*
 * Project uses :
 * - SimpleUser by Jason Grimes
 * - Twitter Bootstrap
 * 
 */

require __DIR__.'/../vendor/autoload.php';
use Symfony\Component\HttpKernel\Debug\ExceptionHandler;
use Symfony\Component\HttpFoundation\Request;
use Silex\Provider;

ExceptionHandler::register();

$app = new Silex\Application();
$app['debug'] = true;

$config=parse_ini_file(__DIR__.'/../src/application.ini');
 
$app->register(new Provider\DoctrineServiceProvider(), array('db.options' => array(
    'driver'   => 'pdo_mysql',
    'dbname' => $config['MYSQL_DB'],
    'host' => $config['MYSQL_HOST'],
    'user' => $config['MYSQL_USER'],
    'password' => $config['MYSQL_PASSWORD'],
)));

$app->register(new Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'register' => array (
            'pattern' => '^/ROR/user/register$',
            'anonymous' => true,
        ),
        'login' => array (
            'pattern' => '^/ROR/user/login$',
            'anonymous' => true,
        ),
        'secured' => array(
            'pattern' => '^.*$',
            'remember_me' => array(),
            
            'form' => array(
                'login_path' => '/ROR/user/login',
                'check_path' => '/ROR/user/login_check',
            ),
            'logout' => array(
                'logout_path' => '/ROR/user/logout',
                'target_url' => '/ROR'
            ),
            'users' => $app->share(function($app) { return $app['user.manager']; }),
        ),
    ),
));
            
$app->register(new Provider\RememberMeServiceProvider());     
$app->register(new Provider\SessionServiceProvider()); 
$app->register(new Provider\ServiceControllerServiceProvider()); 
$app->register(new Provider\UrlGeneratorServiceProvider()); 

// Twig
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../src/views',
));

// User Controller Service Provider for SimpleUser
$app->register($u = new SimpleUser\UserServiceProvider());
$app['twig.loader.filesystem']->addPath(__DIR__.'/../src/views/user','user');
$app['user.controller']->setLayoutTemplate('layout.twig');
$app->mount('/ROR/user', $u);

// ROR Controller Provider
$app->mount('/ROR', new ROR\RORControllerProvider());
$app->run();

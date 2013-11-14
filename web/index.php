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

$app->register(new Provider\DoctrineServiceProvider(), array('db.options' => array(
    'driver'   => 'pdo_mysql',
    'dbname' => 'silex_ROR',
    'host' => 'localhost',
    'user' => 'root',
    'password' => '',
)));

$app->register(new Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'register' => array (
            'pattern' => '^/register$',
            'anonymous' => true,
        ),
        'login' => array (
            'pattern' => '^/login$',
            'anonymous' => true,
        ),
        'secured' => array(
            'pattern' => '^.*$',
            'remember_me' => array(),
            
            'form' => array(
                'login_path' => '/login',
                'check_path' => '/login_check',
            ),
            'logout' => array(
                'logout_path' => '/logout',
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
$app->mount('/', $u);

// ROR Controller Provider
$app->mount('/ROR', new ROR\RORControllerProvider());
$app->run();

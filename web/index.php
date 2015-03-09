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

// i18n
$lang = 'en_US.utf8';
// Those 2 lines below are needed on some systems, so better safe than sorry
putenv('LC_ALL=$lang'); 
putenv('LANGUAGE=$lang');
setlocale(LC_ALL, $lang);
bindtextdomain('messages', 'locale');
bind_textdomain_codeset('messages', 'UTF-8');
textdomain('messages');

// TO DO : http_negotiate_language(array('en') , $result);
ExceptionHandler::register();

$app = new Silex\Application();
$app['debug'] = true;

$config=parse_ini_file(__DIR__.'/../config/application.ini');
 
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
            'pattern' => '^/silex-ror/user/register$',
            'anonymous' => true,
        ),
        'login' => array (
            'pattern' => '^/silex-ror/user/login$',
            'anonymous' => true,
        ),
        'secured' => array(
            'pattern' => '^.*$',
            'remember_me' => array(),
            
            'form' => array(
                'login_path' => '/silex-ror/user/login',
                'check_path' => '/silex-ror/user/login_check',
            ),
            'logout' => array(
                'logout_path' => '/silex-ror/user/logout',
                'target_url' => '/silex-ror'
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
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallbacks' => array('en'),
));
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../src/views',
));

// User Controller Service Provider for SimpleUser
$app->register($u = new SimpleUser\UserServiceProvider());
$app['twig.loader.filesystem']->addPath(__DIR__.'/../src/views/user','user');
$app['user.controller']->setLayoutTemplate('layout.twig');
$app->mount('/silex-ror/user', $u);

// ROR Controller Provider
$app->mount('/', new ROR\RORControllerProvider());
$app->run();

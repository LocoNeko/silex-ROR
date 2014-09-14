<?php
    require dirname(__DIR__) . '/vendor/autoload.php';
    $config=parse_ini_file(__DIR__.'/../config/application.ini');
    $loop   = React\EventLoop\Factory::create();
    $pusher = new Ratchet\Pusher;

    // Listen for the web server to make a ZeroMQ push after an ajax request
    $context = new React\ZMQ\Context($loop);
    $pull = $context->getSocket(ZMQ::SOCKET_PULL);
    $pull->bind('tcp://127.0.0.1:'.$config['WS_PORT']); // Binding to 127.0.0.1 means the only client that can connect is itself
    $pull->on('message', array($pusher, 'onAction'));
    echo "Listening on port 5555".PHP_EOL;

    // Set up our WebSocket server for clients wanting real-time updates
    $webSock = new React\Socket\Server($loop);
    $webSock->listen($config['WS_REMOTE_PORT'], '0.0.0.0'); // Binding to 0.0.0.0 means remotes can connect
    $webServer = new Ratchet\Server\IoServer(
        new Ratchet\Http\HttpServer(
            new Ratchet\WebSocket\WsServer(
                new Ratchet\Wamp\WampServer(
                    $pusher
                )
            )
        ),
        $webSock
    );

    $loop->run();
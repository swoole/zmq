<?php
require_once __DIR__ . '/../src/Swoole/Async/ZMQ.php';

$sub = new Swoole\Component\ZMQ(ZMQ::SOCKET_SUB);

$sub->on('Message', function ($msg)
{
    echo "Received: $msg\n";
});

$sub->bind('tcp://0.0.0.0:5556');
$sub->subscribe('foo');

$pub = new Swoole\Component\ZMQ(ZMQ::SOCKET_PUB);
$pub->connect('tcp://127.0.0.1:5556');

Swoole\Timer::tick(1000, function () use ($pub)
{
    static $i = 0;
    $msg = "foo " . $i++;
    echo "Sending: $msg\n";
    $pub->send($msg);
});

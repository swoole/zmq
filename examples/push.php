<?php
require_once __DIR__ . '/../src/Swoole/Async/ZMQ.php';

$zmq = new Swoole\Component\ZMQ();

$zmq->connect('tcp://0.0.0.0:5555');

Swoole\Timer::tick(1000, function () use ($zmq)
{
    static $i = 0;
    $msg = "hello-" . $i++;
    echo "Sending: $msg\n";
    $zmq->send($msg);
});

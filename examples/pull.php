<?php
require_once __DIR__ . '/../src/Swoole/Async/ZMQ.php';

$zmq = new Swoole\Component\ZMQ();

$zmq->on('Message', function ($msg)
{
    echo "Received: $msg\n";
});

$zmq->bind('tcp://0.0.0.0:5555');

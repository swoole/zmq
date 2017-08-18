# Swoole/Async/ZMQ

ZeroMQ bindings for Swoole.

## Install

The recommended way to install swoole/zmq is [through composer](http://getcomposer.org).

```JSON
{
    "require": {
        "swoole/zmq": "0.1.*"
    }
}
```

## Example

And don't forget to autoload:

```php
<?php
require 'vendor/autoload.php';
```

Here is an example of a push socket:

```php
$zmq = new Swoole\Async\ZMQ();

$zmq->on('Message', function ($msg)
{
    echo "Received: $msg\n";
});

$zmq->bind('tcp://0.0.0.0:9530');
```

And the pull socket that goes with it:

```php
$zmq = new Swoole\Async\ZMQ();

$zmq->connect('tcp://0.0.0.0:5555');

Swoole\Timer::tick(1000, function () use ($zmq)
{
    static $i = 0;
    $msg = "hello-" . $i++;
    echo "Sending: $msg\n";
    $zmq->send($msg);
});
```

<?php
namespace Swoole\Async;

class ZMQ
{
    /**
     * @var \ZMQContext
     */
    protected $context;
    /**
     * @var \ZMQSocket
     */
    protected $socket;

    /**
     * @var \SplQueue
     */
    protected $messages;

    protected $closed;
    protected $fd;
    protected $listening = false;

    protected $onClose = null;
    protected $onMessage = null;
    protected $onError = null;

    protected $type;

    function __construct($type = null)
    {
        $this->type = $type;
        $this->context = new \ZMQContext();
        $this->messages = new \SplQueue();
    }

    function bind($address)
    {
        if ($this->socket)
        {
            throw new ZMQException("Already in listening.");
        }
        if (!$this->type)
        {
            $this->type = \ZMQ::SOCKET_PULL;
        }
        $this->socket = $this->context->getSocket($this->type);
        $this->socket->bind($address);
        if (!$this->onMessage)
        {
            throw new ZMQException("require onMessage callback.");
        }
        $this->fd = $this->socket->getSockOpt(\ZMQ::SOCKOPT_FD);
        swoole_event_add($this->fd, [$this, 'handleReadEvent'], [$this, 'handleWriteEvent'], SWOOLE_EVENT_READ);
    }

    function connect($address)
    {
        if ($this->socket)
        {
            throw new ZMQException("Has been connected.");
        }
        if (!$this->type)
        {
            $this->type = \ZMQ::SOCKET_PUSH;
        }
        $this->socket = $this->context->getSocket($this->type);
        $this->socket->connect($address);
        $this->fd = $this->socket->getSockOpt(\ZMQ::SOCKOPT_FD);
        swoole_event_add($this->fd, [$this, 'handleReadEvent'], [$this, 'handleWriteEvent'], SWOOLE_EVENT_READ);
    }

    public function subscribe($channel)
    {
        $this->socket->setSockOpt(\ZMQ::SOCKOPT_SUBSCRIBE, $channel);
    }

    public function unsubscribe($channel)
    {
        $this->socket->setSockOpt(\ZMQ::SOCKOPT_UNSUBSCRIBE, $channel);
    }

    public function send($message)
    {
        if ($this->closed)
        {
            return;
        }

        $this->messages->push($message);

        if (!$this->listening)
        {
            $this->listening = true;
            swoole_event_set($this->fd, null, null, SWOOLE_EVENT_READ | SWOOLE_EVENT_WRITE);
        }
    }

    public function close()
    {
        if ($this->closed)
        {
            return;
        }

        if ($this->onClose)
        {
            call_user_func($this->onClose);
        }

        swoole_event_del($this->fd);
        unset($this->socket);
        $this->closed = true;
    }

    function on($type, callable $callback)
    {
        if (strcasecmp($type, 'Close') == 0)
        {
            $this->onClose = $callback;
        }
        elseif (strcasecmp($type, 'Message') == 0)
        {
            $this->onMessage = $callback;
        }
        elseif (strcasecmp($type, 'Error') == 0)
        {
            $this->onError = $callback;
        }
        else
        {
            throw new ZMQException("unknown event[$type].");
        }
    }

    public function __call($method, $args)
    {
        return call_user_func_array(array($this->socket, $method), $args);
    }

    function handleReadEvent()
    {
        while (true)
        {
            $events = $this->socket->getSockOpt(\ZMQ::SOCKOPT_EVENTS);

            $hasEvents = ($events & \ZMQ::POLL_IN) || ($events & \ZMQ::POLL_OUT && $this->buffer->listening);
            if (!$hasEvents)
            {
                break;
            }

            if ($events & \ZMQ::POLL_IN)
            {
                $messages = $this->socket->recvmulti(\ZMQ::MODE_NOBLOCK);
                if (false !== $messages)
                {
                    foreach ($messages as $message)
                    {
                        call_user_func($this->onMessage, $message);
                    }
                }
            }

            if ($events & \ZMQ::POLL_OUT && $this->listening)
            {
                $this->handleWriteEvent();
            }
        }
    }

    /**
     * @param int $type
     * @return bool
     */
    protected function isReadableSocketType($type)
    {
        $readableTypes = array(
            \ZMQ::SOCKET_PULL => true,
            \ZMQ::SOCKET_SUB => true,
            \ZMQ::SOCKET_REQ => true,
            \ ZMQ::SOCKET_REP => true,
            \ZMQ::SOCKET_ROUTER => true,
            \ZMQ::SOCKET_DEALER => true,
        );

        return isset($readableTypes[$type]);
    }

    public function handleWriteEvent()
    {
        while ($this->messages->count() > 0)
        {
            $message = $this->messages->pop();
            try
            {
                if (is_array($message))
                {
                    $sent = (bool)$this->socket->sendmulti($message, \ZMQ::MODE_NOBLOCK);
                }
                else
                {
                    $sent = (bool)$this->socket->send($message, \ZMQ::MODE_NOBLOCK);
                }
                if ($sent)
                {
                    continue;
                }
            }
            catch (\ZMQSocketException $e)
            {
                call_user_func($this->onMessage, $e);

                return;
            }
        }

        swoole_event_set($this->fd, null, null, SWOOLE_EVENT_READ);
        $this->listening = false;
    }
}


class ZMQException extends \RuntimeException
{

}
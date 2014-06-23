<?php

namespace React\Socket;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Uri\InvalidUriException;
use React\Uri\Uri;

/** @event connection */
class Server extends EventEmitter implements ServerInterface
{
    public $master;
    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    private function createContext($ctx)
    {
        if (!is_resource($ctx)) {
            $options = (array) $ctx;
        } else if (false === $options = stream_context_get_options($ctx)) {
            $options = [];
        }

        return stream_context_create($options);
    }

    public function listen($uri, $ctx = null)
    {
        if (!$uri instanceof Uri) {
            try {
                $uri = new Uri($uri);
            } catch (InvalidUriException $e) {
                $message = "Invalid URI: {$uri}";
                throw new ConnectionException($message, 0, $e);
            }
        }

        $localSockAddr = $uri->getConnectionString(['scheme', 'host', 'port'], ['scheme' => 'tcp']);
        $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        $ctx = $this->createContext($ctx);

        $this->master = @stream_socket_server($localSockAddr, $errno, $errstr, $flags, $ctx);
        if (false === $this->master) {
            $message = "Could not bind to {$uri}: $errstr";
            throw new ConnectionException($message, $errno);
        }
        stream_set_blocking($this->master, 0);

        $this->loop->addReadStream($this->master, function ($master) {
            $newSocket = stream_socket_accept($master);
            if (false === $newSocket) {
                $this->emit('error', array(new \RuntimeException('Error accepting new connection')));

                return;
            }
            $this->handleConnection($newSocket);
        });
    }

    public function handleConnection($socket)
    {
        stream_set_blocking($socket, 0);

        $client = $this->createConnection($socket);

        $this->emit('connection', array($client));
    }

    public function getPort()
    {
        $name = stream_socket_get_name($this->master, false);

        return (int) substr(strrchr($name, ':'), 1);
    }

    public function shutdown()
    {
        $this->loop->removeStream($this->master);
        fclose($this->master);
        $this->removeAllListeners();
    }

    public function createConnection($socket)
    {
        return new Connection($socket, $this->loop);
    }
}

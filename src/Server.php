<?php

namespace React\Socket;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

/** @event connection */
class Server extends EventEmitter implements ServerInterface
{
    public $master;
    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    private function getCryptoMethod($protocol)
    {
        switch ($protocol) {
            case 'ssl': // Means "any supported protocol, SSL or TLS"
                if (defined('STREAM_CRYPTO_METHOD_ANY_CLIENT')) { // PHP>=5.6.0
                    return STREAM_CRYPTO_METHOD_ANY_CLIENT;
                }
                return STREAM_CRYPTO_METHOD_SSLv23_CLIENT;

            case 'tls': // Means "any support TLS protocol"
                return STREAM_CRYPTO_METHOD_TLS_CLIENT;

            case 'sslv2':
                return STREAM_CRYPTO_METHOD_SSLv2_CLIENT;

            case 'sslv3':
                return STREAM_CRYPTO_METHOD_SSLv3_CLIENT;

            case 'tlsv1.0':
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT')) { // PHP>=5.6.0
                    return STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;
                }
                return STREAM_CRYPTO_METHOD_TLS_CLIENT;

            case 'tlsv1.1':
                if (!defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) {
                    throw new UnsupportedCryptoMethodException('TLSv1.1 is not available in PHP<5.6.0');
                }
                return STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;

            case 'tlsv1.2':
                if (!defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    throw new UnsupportedCryptoMethodException('TLSv1.2 is not available in PHP<5.6.0');
                }
                return STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

            default:
                throw new UnsupportedProtocolException("Unsupported server protocol: {$protocol}");
        }
    }

    private function createContext($protocol, $opts)
    {
        $protocol = strtolower($protocol);
        $secure = in_array(substr($protocol, 0, 3), array('ssl', 'tls'));
        if (!$secure && $protocol !== 'tcp') {
            throw new UnsupportedProtocolException("Unsupported server protocol: {$protocol}");
        }

        if ($secure && !isset($opts['ssl']['crypto_method'])) {
            $opts['ssl']['crypto_method'] = $this->getCryptoMethod($protocol);
        }

        return stream_context_create($opts);
    }

    public function listen($port, $host = '127.0.0.1', $protocol = 'tcp', array $ctxOpts = array())
    {
        if (strpos($host, ':') !== false) {
            // enclose IPv6 addresses in square brackets before appending port
            $host = '[' . $host . ']';
        }

        $localSocket = "tcp://$host:$port";
        $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
        $ctx = $this->createContext($protocol, $ctxOpts);

        $this->master = @stream_socket_server($localSocket, $errno, $errstr, $flags, $ctx);
        if (false === $this->master) {
            $message = "Could not bind to $localSocket: $errstr";
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
        $ctxOpts = stream_context_get_options($socket);

        if (isset($ctxOpts['ssl']['crypto_method'])) {
            return new SecureConnection($socket, $this->loop);
        }

        return new Connection($socket, $this->loop);
    }
}

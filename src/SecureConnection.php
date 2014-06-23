<?php

namespace React\Socket;

use React\EventLoop\LoopInterface;

class SecureConnection extends Connection
{
    /**
     * Whether encryption is currently active on the socket
     *
     * @var bool
     */
    private $cryptoEnabled = false;

    /**
     * The type of encryption to use on the socket
     *
     * @var int
     */
    private $cryptoMethod;

    public function __construct($stream, LoopInterface $loop)
    {
        $this->cryptoMethod = stream_context_get_options($stream)['ssl']['crypto_method'];
        $this->enableCrypto($stream);

        parent::__construct($stream, $loop);
    }

    /**
     * Negotiate encryption on the socket
     *
     * @param resource $stream
     * @return bool
     */
    private function enableCrypto($stream)
    {
        $result = stream_socket_enable_crypto($stream, true, $this->cryptoMethod);

        if ($result === true) {
            $this->cryptoEnabled = true;
            return true;
        }

        if ($result === false) {
            $this->end();
        }

        return false;
    }

    public function handleData($stream)
    {
        if ($this->cryptoEnabled) {
            parent::handleData($stream);
        } else {
            $this->enableCrypto($stream);
        }
    }
}

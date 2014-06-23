<?php

namespace React\Socket;

use Evenement\EventEmitterInterface;
use React\Uri\Uri;

/** @event connection */
interface ServerInterface extends EventEmitterInterface
{
    /**
     * Start listening for clients
     *
     * @param string|Uri $uri Local address of the listen socket
     * @param array|resource $ctx An array of context options or a context/stream
     */
    public function listen($uri, $ctx = null);

    /**
     * Get the port number to which the listen socket is bound
     *
     * @return int
     */
    public function getPort();

    /**
     * Shut down the listen socket
     *
     * Prevents new clients from connecting. Does not disconnect existing clients.
     */
    public function shutdown();
}

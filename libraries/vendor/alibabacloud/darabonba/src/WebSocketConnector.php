<?php

namespace AlibabaCloud\Dara;

use Ratchet\Client\Connector;
use Ratchet\RFC6455\Handshake\ClientNegotiator;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;

class WebSocketConnector extends Connector
{
    public function __construct(?LoopInterface $loop = null, ?ConnectorInterface $connector = null)
    {
        $this->_loop = $loop ?: Loop::get();

        if (null === $connector) {
            $connector = new \React\Socket\Connector([
                'timeout' => 20,
            ], $this->_loop);
        }

        $this->_connector = $connector;
        $this->_negotiator = new ClientNegotiator();
    }
}

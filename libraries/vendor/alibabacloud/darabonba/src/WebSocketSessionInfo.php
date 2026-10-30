<?php

namespace AlibabaCloud\Dara;

class WebSocketSessionInfo
{
    /**
     * @var string
     */
    public $sessionID;

    /**
     * @var string
     */
    public $requestID;

    /**
     * @var float
     */
    public $connectedAt;

    /**
     * @var string
     */
    public $remoteAddr;

    /**
     * @var string
     */
    public $localAddr;

    /**
     * @var array
     */
    public $attributes;

    public function __construct($sessionID = '', $requestID = '', $connectedAt = null, $remoteAddr = '', $localAddr = '', $attributes = [])
    {
        $this->sessionID = $sessionID;
        $this->requestID = $requestID;
        $this->connectedAt = $connectedAt !== null ? $connectedAt : microtime(true);
        $this->remoteAddr = $remoteAddr;
        $this->localAddr = $localAddr;
        $this->attributes = $attributes;
    }
}

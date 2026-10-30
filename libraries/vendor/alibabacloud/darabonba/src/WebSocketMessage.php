<?php

namespace AlibabaCloud\Dara;

class WebSocketMessage
{
    /**
     * @var int
     */
    public $type;

    /**
     * @var string
     */
    public $payload;

    /**
     * @var array
     */
    public $headers;

    /**
     * @var float
     */
    public $timestamp;

    public function __construct($type = null, $payload = '', $headers = [], $timestamp = null)
    {
        $this->type = $type;
        $this->payload = $payload;
        $this->headers = $headers;
        $this->timestamp = $timestamp !== null ? $timestamp : microtime(true);
    }
}

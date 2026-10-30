<?php

namespace AlibabaCloud\Dara;

interface WebSocketClientInterface
{
    /**
     * @param Request $request
     * @param array   $runtimeObject
     *
     * @return Response
     */
    public function connect($request, $runtimeObject);

    /**
     * @return void
     */
    public function disconnect();

    /**
     * @return Response|null
     */
    public function reconnect();

    /**
     * @return Response|null
     */
    public function reconnectGracefully();

    /**
     * @return bool
     */
    public function isConnected();

    /**
     * @param string $text
     *
     * @return void
     */
    public function sendText($text);

    /**
     * @param string $data
     *
     * @return void
     */
    public function sendBinary($data);

    /**
     * @return WebSocketSessionInfo|null
     */
    public function getSessionInfo();

    /**
     * @param int $timeoutMs
     *
     * @return void
     */
    public function pump($timeoutMs = 100);

    /**
     * @return void
     */
    public function close();
}

<?php

namespace AlibabaCloud\Dara;

interface WebSocketHandler
{
    /**
     * @param WebSocketSessionInfo $session
     *
     * @return void
     */
    public function afterConnectionEstablished($session);

    /**
     * @param WebSocketSessionInfo $session
     * @param WebSocketMessage     $message
     *
     * @return void
     */
    public function handleRawMessage($session, $message);

    /**
     * @param WebSocketSessionInfo $session
     * @param \Exception|\Throwable $err
     *
     * @return void
     */
    public function handleError($session, $err);

    /**
     * @param WebSocketSessionInfo $session
     * @param int                  $code
     * @param string               $reason
     *
     * @return void
     */
    public function afterConnectionClosed($session, $code, $reason);
}

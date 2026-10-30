<?php

namespace AlibabaCloud\Dara;

class AbstractWebSocketHandler implements WebSocketHandler
{
    public function afterConnectionEstablished($session)
    {
    }

    public function handleRawMessage($session, $message)
    {
    }

    public function handleError($session, $err)
    {
    }

    public function afterConnectionClosed($session, $code, $reason)
    {
    }
}

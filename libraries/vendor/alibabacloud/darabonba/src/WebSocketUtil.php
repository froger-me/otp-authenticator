<?php

namespace AlibabaCloud\Dara;

use Clue\React\Block;
use Exception;
use InvalidArgumentException;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Socket\Connector as SocketConnector;

class WebSocketUtil
{
    public static function getWebSocketPingInterval($runtime)
    {
        return self::getRuntimeValue($runtime, 'webSocketPingInterval');
    }

    public static function getWebSocketPongTimeout($runtime)
    {
        return self::getRuntimeValue($runtime, 'webSocketPongTimeout');
    }

    public static function getWebSocketEnableReconnect($runtime)
    {
        return self::getRuntimeValue($runtime, 'webSocketEnableReconnect');
    }

    public static function getWebSocketReconnectInterval($runtime)
    {
        return self::getRuntimeValue($runtime, 'webSocketReconnectInterval');
    }

    public static function getWebSocketMaxReconnectTimes($runtime)
    {
        return self::getRuntimeValue($runtime, 'webSocketMaxReconnectTimes');
    }

    public static function getWebSocketWriteTimeout($runtime)
    {
        return self::getRuntimeValue($runtime, 'webSocketWriteTimeout');
    }

    public static function getWebSocketHandshakeTimeout($runtime)
    {
        return self::getRuntimeValue($runtime, 'webSocketHandshakeTimeout');
    }

    /**
     * @param mixed $runtime
     *
     * @return WebSocketHandler|null
     */
    public static function getWebSocketHandler($runtime)
    {
        if ($runtime === null) {
            return null;
        }
        if (\is_array($runtime) && isset($runtime['webSocketHandler']) && $runtime['webSocketHandler'] instanceof WebSocketHandler) {
            return $runtime['webSocketHandler'];
        }
        if (\is_object($runtime) && isset($runtime->webSocketHandler) && $runtime->webSocketHandler instanceof WebSocketHandler) {
            return $runtime->webSocketHandler;
        }

        return null;
    }

    /**
     * @param array $headers
     *
     * @return Response
     */
    public static function newWebSocketResponse($statusCode, $statusMessage, $headers = [])
    {
        $response = new Response(new \GuzzleHttp\Psr7\Response(
            $statusCode,
            $headers,
            null,
            '1.1',
            $statusMessage
        ));
        $normalized = [];
        foreach ($headers as $key => $values) {
            $normalized[strtolower($key)] = \is_array($values) ? $values : [$values];
        }
        $response->headers = $normalized;
        $response->statusCode = $statusCode;
        $response->statusMessage = $statusMessage;

        return $response;
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public static function buildWebSocketURL($request)
    {
        if ($request === null) {
            throw new InvalidArgumentException('request cannot be null');
        }

        $protocol = $request->protocol;
        if ($protocol === null || $protocol === '') {
            $protocol = 'ws';
        } else {
            $protocol = strtolower($protocol);
            if ($protocol === 'http') {
                $protocol = 'ws';
            } elseif ($protocol === 'https') {
                $protocol = 'wss';
            }
        }
        $request->protocol = $protocol;

        $domain = null;
        if (isset($request->headers['host'])) {
            $domain = $request->headers['host'];
        }
        if ($domain === null || $domain === '') {
            throw new InvalidArgumentException('domain is required (set in request.Headers["host"] or request.Domain)');
        }

        $requestURL = $protocol . '://' . $domain;
        $pathname = $request->pathname;
        $requestURL .= ($pathname !== null && $pathname !== '') ? $pathname : '/';

        if (!empty($request->query)) {
            $query = http_build_query($request->query);
            if ($query !== '') {
                $requestURL .= (strpos($requestURL, '?') !== false ? '&' : '?') . $query;
            }
        }

        return $requestURL;
    }

    public static function convertToWebSocketMessageType($messageType)
    {
        switch ($messageType) {
            case 'text':
                return WebSocketMessageType::TEXT;
            case 'binary':
                return WebSocketMessageType::BINARY;
            case 'ping':
                return WebSocketMessageType::PING;
            case 'pong':
                return WebSocketMessageType::PONG;
            case 'close':
                return WebSocketMessageType::CLOSE;
            default:
                return WebSocketMessageType::BINARY;
        }
    }

    public static function generateSessionID()
    {
        return 'ws-session-' . (int) (microtime(true) * 1000000000);
    }

    /**
     * @param WebSocketHandler $handler
     *
     * @return DefaultWebSocketClient
     */
    public static function newDefaultWebSocketClient($handler)
    {
        if ($handler === null) {
            throw new InvalidArgumentException('handler cannot be null');
        }

        return new DefaultWebSocketClient($handler);
    }

    /**
     * @param Request $request
     * @param array   $runtimeObject
     *
     * @return array [DefaultWebSocketClient, Response]
     */
    public static function newWebSocketClientAndConnect($request, $runtimeObject)
    {
        if ($runtimeObject === null) {
            throw new InvalidArgumentException('runtimeObject cannot be null');
        }

        $handler = self::getWebSocketHandler($runtimeObject);
        if ($handler === null) {
            throw new InvalidArgumentException('WebSocketHandler is required: please set it in runtimeObject.webSocketHandler');
        }

        $client = self::newDefaultWebSocketClient($handler);
        $response = $client->connect($request, $runtimeObject);

        return [$client, $response];
    }

    private static function getRuntimeValue($runtime, $key)
    {
        if ($runtime === null) {
            return null;
        }
        if (\is_array($runtime) && \array_key_exists($key, $runtime)) {
            return $runtime[$key];
        }
        if (\is_object($runtime) && isset($runtime->$key)) {
            return $runtime->$key;
        }

        return null;
    }
}

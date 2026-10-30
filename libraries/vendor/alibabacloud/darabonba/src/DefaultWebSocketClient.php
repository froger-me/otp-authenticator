<?php

namespace AlibabaCloud\Dara;

use Clue\React\Block;
use Exception;
use InvalidArgumentException;
use Ratchet\Client\WebSocket;
use React\EventLoop\Factory;
use React\Socket\Connector as SocketConnector;

class DefaultWebSocketClient implements WebSocketClientInterface
{
    const STATE_DISCONNECTED = 0;
    const STATE_CONNECTING = 1;
    const STATE_CONNECTED = 2;
    const STATE_DISCONNECTING = 3;

    /** @var WebSocketHandler */
    private $handler;

    /** @var bool */
    private $stopped = false;

    /** @var bool */
    private $pongReceived = false;

    /** @var int */
    private $state = self::STATE_DISCONNECTED;

    /** @var WebSocket|null */
    private $conn;

    /** @var WebSocketSessionInfo|null */
    private $session;

    /** @var Request|null */
    private $request;

    /** @var array|null */
    private $runtimeObject;

    /** @var \React\EventLoop\LoopInterface|null */
    private $loop;

    /** @var int */
    private $reconnectCount = 0;

    /** @var \React\EventLoop\TimerInterface|null */
    private $pingTimer;

    /** @var float milliseconds */
    private $pingInterval = 30000;

    /** @var float milliseconds */
    private $reconnectInterval = 5000;

    /** @var float milliseconds */
    private $writeTimeout = 30000;

    /** @var float milliseconds */
    private $readTimeout = 0;

    /** @var float milliseconds */
    private $pongTimeout = 5000;

    /** @var int */
    private $maxReconnectTimes = 5;

    /** @var Response|null */
    private $handshakeResponse;

    /** @var bool */
    private $reconnecting = false;

    public function __construct($handler)
    {
        if ($handler === null) {
            throw new InvalidArgumentException('handler cannot be null');
        }
        $this->handler = $handler;
    }

    /**
     * @param Request $request
     * @param array   $runtimeObject
     *
     * @return Response
     */
    public function connect($request, $runtimeObject)
    {
        if ($request === null) {
            throw new InvalidArgumentException('request cannot be null');
        }
        if ($runtimeObject === null) {
            throw new InvalidArgumentException('runtimeObject cannot be null');
        }

        $this->request = $request;
        $this->runtimeObject = $runtimeObject;
        $this->updateTimeoutConfig($runtimeObject);
        $this->state = self::STATE_CONNECTING;
        $this->stopped = false;

        $requestURL = WebSocketUtil::buildWebSocketURL($request);
        $parsed = parse_url($requestURL);
        if ($parsed === false) {
            $this->state = self::STATE_DISCONNECTED;
            throw new InvalidArgumentException('invalid websocket url');
        }

        $handshakeTimeout = WebSocketUtil::getWebSocketHandshakeTimeout($runtimeObject);
        $handshakeTimeout = ($handshakeTimeout !== null && $handshakeTimeout > 0) ? $handshakeTimeout : 30000;
        $connectTimeout = isset($runtimeObject['connectTimeout']) && $runtimeObject['connectTimeout'] > 0
            ? $runtimeObject['connectTimeout']
            : 10000;

        $this->loop = Factory::create();
        $socketConnector = $this->buildSocketConnector($this->loop, $runtimeObject, $parsed, $request, $connectTimeout);
        $connector = new WebSocketConnector($this->loop, $socketConnector);

        $headers = [];
        if ($request->headers !== null) {
            foreach ($request->headers as $key => $value) {
                if ($value !== null && strtolower($key) !== 'host' && strtolower($key) !== 'content-length') {
                    $headers[$key] = $value;
                }
            }
        }

        try {
            $timeoutSeconds = max($connectTimeout, $handshakeTimeout) / 1000;
            /** @var WebSocket $conn */
            $conn = Block\await($connector($requestURL, [], $headers), $this->loop, $timeoutSeconds);
            $this->conn = $conn;
            $this->state = self::STATE_CONNECTED;
            $this->setupConnectionHandlers($conn);
            $this->createSessionInfo($conn);
            if ($this->pingInterval > 0) {
                $this->startPingPong();
            }

            $this->handler->afterConnectionEstablished($this->session);

            return $this->handshakeResponse;
        } catch (Exception $e) {
            $this->state = self::STATE_DISCONNECTED;
            $this->cleanupConnection(false);
            throw $e;
        }
    }

    public function disconnect()
    {
        $this->disconnectInternal(1000, 'Normal closure');
    }

    public function reconnect()
    {
        return $this->reconnectInternal(false);
    }

    public function reconnectGracefully()
    {
        return $this->reconnectInternal(true);
    }

    public function isConnected()
    {
        return $this->state === self::STATE_CONNECTED;
    }

    public function sendText($text)
    {
        if (!$this->isConnected()) {
            throw new Exception('not connected');
        }
        if ($this->conn === null) {
            throw new Exception('connection is nil');
        }
        $this->conn->send($text);
        $this->pump();
    }

    public function sendBinary($data)
    {
        if (!$this->isConnected()) {
            throw new Exception('not connected');
        }
        if ($this->conn === null) {
            throw new Exception('connection is nil');
        }
        $frame = new \Ratchet\RFC6455\Messaging\Frame($data, true, \Ratchet\RFC6455\Messaging\Frame::OP_BINARY);
        $this->conn->send($frame);
        $this->pump();
    }

    public function pump($timeoutMs = 100)
    {
        if ($this->loop === null || $this->stopped) {
            return;
        }
        if ($timeoutMs <= 0) {
            $timeoutMs = 100;
        }

        $stopTimer = $this->loop->addTimer($timeoutMs / 1000, function () {
            $this->loop->stop();
        });
        try {
            $this->loop->run();
        } finally {
            $this->loop->cancelTimer($stopTimer);
        }
    }

    public function getSessionInfo()
    {
        return $this->session;
    }

    public function close()
    {
        $this->disconnectInternal(1000, 'Client closed');
    }

    /**
     * @param array $runtimeObject
     */
    private function updateTimeoutConfig($runtimeObject)
    {
        $pingInterval = WebSocketUtil::getWebSocketPingInterval($runtimeObject);
        $this->pingInterval = ($pingInterval !== null && $pingInterval > 0) ? $pingInterval : 30000;

        $reconnectInterval = WebSocketUtil::getWebSocketReconnectInterval($runtimeObject);
        $this->reconnectInterval = ($reconnectInterval !== null && $reconnectInterval > 0) ? $reconnectInterval : 5000;

        $writeTimeout = WebSocketUtil::getWebSocketWriteTimeout($runtimeObject);
        $this->writeTimeout = ($writeTimeout !== null && $writeTimeout > 0) ? $writeTimeout : 30000;

        $readTimeout = isset($runtimeObject['readTimeout']) ? $runtimeObject['readTimeout'] : 0;
        $this->readTimeout = ($readTimeout !== null && $readTimeout > 0) ? $readTimeout : 0;

        $pongTimeout = WebSocketUtil::getWebSocketPongTimeout($runtimeObject);
        $this->pongTimeout = ($pongTimeout !== null && $pongTimeout > 0) ? $pongTimeout : 5000;

        $maxReconnectTimes = WebSocketUtil::getWebSocketMaxReconnectTimes($runtimeObject);
        $this->maxReconnectTimes = ($maxReconnectTimes !== null && $maxReconnectTimes > 0) ? $maxReconnectTimes : 5;
    }

    private function buildSocketConnector($loop, $runtimeObject, $parsed, $request, $connectTimeout)
    {
        $options = [
            'timeout' => $connectTimeout / 1000,
        ];

        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'ws';
        if ($scheme === 'wss' || $scheme === 'https') {
            $ignoreSSL = !empty($runtimeObject['ignoreSSL']);
            $tlsOptions = [
                'verify_peer' => !$ignoreSSL,
                'verify_peer_name' => !$ignoreSSL,
            ];
            if (!empty($runtimeObject['ca'])) {
                $tlsOptions['cafile'] = $this->writeTempPem($runtimeObject['ca'], 'ca');
            }
            if (!empty($runtimeObject['cert']) && !empty($runtimeObject['key'])) {
                $tlsOptions['local_cert'] = $this->writeTempPem($runtimeObject['cert'], 'cert');
                $tlsOptions['local_pk'] = $this->writeTempPem($runtimeObject['key'], 'key');
            }
            $options['tls'] = $tlsOptions;
        }

        $this->configureProxy($options, $parsed, $runtimeObject, $request);

        return new SocketConnector($loop, $options);
    }

    private function configureProxy(&$options, $parsed, $runtimeObject, $request)
    {
        if (!empty($runtimeObject['socks5Proxy'])) {
            return;
        }

        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'ws';
        $host = isset($parsed['host']) ? $parsed['host'] : '';
        $noProxy = isset($runtimeObject['noProxy']) ? $runtimeObject['noProxy'] : '';
        if ($noProxy !== '') {
            foreach (explode(',', $noProxy) as $noProxyHost) {
                if (trim($noProxyHost) === $host) {
                    return;
                }
            }
        }

        $proxyURL = null;
        if ($scheme === 'wss' || $scheme === 'https') {
            $proxyURL = isset($runtimeObject['httpsProxy']) ? $runtimeObject['httpsProxy'] : null;
        } else {
            $proxyURL = isset($runtimeObject['httpProxy']) ? $runtimeObject['httpProxy'] : null;
        }

        if ($proxyURL !== null && $proxyURL !== '') {
            $options['tcp']['proxy'] = $proxyURL;
            $proxyParts = parse_url($proxyURL);
            if (isset($proxyParts['user'])) {
                $password = isset($proxyParts['pass']) ? $proxyParts['pass'] : '';
                $auth = base64_encode($proxyParts['user'] . ':' . $password);
                if ($request->headers === null) {
                    $request->headers = [];
                }
                $request->headers['Proxy-Authorization'] = 'Basic ' . $auth;
            }
        }
    }

    private function writeTempPem($content, $prefix)
    {
        $path = sys_get_temp_dir() . '/dara-ws-' . $prefix . '-' . uniqid() . '.pem';
        file_put_contents($path, $content);

        return $path;
    }

    private function setupConnectionHandlers(WebSocket $conn)
    {
        $response = $conn->response;
        $headers = [];
        foreach ($response->getHeaders() as $key => $values) {
            $headers[$key] = $values;
        }
        $this->handshakeResponse = WebSocketUtil::newWebSocketResponse(
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $headers
        );

        $conn->on('message', function ($msg) {
            if ($this->stopped) {
                return;
            }
            $payload = $msg instanceof \Ratchet\RFC6455\Messaging\MessageInterface ? $msg->getPayload() : (string) $msg;
            $isBinary = $msg instanceof \Ratchet\RFC6455\Messaging\MessageInterface ? $msg->isBinary() : false;
            $msg = new WebSocketMessage(
                $isBinary ? WebSocketMessageType::BINARY : WebSocketMessageType::TEXT,
                $payload,
                [],
                microtime(true)
            );
            if ($this->session === null) {
                return;
            }
            try {
                $this->handler->handleRawMessage($this->session, $msg);
            } catch (Exception $e) {
                $this->handler->handleError($this->session, $e);
            }
        });

        $conn->on('pong', function () {
            $this->pongReceived = true;
        });

        $conn->on('close', function ($code = null, $reason = null) {
            if ($this->stopped) {
                return;
            }
            if ($this->session !== null) {
                $this->handler->handleError($this->session, new Exception((string) $reason));
            }
            if (!$this->stopped && $this->shouldAutoReconnect()) {
                $this->scheduleReconnect(false);
            }
        });

        $conn->on('error', function (Exception $e) {
            if ($this->session !== null) {
                $this->handler->handleError($this->session, $e);
            }
            if (!$this->stopped && $this->shouldAutoReconnect()) {
                $this->scheduleReconnect(false);
            }
        });
    }

    private function createSessionInfo(WebSocket $conn)
    {
        $response = $conn->response;
        $sessionID = '';
        $requestID = '';
        foreach ($response->getHeaders() as $key => $values) {
            $lower = strtolower($key);
            if ($lower === 'x-acs-ws-session-id' && !empty($values)) {
                $sessionID = $values[0];
            }
            if ($lower === 'x-acs-request-id' && !empty($values)) {
                $requestID = $values[0];
            }
        }
        if ($sessionID === '') {
            $sessionID = WebSocketUtil::generateSessionID();
        }

        $remote = method_exists($conn, 'getRemoteAddress') ? $conn->getRemoteAddress() : '';
        $local = method_exists($conn, 'getLocalAddress') ? $conn->getLocalAddress() : '';

        $this->session = new WebSocketSessionInfo(
            $sessionID,
            $requestID,
            microtime(true),
            (string) $remote,
            (string) $local,
            []
        );
    }

    private function startPingPong()
    {
        if ($this->pingInterval <= 0 || $this->loop === null) {
            return;
        }

        $intervalSeconds = $this->pingInterval / 1000;
        $this->pingTimer = $this->loop->addPeriodicTimer($intervalSeconds, function () {
            if ($this->stopped || $this->conn === null) {
                return;
            }
            $this->pongReceived = false;
            try {
                $frame = new \Ratchet\RFC6455\Messaging\Frame('', true, \Ratchet\RFC6455\Messaging\Frame::OP_PING);
                $this->conn->send($frame);
            } catch (Exception $e) {
                if ($this->session !== null) {
                    $this->handler->handleError($this->session, $e);
                }

                return;
            }

            $this->loop->addTimer($this->pongTimeout / 1000, function () {
                if ($this->stopped) {
                    return;
                }
                if (!$this->pongReceived && $this->shouldAutoReconnect()) {
                    $this->scheduleReconnect(false);
                }
            });
        });
    }

    private function stopPingPong()
    {
        if ($this->pingTimer !== null && $this->loop !== null) {
            $this->loop->cancelTimer($this->pingTimer);
            $this->pingTimer = null;
        }
    }

    private function shouldAutoReconnect()
    {
        $enabled = WebSocketUtil::getWebSocketEnableReconnect($this->runtimeObject);

        return $enabled === true || $enabled === 1 || $enabled === '1';
    }

    private function scheduleReconnect($graceful)
    {
        if ($this->reconnecting) {
            return;
        }
        $this->reconnecting = true;
        if ($this->loop !== null) {
            $this->loop->addTimer(0.001, function () use ($graceful) {
                try {
                    $this->reconnectInternal($graceful);
                } catch (Exception $e) {
                    if ($this->session !== null) {
                        $this->handler->handleError($this->session, $e);
                    }
                } finally {
                    $this->reconnecting = false;
                }
            });
        }
    }

    private function reconnectInternal($graceful)
    {
        if (!$graceful && $this->isConnected()) {
            throw new Exception('already connected');
        }

        if (!$this->shouldAutoReconnect()) {
            throw new Exception('reconnect is disabled');
        }

        if ($this->reconnectCount >= $this->maxReconnectTimes) {
            throw new Exception('max reconnect times reached: ' . $this->maxReconnectTimes);
        }

        $previousSessionID = '';
        if ($graceful) {
            if ($this->session !== null && $this->session->sessionID !== '') {
                $previousSessionID = $this->session->sessionID;
            } else {
                throw new Exception('graceful reconnection requires existing session ID');
            }
        }

        $this->cleanupResources();

        $this->stopped = false;
        $this->reconnectCount++;

        if ($this->request === null) {
            throw new Exception('request is nil, cannot reconnect');
        }

        if ($graceful && $previousSessionID !== '') {
            if ($this->request->headers === null) {
                $this->request->headers = [];
            }
            $this->request->headers['X-Acs-Ws-Session-Id'] = $previousSessionID;
        } elseif ($this->request->headers !== null) {
            unset($this->request->headers['X-Acs-Ws-Session-Id']);
        }

        usleep((int) ($this->reconnectInterval * 1000));

        $response = $this->connect($this->request, $this->runtimeObject);
        $this->reconnectCount = 0;

        return $response;
    }

    private function cleanupResources()
    {
        $this->state = self::STATE_DISCONNECTING;
        $this->stopPingPong();
        $this->stopped = true;
        $this->cleanupConnection(false);
        $this->session = null;
        $this->state = self::STATE_DISCONNECTED;
    }

    private function disconnectInternal($code, $reason)
    {
        if ($this->state === self::STATE_DISCONNECTED && $this->loop === null) {
            return;
        }

        $this->state = self::STATE_DISCONNECTING;
        $this->stopPingPong();
        $this->stopped = true;

        if ($this->session !== null) {
            $this->handler->afterConnectionClosed($this->session, $code, $reason);
        }

        $this->cleanupConnection(true);
        $this->session = null;
        $this->state = self::STATE_DISCONNECTED;
    }

    private function cleanupConnection($sendClose)
    {
        if ($this->conn !== null) {
            try {
                if ($sendClose) {
                    $this->conn->close();
                }
            } catch (Exception $e) {
                // ignore close errors
            }
            $this->conn = null;
        }

        if ($this->loop !== null) {
            try {
                $this->loop->stop();
            } catch (Exception $e) {
                // ignore
            }
        }

        $this->loop = null;
    }
}

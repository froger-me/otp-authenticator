<?php

namespace AlibabaCloud\Dara;

use GuzzleHttp\Psr7\Request as PsrRequest;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

/**
 * Class Request.
 */
class Request extends PsrRequest
{
    /**
     * @var string
     */
    public $protocol = 'https';

    /**
     * @var string
     */
    public $pathname = '/';

    /**
     * @var array
     */
    public $headers = [];

    /**
     * @var array
     */
    public $query = [];

    /**
     * @var string
     */
    public $body;

    /**
     * @var int
     */
    public $port;

    public $method;

    public function __construct($method = 'GET', $uri = '', array $headers = [], $body = null, $version = '1.1')
    {
        parent::__construct($method, $uri, $headers, $body, $version);
        $this->method = $method;
    }

    /**
     * These fields are compatible if you define other fields.
     * Mainly for compatibility situations where the code generator cannot generate set properties.
     *
     * @return PsrRequest
     */
    public function getPsrRequest()
    {
        $this->assertQuery($this->query);

        $request = clone $this;

        $uri = $request->getUri();
        if ($this->query) {
            $uri = $uri->withQuery(http_build_query($this->query));
        }

        if ($this->port) {
            $uri = $uri->withPort($this->port);
        }

        if ($this->protocol) {
            $uri = $uri->withScheme($this->protocol);
        }

        if ($this->pathname) {
            $uri = $uri->withPath($this->pathname);
        }

        if (isset($this->headers['host'])) {
            $uri = self::applyHostHeader($uri, $this->headers['host'], $this->port);
        }

        $request = $request->withUri($uri);
        $request = $request->withMethod($this->method);

        if ('' !== $this->body && null !== $this->body) {
            if ($this->body instanceof StreamInterface) {
                $request = $request->withBody($this->body);
            } else {
                $body = $this->body;
                if (Helper::isBytes($this->body)) {
                    $body = Helper::toString($this->body);
                }
                if (\function_exists('\GuzzleHttp\Psr7\stream_for')) {
                    // @deprecated stream_for will be removed in guzzlehttp/psr7:2.0
                    $request = $request->withBody(\GuzzleHttp\Psr7\stream_for($body));
                } else {
                    $request = $request->withBody(\GuzzleHttp\Psr7\Utils::streamFor($body));
                }
            }
        }

        if ($this->headers) {
            foreach ($this->headers as $key => $value) {
                $request = $request->withHeader($key, $value);
            }
        }

        return $request;
    }

    /**
     * psr7 1.x accepts a Host value that still contains ":port". psr7 2.x
     * rejects that, so the port is applied with withPort() instead.
     *
     * @param \Psr\Http\Message\UriInterface $uri
     * @param mixed                          $hostHeader
     * @param int|null                       $explicitPort
     *
     * @return \Psr\Http\Message\UriInterface
     */
    private static function applyHostHeader($uri, $hostHeader, $explicitPort)
    {
        if (!is_string($hostHeader) || self::psr7MajorVersion() < 2) {
            return $uri->withHost($hostHeader);
        }

        list($host, $port) = self::splitHostHeader($hostHeader);
        $uri = $uri->withHost($host);
        if ($port !== null && !$explicitPort) {
            $uri = $uri->withPort($port);
        }

        return $uri;
    }

    /**
     * @param string $hostHeader
     *
     * @return array
     */
    private static function splitHostHeader($hostHeader)
    {
        if ($hostHeader !== '' && $hostHeader[0] === '[') {
            $end = strpos($hostHeader, ']');
            if ($end !== false) {
                $host = substr($hostHeader, 0, $end + 1);
                $rest = substr($hostHeader, $end + 1);
                if (strlen($rest) > 1 && $rest[0] === ':') {
                    return array($host, (int) substr($rest, 1));
                }

                return array($host, null);
            }
        }

        if (substr_count($hostHeader, ':') === 1) {
            $parts = explode(':', $hostHeader, 2);
            if ($parts[1] !== '' && ctype_digit($parts[1])) {
                return array($parts[0], (int) $parts[1]);
            }
        }

        return array($hostHeader, null);
    }

    /**
     * @return int
     */
    private static function psr7MajorVersion()
    {
        static $major = null;
        if ($major !== null) {
            return $major;
        }

        $detected = null;
        if (class_exists('Composer\\InstalledVersions')) {
            try {
                $version = \Composer\InstalledVersions::getVersion('guzzlehttp/psr7');
                if (is_string($version) && preg_match('/^v?(\d+)/', $version, $match)) {
                    $detected = (int) $match[1];
                }
            } catch (\Exception $e) {
                $detected = null;
            }
        }

        if (!$detected) {
            // psr7 2.x requires PHP >= 7.2.5 and adds a return type on Utils::streamFor().
            // stream_for() cannot be used here: this package defines it on 2.x as well.
            if (PHP_VERSION_ID < 70205 || !class_exists('GuzzleHttp\\Psr7\\Utils')) {
                $detected = 1;
            } else {
                $method = new \ReflectionMethod('GuzzleHttp\\Psr7\\Utils', 'streamFor');
                $detected = $method->hasReturnType() ? 2 : 1;
            }
        }

        $major = $detected;

        return $major;
    }

    /**
     * @param array $query
     */
    private function assertQuery($query)
    {
        if (!\is_array($query) && $query !== null) {
            throw new InvalidArgumentException('Query must be array.');
        }
    }
}

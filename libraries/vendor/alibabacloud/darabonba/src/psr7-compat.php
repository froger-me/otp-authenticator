<?php

namespace GuzzleHttp\Psr7;

// guzzlehttp/psr7 2.0 removed these 1.x functions. Define them only when the
// installed psr7 does not, and forward to the 1.7+/2.x replacements.
// ratchet/pawl 0.4.0 still calls uri_for(), str() and parse_response().
if (!function_exists('GuzzleHttp\\Psr7\\stream_for')) {
    function stream_for($resource = '', array $options = [])
    {
        return Utils::streamFor($resource, $options);
    }
}

if (!function_exists('GuzzleHttp\\Psr7\\uri_for')) {
    function uri_for($uri)
    {
        return Utils::uriFor($uri);
    }
}

if (!function_exists('GuzzleHttp\\Psr7\\str')) {
    function str(\Psr\Http\Message\MessageInterface $message)
    {
        return Message::toString($message);
    }
}

if (!function_exists('GuzzleHttp\\Psr7\\parse_response')) {
    function parse_response($message)
    {
        return Message::parseResponse($message);
    }
}

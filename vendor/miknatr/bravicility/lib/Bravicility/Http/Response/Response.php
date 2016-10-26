<?php

namespace Bravicility\Http\Response;

use Bravicility\Http\Request;

class Response
{
    public function __construct($statusCode, $content = '')
    {
        $this->setStatusCode($statusCode);
        $this->setContent($content);
    }

    public function send()
    {
        $statusCode = $this->getStatusCode();
        header('HTTP/1.1 ' . $statusCode .' ' . static::$statusCodes[$statusCode]);

        foreach ($this->getCookies() as $cookieArgs) {
            setcookie(
                $cookieArgs['name'],
                $cookieArgs['value'],
                $cookieArgs['expire'],
                $cookieArgs['path'],
                $cookieArgs['domain'],
                $cookieArgs['secure'],
                $cookieArgs['httpOnly']
            );
        }

        foreach ($this->getHeaders() as $header) {
            header($header);
        }

        echo $this->getContent();
    }

    public function isCacheable($statusCode, array $headers)
    {
        return $this->getStatusCode() == $statusCode
            && $this->getCookies() === array()
            && count(array_diff($this->getHeaders(), $headers)) == 0
        ;
    }


    //
    // CONTENT
    //

    protected $content = '';

    public function getContent()
    {
        return $this->content;
    }

    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }


    //
    // HEADERS
    //

    protected $headers = array();

    public function addHeader($header)
    {
        $this->headers[] = $header;
        return $this;
    }

    public function requireBasicAuth($description)
    {
        $this->setStatusCode(401);
        $this->addHeader('WWW-Authenticate: Basic realm="' . urlencode($description) . '"');
        return $this;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function allowAnyCrossDomainUrl(Request $request)
    {
        $this->setAllowedCrossDomainUrls($request, array($request->header('Origin')));
    }

    public function setAllowedCrossDomainUrls(Request $request, array $urls)
    {
        $origin = null;

        if (count($urls) > 1) {
            // we can only set one allowed origin URL
            // if many are given, we need to find the current one
            $requestOrigin = $request->header('Origin');
            if ($requestOrigin) {
                // the docs say this will not have any path info, but we don't trust those bastards
                if (substr($requestOrigin, -1) != '/') {
                    $requestOrigin .= '/';
                }

                foreach ($urls as $allowedOrigin) {
                    $allowedOriginWithSlash = $allowedOrigin;
                    if (substr($allowedOriginWithSlash, -1) != '/') {
                        $allowedOriginWithSlash .= '/';
                    }
                    if (strpos($requestOrigin, $allowedOriginWithSlash) === 0) {
                        $origin = $allowedOrigin;
                        break;
                    }
                }
            }
        }

        if (!$origin) {
            // only one URL given, or none matches the request
            // fallback to first allowed origin
            $origin = reset($urls);
        }

        $this->addHeader('Access-Control-Allow-Credentials: true');
        $this->addHeader('Access-Control-Allow-Origin: ' . $origin);
    }

    public function disableCaching()
    {
        $this->addHeader('Cache-Control: no-cache, no-store, must-revalidate');
        $this->addHeader('Pragma: no-cache');
        $this->addHeader('Expires: 0');
    }


    //
    // COOKIES
    //

    protected $cookies = array();
    public function setCookie($name, $value = null, $expire = 0 , $path = '/', $domain = null, $secure = false, $httpOnly = false)
    {
        $this->cookies[] = array(
            'name'     => $name,
            'value'    => $value,
            'expire'   => $expire,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httpOnly' => $httpOnly,
        );

        return $this;
    }

    public function removeCookie($name, $path = '/', $domain = null, $secure = false, $httpOnly = false)
    {
        $this->setCookie($name, null, 0, $path, $domain, $secure, $httpOnly);

        return $this;
    }

    public function getCookies()
    {
        return $this->cookies;
    }


    //
    // STATUS CODES
    //

    protected $statusCode;

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function setStatusCode($statusCode)
    {
        if (!isset(static::$statusCodes[$statusCode])) {
            throw new \Exception("Status code '{$statusCode}' is not allowed");
        }
        $this->statusCode = $statusCode;
        return $this;
    }

    protected static $statusCodes = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',            // RFC2518
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',          // RFC4918
        208 => 'Already Reported',      // RFC5842
        226 => 'IM Used',               // RFC3229
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Reserved',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',    // RFC-reschke-http-status-308-07
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',                                               // RFC2324
        422 => 'Unprocessable Entity',                                        // RFC4918
        423 => 'Locked',                                                      // RFC4918
        424 => 'Failed Dependency',                                           // RFC4918
        425 => 'Reserved for WebDAV advanced collections expired proposal',   // RFC2817
        426 => 'Upgrade Required',                                            // RFC2817
        428 => 'Precondition Required',                                       // RFC6585
        429 => 'Too Many Requests',                                           // RFC6585
        431 => 'Request Header Fields Too Large',                             // RFC6585
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates (Experimental)',                      // RFC2295
        507 => 'Insufficient Storage',                                        // RFC4918
        508 => 'Loop Detected',                                               // RFC5842
        510 => 'Not Extended',                                                // RFC2774
        511 => 'Network Authentication Required',                             // RFC6585
    );
}

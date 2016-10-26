<?php

namespace Bravicility\Http;

class Request
{
    /**
     * @return Request
     */
    public static function createFromGlobals()
    {
        return (new static)
            ->setMethod($_SERVER['REQUEST_METHOD'])
            ->setUrlPath($_SERVER['REQUEST_URI'])
            ->setGet($_GET)
            ->setPost($_POST)
            ->setFiles(static::getUploadedFilesFromPhp($_FILES))
            ->setCookies($_COOKIE)
            ->setServer($_SERVER)
            ->setRawBody(function () {
                return file_get_contents('php://input');
            })
        ;
    }

    public static function getUploadedFilesFromPhp(array $phpFiles)
    {
        $files = array();
        foreach ($phpFiles as $name => $phpFile) {
            // TODO <input type="file" name="files[]" />
            $file = UploadedFile::createFromPhpUpload($phpFile);
            if ($file) {
                $files[$name] = $file;
            }
        }
        return $files;
    }


    //
    // LOCATION
    //

    protected $method;
    protected $urlPath;

    // TODO название
    public function getLocation()
    {
        $location = $this->getUrlPath();
        if (!empty($this->get)) {
            $location .= '?' . http_build_query($this->get);
        }
        return $location;
    }

    public function getUrlPath()
    {
        return $this->urlPath;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getScheme()
    {
        $https = $this->server('HTTPS');
        if ($https === 'off') {
            $https = false;
        }
        return $https ? 'https' : 'http';
    }

    public function getHost()
    {
        return $this->header('Host');
    }

    public function setUrlPath($urlPath)
    {
        $this->urlPath = explode('?', $urlPath, 2)[0];
        return $this;
    }

    public function setMethod($method)
    {
        $this->method = strtoupper($method);
        return $this;
    }


    //
    // GET
    //

    protected $get = array();

    public function get($name, $default = null)
    {
        return isset($this->get[$name]) ? $this->get[$name] : $default;
    }

    public function allGet()
    {
        return $this->get;
    }

    public function setGet(array $get)
    {
        $this->get = $get;
        return $this;
    }


    //
    // POST
    //

    protected $post = array();

    public function post($name, $default = null)
    {
        return isset($this->post[$name]) ? $this->post[$name] : $default;
    }

    public function allPost()
    {
        return $this->post;
    }

    public function filterPostByFieldList(array $fieldList)
    {
        $r = array();
        foreach ($fieldList as $fieldName) {
            $r[$fieldName] = $this->post($fieldName);
        }
        return $r;
    }

    public function setPost(array $post)
    {
        $this->post = $post;
        return $this;
    }


    //
    // FILES
    //

    /**
     * @var UploadedFile[]
     */
    protected $files = array();

    /**
     * @param string $name
     * @param null $default
     * @return UploadedFile|null
     */
    public function file($name, $default = null)
    {
        return isset($this->files[$name]) ? $this->files[$name] : $default;
    }

    /**
     * @return UploadedFile[]
     */
    public function allFiles()
    {
        return $this->files;
    }

    /**
     * @param UploadedFile[] $files
     * @return $this
     */
    public function setFiles(array $files)
    {
        $this->files = $files;
        return $this;
    }


    //
    // COOKIES
    //

    protected $cookies = array();

    public function cookie($name, $default = null)
    {
        return isset($this->cookies[$name]) ? $this->cookies[$name] : $default;
    }

    public function allCookies()
    {
        return $this->cookies;
    }

    public function setCookies(array $cookies)
    {
        $this->cookies = $cookies;
        return $this;
    }


    //
    // OPTIONS
    //

    protected $options = array();

    public function option($name, $default = null)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $default;
    }

    public function allOptions()
    {
        return $this->options;
    }

    public function addOption($name, $value)
    {
        $this->options[$name] = $value;
        return $this;
    }

    public function addOptions(array $options)
    {
        foreach ($options as $name => $value) {
            $this->options[$name] = $value;
        }
        return $this;
    }

    public function setOptions(array $options)
    {
        $this->options = $options;
        return $this;
    }


    //
    // SERVER
    //

    protected $server  = array();

    public function server($name, $default = null)
    {
        return isset($this->server[$name]) ? $this->server[$name] : $default;
    }

    public function allServer()
    {
        return $this->server;
    }

    public function setServer(array $server)
    {
        $this->server = $server;
        if (!$this->headers) {
            $this->setHeaders(function () {
                $headers = array();
                foreach ($this->allServer() as $k => $v) {
                    if (substr($k, 0, 5) !== 'HTTP_') {
                        continue;
                    }

                    $name = str_replace('_', '-', substr($k, 5));
                    $headers[$name] = $v;
                }
                return $headers;
            });
        }
        return $this;
    }


    //
    // HEADERS
    //

    protected $headers;

    public function header($name, $default = null)
    {
        $name    = strtolower($name);
        $headers = $this->allHeaders();
        return isset($headers[$name]) ? $headers[$name] : $default;
    }

    public function allHeaders()
    {
        if (is_callable($this->headers)) {
            $this->setHeaders(call_user_func($this->headers));
        }
        return $this->headers;
    }

    public function getUserAgent()
    {
        return $this->server('HTTP_USER_AGENT');
    }

    /**
     * @return string full Content-Type header value, e.g. "multipart/form-data; boundary=---------------------------17265797101826147706228211818"
     */
    public function getContentType()
    {
        $header = $this->header('Content-Type');
        if ($header === null) {
            $header = $this->server('HTTP_CONTENT_TYPE');
        }
        return $header;
    }

    /**
     * @return string only the first part, e.g. "application/json" or "multipart/form-data"
     */
    public function getContentTypeId()
    {
        $ct = $this->getContentType();
        if ($ct) {
            return trim(explode(';', $ct, 2)[0]);
        }
        return null;
    }

    /**
     * @param string[]|callable $headers
     * @return $this
     */
    public function setHeaders($headers)
    {
        if (is_array($headers)) {
            $this->headers = array();
            foreach ($headers as $name => $value) {
                $this->headers[strtolower($name)] = $value;
            }
        } else {
            // callable (lazy loading of headers)
            $this->headers = $headers;
        }
        return $this;
    }


    //
    // RAW BODY
    //

    protected $rawBody;

    public function getRawBody()
    {
        if (is_callable($this->rawBody)) {
            $this->rawBody = call_user_func($this->rawBody);
        }
        return $this->rawBody;
    }

    /**
     * @param string|callable $rawBody
     * @return $this
     */
    public function setRawBody($rawBody)
    {
        $this->rawBody = $rawBody;
        return $this;
    }

    public function parseBodyAsJson()
    {
        $arr = json_decode($this->getRawBody(), true);
        return is_array($arr) ? $arr : array();
    }

    public function parseBodyAsUrlEncoded()
    {
        $parsed = array();
        parse_str($this->getRawBody(), $parsed);
        return $parsed;
    }

    public function getSubmittedFormData()
    {
        switch ($this->getMethod()) {
            case 'GET':
                return $this->allGet();

            case 'POST':
                // PHP нам помогает тут попарсить всё
                return array_merge($this->allPost(), $this->allFiles());

            default:
                return $this->parseBodyAsUrlEncoded();
        }
    }


    //
    // CLIENT IP
    //

    protected $trustedProxies = array();

    public function getClientIp()
    {
        $ip = $this->server('REMOTE_ADDR');

        if (empty($this->trustedProxies)) {
            return $ip;
        }

        $forwardedFor = $this->server('X_FORWARDED_FOR');
        if (!$forwardedFor) {
            return $ip;
        }

        $clientIps = array_map('trim', explode(',', $forwardedFor));
        $clientIps[] = $ip;

        $clientIps = array_diff($clientIps, empty($this->trustedProxies) ? array($ip) : $this->trustedProxies);

        return array_pop($clientIps);
    }

    public function setTrustedProxies(array $trustedProxies)
    {
        $this->trustedProxies = $trustedProxies;
    }
}

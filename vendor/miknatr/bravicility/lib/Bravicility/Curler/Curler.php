<?php

namespace Bravicility\Curler;

class Curler
{
    protected $method         = 'GET';
    protected $headers        = array();
    protected $urlParts       = array();
    protected $query          = array();
    protected $requestBody    = '';
    protected $ignoreErrors   = false;
    protected $validateSsl    = false;
    protected $followLocation = true;
    protected $timeout        = 3;


    //
    // BUILDER
    //

    /**
     * @param string $method
     * @return Curler
     */
    public function method($method)
    {
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * @param string $url
     * @return Curler
     */
    public function url($url)
    {
        $this->urlParts = parse_url($url);
        if (!empty($this->urlParts['query'])) {
            $this->query = array();
            parse_str($this->urlParts['query'], $this->query);
            unset($this->urlParts['query']);
        }
        return $this;
    }

    /**
     * @param string $host
     * @return Curler
     */
    public function host($host)
    {
        $this->urlParts['host'] = $host;
        return $this;
    }

    /**
     * @param string $port
     * @return Curler
     */
    public function port($port)
    {
        $this->urlParts['port'] = $port;
        return $this;
    }

    /**
     * @param string $user
     * @param string $pass
     * @return Curler
     */
    public function basicAuth($user, $pass)
    {
        $this->urlParts['user'] = $user;
        $this->urlParts['pass'] = $pass;
        return $this;
    }

    /**
     * @param array $query
     * @return Curler
     */
    public function query(array $query)
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     * @return Curler
     */
    public function queryParam($name, $value)
    {
        $this->query[$name] = $value;
        return $this;
    }

    /**
     * @param array $headers
     * @return Curler
     */
    public function headers(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * @param string $header
     * @return Curler
     */
    public function header($header)
    {
        $this->headers[] = $header;
        return $this;
    }

    /**
     * @param array $data
     * @return Curler
     */
    public function post(array $data)
    {
        $this
            ->method('POST')
            ->header('Content-Type: application/x-www-form-urlencoded')
        ;
        $this->requestBody = http_build_query($data);
        return $this;
    }

    /**
     * @param string $data
     * @return Curler
     */
    public function postRaw($data)
    {
        $this->method('POST');
        $this->requestBody = $data;
        return $this;
    }

    /**
     * @param mixed $data
     * @return Curler
     */
    public function postJson($data)
    {
        $this
            ->method('POST')
            ->header('Content-Type: application/json')
        ;
        $this->requestBody = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $this;
    }

    /**
     * @param array $data
     * @return Curler
     */
    public function put(array $data)
    {
        $this
            ->method('PUT')
            ->header('Content-Type: application/x-www-form-urlencoded')
        ;
        $this->requestBody = http_build_query($data);
        return $this;
    }

    /**
     * @param mixed $data
     * @return Curler
     */
    public function putJson($data)
    {
        $this
            ->method('PUT')
            ->header('Content-Type: application/json')
        ;
        $this->requestBody = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $this;
    }

    /**
     * @return Curler
     */
    public function ignoreErrors()
    {
        $this->ignoreErrors = true;
        return $this;
    }

    /**
     * @return Curler
     */
    public function validateSsl()
    {
        $this->validateSsl = true;
        return $this;
    }

    /**
     * @return Curler
     */
    public function dontFollowLocation()
    {
        $this->followLocation = false;
        return $this;
    }

    /**
     * @param int $seconds
     * @return Curler
     */
    public function timeout($seconds)
    {
        $this->timeout = $seconds;
        return $this;
    }


    //
    // IMPLEMENTATION
    //

    protected function buildUrl()
    {
        $auth = '';
        if (isset($this->urlParts['user'])) {
            $auth = urlencode($this->urlParts['user']);
            if (isset($this->urlParts['pass'])) {
                $auth .= ':' . urlencode($this->urlParts['pass']);
            }
            $auth .= '@';
        }

        return (isset($this->urlParts['scheme']) ? urlencode($this->urlParts['scheme']) : 'http')
            . '://'
            . $auth
            . urlencode($this->urlParts['host'])
            . (isset($this->urlParts['port']) ? ':' . urlencode($this->urlParts['port']) : '')
            . (isset($this->urlParts['path']) ? $this->urlParts['path'] : '')
            . (!empty($this->query) ? '?' . http_build_query($this->query) : '')
        ;
    }

    public function send()
    {
        $url = $this->buildUrl();

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        switch ($this->method) {
            case 'GET':
                break;

            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->requestBody);
                break;

            case 'PUT':
                curl_setopt($ch, CURLOPT_PUT, true);

                $fhPut = fopen('php://memory', 'rw');
                fwrite($fhPut, $this->requestBody);
                rewind($fhPut);
                curl_setopt($ch, CURLOPT_INFILE, $fhPut);
                curl_setopt($ch, CURLOPT_INFILESIZE, strlen($this->requestBody));
                break;

            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
                break;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // exec will return the response body

        if (!$this->validateSsl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        if ($this->followLocation) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // will follow redirects in response
        }

        $response = curl_exec($ch);

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_error($ch)) {
            if (isset($fhPut)) {
                fclose($fhPut);
            }

            if ($this->ignoreErrors) {
                curl_close($ch);
                return new CurlerResponse(0, '');
            }

            throw new CurlerException(curl_error($ch) . ' while ' . $this->method . 'ing ' . $url);
        }

        curl_close($ch);
        if (isset($fhPut)) {
            fclose($fhPut);
        }

        return new CurlerResponse($status, $response);
    }
}

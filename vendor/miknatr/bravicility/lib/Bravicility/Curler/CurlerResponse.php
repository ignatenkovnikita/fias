<?php

namespace Bravicility\Curler;

class CurlerResponse
{
    protected $httpStatus;
    protected $response;

    public function __construct($httpStatus, $response)
    {
        $this->httpStatus = $httpStatus;
        $this->response   = $response;
    }

    public function getStatus()
    {
        return $this->httpStatus;
    }

    public function getBody()
    {
        return $this->response;
    }

    public function getJson()
    {
        return json_decode($this->response, true);
    }
}

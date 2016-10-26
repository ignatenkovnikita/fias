<?php

namespace Bravicility\Http\Response;

class JsonResponse extends Response
{
    public function __construct($statusCode, $json = null)
    {
        parent::__construct($statusCode, json_encode($json));
        $this->addHeader('Content-Type: application/json; charset=UTF-8');
    }
}

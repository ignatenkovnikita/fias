<?php

namespace Bravicility\Http\Response;

class JavaScriptResponse extends Response
{
    public function __construct($statusCode, $source)
    {
        parent::__construct($statusCode, $source);
        $this->addHeader('Content-Type: text/javascript; charset=UTF-8');
    }
}

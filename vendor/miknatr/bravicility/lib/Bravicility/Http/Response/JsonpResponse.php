<?php

namespace Bravicility\Http\Response;

class JsonpResponse extends Response
{
    public function __construct($json, $callback)
    {
        $content = $callback . '(' . json_encode($json) . ')';
        parent::__construct(200, $content);
        $this->addHeader('Content-Type: application/json; charset=UTF-8');
    }
}

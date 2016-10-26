<?php

namespace Bravicility\Http\Response;

class HtmlResponse extends Response
{
    public function __construct($statusCode, $html)
    {
        parent::__construct($statusCode, $html);
        $this->addHeader('Content-Type: text/html; charset=UTF-8');
    }
}

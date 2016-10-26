<?php

namespace Bravicility\Http\Response;

class RedirectResponse extends Response
{
    protected $location;

    public function __construct($location)
    {
        if (!preg_match('#^https?://#', $location) && substr($location, 0, 1) != '/') {
            throw new \LogicException('Redirect location must be absolute or begin with "/", "' . $location . '" is not');
        }

        parent::__construct(302);
        $this->location = $location;
    }

    public function send()
    {
        $location = $this->location;

        if (!preg_match('#^https?://#', $location)) {
            $location = $this->getSchema() . '://' . $_SERVER['HTTP_HOST'] . $location;
        }

        $this->addHeader('Location: ' . $location);

        parent::send();
    }

    protected function getSchema()
    {
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
            return 'https';
        }

        return 'http';
    }
}

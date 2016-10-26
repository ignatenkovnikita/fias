<?php

use Bravicility\Router\RouteProvider;

class RouteProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider patternsProvider
     */
    public function testHasOverlappingPatterns($isOverlapExpected, $patterns)
    {
        $list = preg_split('/\s+/', $patterns);

        $overlappingPatterns = RouteProvider::findOverlappingPatterns($list);

        $this->assertEquals(
            $isOverlapExpected ? $list : array(),
            $overlappingPatterns,
            $isOverlapExpected ? 'Overlap is not detected' : 'Incorrect overlap detected'
        );
    }

    public function patternsProvider()
    {
        // requirements:
        // leading slash
        // no trailing slash
        // no double-slash inside
        // {var} can only go between two slashes
        return array(
            array(false, '/a  /b'),
            array(true,  '/a  /{var}'),

            array(false, '/{var}       /{var}/b'),
            array(false, '/{var}/one   /{var}/two'),
            array(true,  '/{var}/same  /{var}/same'),
            array(true,  '/fixed/same  /{var}/same'),

            array(true,  '/api/{apiVersion}/{modelName}  /api/{apiVersion}/whoami'),
        );
    }
}

<?php

class FunctionsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider phpdocTagsProvider
     */
    public function testGetPhpdocTags($expected, $phpdoc)
    {
        $this->assertEquals($expected, getPhpdocTags($phpdoc));
    }

    /**
     * @blah arg1  arg2%^#$^&#(
     * @return array
     */
    public function phpdocTagsProvider()
    {
        return array(
            array(
                array(),
                '',
            ),
            array(
                array(),
                '/** */',
            ),
            array(
                array(
                    array('name' => 'blah',   'args' => array('arg1', 'arg2%^#$^&#('), 'string' => '@blah arg1  arg2%^#$^&#('),
                    array('name' => 'return', 'args' => array('array'), 'string' => '@return array'),
                ),
                (new \ReflectionMethod(__CLASS__, 'phpdocTagsProvider'))->getDocComment(),
            ),
        );
    }
}

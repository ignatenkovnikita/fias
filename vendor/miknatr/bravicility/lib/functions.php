<?php

function dt($ts = null)
{
    if ($ts === null) {
        $ts = time();
    }

    return date('Y-m-d H:i:s', $ts);
}

function dtz($ts = null)
{
    if ($ts === null) {
        $ts = time();
    }

    return date('c', $ts);
}

function dt2dtz($dt)
{
    return date('c', strtotime($dt));
}

function formatMoney($amount)
{
    $amount = (float) str_replace(',', '.', $amount);
    return number_format($amount, 2, '.', '');
}

function phoneToInt($phone)
{
    $phone = preg_replace('/\D+/', '', $phone);
    $phone = preg_replace('/^8(\d{10})$/', '7$1', $phone);
    return $phone;
}

function formatPhone($phone)
{
    $clean = phoneToInt($phone);
    // ru
    $formatted = preg_replace('/^7(\d\d\d)(\d\d\d)(\d\d)(\d\d)$/', '+7 $1 $2-$3-$4', $clean);
    $formatted = preg_replace('/^8(\d\d\d)(\d\d\d)(\d\d)(\d\d)$/', '+7 $1 $2-$3-$4', $formatted);
    // ua
    $formatted = preg_replace('/^380(\d\d)(\d\d\d)(\d\d)(\d\d)$/', '+380 $1 $2-$3-$4', $formatted);
    // az
    $formatted = preg_replace('/^994(\d\d\d)(\d\d\d\d\d)$/', '+994 $1 $2', $formatted);               // 994 xxx xxxxx      8 digits
    $formatted = preg_replace('/^994(\d\d)(\d\d\d)(\d\d)(\d\d)$/', '+994 $1 $2-$3-$4', $formatted);   // 994 xx xxx-xx-xx   9 digits
    $formatted = preg_replace('/^994(\d\d\d)(\d\d\d)(\d\d)(\d\d)$/', '+994 $1 $2-$3-$4', $formatted); // 994 xxx xxx-xx-xx 10 digits
    // am
    $formatted = preg_replace('/^374(9\d|10)(\d\d\d\d\d\d)$/', '+374 $1 $2', $formatted);
    $formatted = preg_replace('/^374(\d\d\d)(\d\d\d\d\d)$/', '+374 $1 $2', $formatted);

    // if we could not recognize the phone, let's return the original in hopes it had any formatting
    return $clean == $formatted ? $phone : $formatted;
}

function getPhpdocTags($phpdoc)
{
    preg_match_all(
        '{
            ^
            \s* \* \s+
            @ (\S+) ([^\n]*)
            $
        }xm',
        $phpdoc,
        $matches,
        PREG_SET_ORDER
    );

    $tags = array();
    foreach ($matches as $match) {
        // TODO поддержка закавыченных строк
        $args = preg_split('/\s+/', $match[2], -1, PREG_SPLIT_NO_EMPTY);

        $tags[] = array(
            'name'   => $match[1],
            'args'   => $args,
            'string' => '@' . $match[1] . $match[2],
        );
    }

    return $tags;
}

function getFilesRecursively($dir, $includeDirs = false)
{
    $files = array();
    $directories = array($dir);

    while (sizeof($directories)) {
        $curDir = array_pop($directories);
        $handle = opendir($curDir);
        while (false !== ($file = readdir($handle))) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $file  = $curDir . '/' . $file;
            if (is_dir($file)) {
                if ($includeDirs) {
                    $files[] = $file;
                }
                $directory_path = $file;
                array_push($directories, $directory_path);
            } elseif (is_file($file)) {
                $files[] = $file;
            }
        }
        closedir($handle);
    }

    return $files;
}

/**
 * @param $methodTypeUrl 'GET http://ya.ru/preved' or 'POST json|form http://ya.ru/poka'
 * @param string $postContent
 * @return array
 * @throws Exception
 */
function httpRequest($methodTypeUrl, $postContent = '')
{
    $parts = explode(' ', $methodTypeUrl);
    if (count($parts) == 2) {
        $method = $parts[0];
        $type   = null;
        $url    = $parts[1];
    } elseif (count($parts) == 3) {
        $method = $parts[0];
        $type   = $parts[1];
        $url    = $parts[2];
    } else {
        throw new \Exception("Unexpected argument $methodTypeUrl");
    }

    $headers = array();

    switch ($type) {
        case null:
            break;
        case 'json':
            $headers[] = 'Content-Type: application/json';
            break;
        case 'form':
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            break;
        default:
            throw new \Exception("Unexpected data type argument $method");
    }

    $ch = curl_init($url);

    switch ($method) {
        case 'GET':
            break;
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postContent);
            break;
        case 'PUT':
            curl_setopt($ch, CURLOPT_PUT, true);

            $fhPut = fopen('php://memory', 'rw');
            fwrite($fhPut, $postContent);
            rewind($fhPut);
            curl_setopt($ch, CURLOPT_INFILE, $fhPut);
            curl_setopt($ch, CURLOPT_INFILESIZE, strlen($postContent));
            //curl_setopt($ch, CURLOPT_HTTPHEADER, Array('Expect: '));
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
        default:
            throw new \Exception("Unexpected method argument $method");
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, false); // exclude the header in the output
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // exec will return the response body
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // will follow redirects in response
    //curl_setopt($ch, CURLOPT_VERBOSE, true);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $error = curl_error($ch);
    if ($error) {
        curl_close($ch);
        throw new \Exception($error);
    }

    curl_close($ch);
    if (isset($fhPut)) {
        fclose($fhPut);
    }

    return array($status, $response);
}

function p()
{
    if (!defined('STDIN')) {
        if (!headers_sent()) {
            header("HTTP/1.1 500 Debug");
            header("Content-Type: text/html; charset=UTF-8");
        }
        $pre1 = '<pre>';
        $pre2 = '</pre>';
    } else {
        $pre1 = '';
        $pre2 = "\n";
    }

    $args = func_get_args();
    if (count($args) == 0) {
        echo $pre1 . 'flag!' . $pre2;
    } else {
        foreach ($args as $v) {
            echo $pre1;
            if (is_bool($v)) {
                echo ($v ? "true" : "false");
            } elseif (is_null($v)) {
                echo "NULL";
            } else {
                echo ((is_string($v) and $v == '') ? "empty string" : print_r($v, true));
            }
            echo $pre2;
        }
    }

    if (defined('STDIN')) {
        echo "\n";
    }
}

function pd()
{
    $args = func_get_args();
    call_user_func_array('p', $args);
    die((defined('STDIN') ? "\n\n" : '<br><br>') . 'DIE');
}

/**
 * Special format for lists:
 * 'cars' => array(
 *     '__list'      => true,
 *     '__item_name' => 'car',
 *     '__items'     => array($item1, $item2...),
 * ),
 * @param array $data
 * @param string $encoding
 * @return string
 */
function buildXmlFromArray(array $data, $encoding = 'utf-8')
{
    $xml = '<?xml version="1.0" encoding="' . $encoding . '"?>';
    $xml .= buildXmlFromArrayWithoutPrefix($data);
    return $xml;
}

function buildXmlFromArrayWithoutPrefix(array $data)
{
    $xml = '';

    foreach ($data as $k => $v) {
        if (is_array($v)) {
            $xml .= "<$k>";
            if (isset($v['__list'])) {
                foreach ($v['__items'] as $vItem) {
                    $xml .= "<" . $v['__item_name'] . ">";
                    if (!is_array($vItem)) {
                        throw new \Exception('Invalid data');
                    }
                    $xml .= buildXmlFromArrayWithoutPrefix($vItem);
                    $xml .= "</" . $v['__item_name'] . ">";
                }
            } else {
                $xml .= buildXmlFromArrayWithoutPrefix($v);
            }
            $xml .= "</$k>";
        } else {
            $xml .= "<$k>" . htmlentities($v, ENT_XML1) . "</$k>";
        }
    }

    return $xml;
}

// snake_case -> camelCase
function snakeToCamelCase($string)
{
    return preg_replace_callback(
        '/_(.)/',
        function ($m) {
            return strtoupper($m[1]);
        },
        $string
    );
}

function camelToSnakeCase($string)
{
    // camelCase -> snake_case
    // WARNING: if the first letter is capital, we'll make ClassName -> _class_name
    return preg_replace_callback(
        '/[A-Z]/',
        function ($m) {
            return '_'.strtolower($m[0]);
        },
        $string
    );
}

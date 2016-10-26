<?php

namespace Bravicility\Failure;

use Bravicility\Http\Response\TextResponse;

class FailureInfoResponse extends TextResponse
{
    public function __construct(array $error)
    {
        parent::__construct(500, $this->drawError($error));
    }

    public function send()
    {
        if (headers_sent()) {
            // prevent the 'headers already sent' error overtaking the actual error
            echo $this->getContent();
        } else {
            parent::send();
        }
    }

    protected function drawError(array $error)
    {
        return
            "\n\n"
            . $this->showSeverity($error) . ': ' . $error['message'] . "\n"
            . $this->dumpTrace($error)
        ;
    }

    protected function showSeverity($error)
    {
        static $types = array(
            E_ERROR             => 'E_ERROR',
            E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
            E_STRICT            => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED        => 'E_DEPRECATED',
        );

        if (!empty($error['exception'])) {
            return $error['exception'];
        }

        if (isset($types[$error['type']])) {
            return $types[$error['type']];
        }

        return 'PHP error ' . $error['type'];
    }

    protected function dumpTrace(array $error, $position = 0)
    {
        $text = '';
        if (!empty($error['exception_wrapped'])) {
            $i = count($error['trace']) - 1;
            foreach (array_reverse($error['exception_wrapped']['trace']) as $trace) {
                if ($error['trace'][$i] != $trace) {
                    break; // prevent non-consecutive dupes from matching
                }
                $error['trace'][$i]['is_dupe'] = true;

                $i--;
            }
        }

        foreach ($error['trace'] as $trace) {
            if (empty($trace['is_dupe'])) {
                $text .= "\n";
                $text .= ' ' . ++$position . '. ' . $this->fileLine($trace) . "\n";
                $text .= '    ' . $this->showTraceElement($trace, $error) . "\n";
            }
        }

        if (!empty($error['exception_wrapped'])) {
            $text .= "\nWrapped in " . $this->showSeverity($error['exception_wrapped']) . ": " . $error['exception_wrapped']['message'] . "\n";
            $text .= $this->dumpTrace($error['exception_wrapped'], $position);
        }

        return $text;
    }

    protected function fileLine(array $data)
    {
        $line = isset($data['line']) ? ':' . $data['line'] : '';

        if (isset($data['file'])) {
            $file = $data['file'];
            return $this->relativeName($file) . $line;
        }

        return '<native function>' . $line;
    }

    protected function relativeName($file)
    {
        static $prefix;
        if ($prefix === null) {
            $prefix = realpath(__DIR__ . '/../../../../../..') . '/';
        }
        if (strpos($file, $prefix) === 0) {
            return substr($file, strlen($prefix));
        }
        return $file;
    }

    protected function showTraceElement($trace, $error)
    {
        $file = isset($trace['file']) ? $trace['file'] : null;
        unset($trace['file'], $trace['line']);

        if (isset($trace['function'])) {
            $isStatement = in_array($trace['function'], array('include', 'include_once', 'require', 'require_once'));
            if (isset($trace['args'])) {
                $args = array_map(array($this, 'formatArg'), $trace['args']);
                $args = join(', ', $args);
                unset($trace['args']);

                $trace['function'] .= $isStatement ? ' ' . $args : '(' . $args . ')';
            } else if (!$isStatement) {
                // If it's an include, we know it doesn't work with no args;
                // but in case of a function we need to tell 'no args' and 'unknown args' apart.
                $trace['function'] .= '(...)';
            }
        }

        $keys = array_keys($trace);
        sort($keys);
        if ($keys === array('function')) {
            return $trace['function'];
        }

        if ($keys === array('class', 'function', 'type')) {
            return $trace['class'] . $trace['type'] . $trace['function'];
        }

        if ($keys === array('args')) {
            // this looks like a notice inside an expression
            $data = $trace['args'];
            if (count($data) > 2 && $data[0] == $error['type'] && $data[2] == $file) {
                return $data[1];
            }
        }

        if ($keys !== array()) {
            return var_export($trace, true);
        }

        if (!empty($error['exception'])) {
            return 'throw';
        }

        return '';
    }

    protected function formatArg($var)
    {
        if (is_array($var)) {
            $showKeys = !$this->isList($var);

            $s = '';
            foreach ($var as $k => $v) {
                if ($showKeys) {
                    $s .= $this->formatArg($k) . ' => ';
                }
                $s .= $this->formatArg($v) . ', ';
            }
            return '[' . substr($s, 0, -2) . ']';
        }

        if (is_string($var)) {
            if (preg_match('#^<(?:\w+\\\\)*\w+>$#', $var)) {
                return $var;
            }
            if (preg_match('#^<array \d+>$#', $var)) {
                return $var;
            }
        }

        return var_export($var, true);
    }

    protected function isList($array)
    {
        return (array_keys($array) === range(0, count($array) - 1));
    }

    private function dump($var, $prefix = '')
    {
        $s = '';
        switch (gettype($var)) {
            case 'array':
                // hide keys if: 1. values are not arrays; 2. keys are numeric
                $hideKeys = !in_array('array', array_map('gettype', $var)) && $this->isList($var);
                $pad       = 0;
                if (!$hideKeys && $var) {
                    $pad = max(array_map('strlen', array_keys($var)));
                }
                foreach ($var as $k => $v) {
                    $s .= "\n" . $prefix;
                    if (!$hideKeys) {
                        $s .= str_pad($this->dump($k) . ':', $pad + 2, ' ', STR_PAD_RIGHT);
                    }
                    $s .= $this->dump($v, $prefix . '   ');
                }
                break;

            default:
                $a = var_export($var, true);
                if ($var !== '' && $a == "'$var'") {
                    $s = $var;
                } else {
                    $s = $a;
                }
        }
        return $s;
    }
}

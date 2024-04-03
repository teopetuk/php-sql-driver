<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $argPos = 0;

        // replace args
        $result = preg_replace_callback(     
            "/\?[dfa#]?/",
            function ($matched) use ($args, &$argPos) {
                // echo("!!!matched $argPos:".print_r($matched, true));
                switch ($matched[0]) {
                    case '?':
                        return $this->formatArg($args[$argPos++], '');    
                    case '?#':
                        return $this->formatArg($args[$argPos++], '#');    
                    case '?d':
                        return $this->formatArg($args[$argPos++], 'd');    
                    case '?a':
                        return $this->formatArg($args[$argPos++], 'a');    
                }
            },
            $query,
        );

        if ($argPos <> count($args)) throw new Exception("Not all the args are consumed");

        // replace conditional blocks
        $result = preg_replace_callback(
            "/\{([^\}]+)\}/",
            function ($matched) use ($args, &$argPos) {
                if (str_contains($matched[1], $this->skip())) return '';
                return $matched[1];
            },
            $result,
        );

        return $result;
    }

    private function formatArg($arg, $type) {
        if ($arg == $this->skip()) {
            return $arg;
        }

        if ($type == 'a') {
            // format as array
            if (!is_array($arg)) throw new Exception("Array expected");
            if (!count($arg)) throw new Exception("Empty array");        

            if (array_is_list($arg)) {
                $parts = array_map(
                    fn ($x) => $this->formatArg($x, ''), 
                    $arg,
                );
                return implode(", ", $parts);                
            }

            // format as associative array
            $parts = [];
            foreach ($arg as $k => $v) {
                $parts[] = $this->formatArg($k, '#')." = ".$this->formatArg($v, '');
            }
            return implode(", ", $parts);
        }


        if ($type == '#') {
            // format as identifiers
            $arr = is_string($arg) ? [$arg] : $arg;
            if (!is_array($arr)) throw new Exception("Array expected");
            if (!count($arr)) throw new Exception("Empty array");
            return "`". implode("`, `", $arr) . "`"; // TODO: escape?
        }

        if ($type == '' && is_string($arg)) {
            // format as string
            return "'$arg'";   // TODO: escape
        }
        if ($type == 'd' || ($type == '' && is_int($arg))) {
            // format as integer
            return (int)$arg;
        }
        if ($type == 'f' || ($type == '' && is_float($arg))) {
            // format as float
            return (float)$arg;
        }
        if (in_array($type, ['f','d','']) && is_null($arg)) {
            // format as null
            return 'NULL';
        }

        return $arg;
    }

    public function skip()
    {
        return '__SKIPPED__8s7dtfgkdhlsfo';
    }
}

<?php

function explodeAttributes($string)
{
    $words = str_getcsv($string, ' ');

    $command = array(
        'command' => $words[0],
        'arguments' => array(),
    );

    for ($i = 1; $i <= count($words) - 1; $i++) {
        if (strpos($words[$i], ':') === false) {
            throw new Exception("[ERROR] Missing value for the attribute \"{$words[$i]}\"");
        }
        list($argument, $value) = str_getcsv($words[$i], ':' );
        $command['arguments'][$argument] = $value;
    }

    return $command;
}


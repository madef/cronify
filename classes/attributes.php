<?php

function explodeAttributes($string)
{
    $string = preg_replace('/ (\w+):"(.*)/Usi', ' "$1:$2', $string);
    $words = str_getcsv($string, ' ');

    $command = array(
        'command' => $words[0],
        'arguments' => array(),
    );

    for ($i = 1; $i <= count($words) - 1; $i++) {
        if (strpos($words[$i], ':') === false) {
            throw new Exception("[ERROR] Missing value for the attribute \"{$words[$i]}\"");
        }

        $substring = preg_replace('/^(\w+):(.*)$/Usi', '$1:"$2"', $words[$i]);
        list($argument, $value) = str_getcsv($substring, ':');
        $command['arguments'][$argument] = $value;
    }

    return $command;
}


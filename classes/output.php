<?php

function writeNewLine($message, $sock)
{
    $message .= "\n\0";
    socket_write($sock, $message, strlen($message));
}

function writeLine($message, $sock)
{
    $message .= "\0";
    socket_write($sock, $message, strlen($message));
}


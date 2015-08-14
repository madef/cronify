<?php

function writeLine($message, $sock)
{
    $message .= "\n";
    socket_write($sock, $message, strlen($message));
}


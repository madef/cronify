#!/usr/local/bin/php -q
<?php
error_reporting(E_ALL);

/* Autorise l'exécution infinie du script, en attente de connexion. */
set_time_limit(0);

/* Active le vidage implicite des buffers de sortie, pour que nous
 * puissions voir ce que nous lisons au fur et à mesure. */
ob_implicit_flush();

$config = json_decode(file_get_contents('config/srv.json'));

$address = $config->address;
$port = $config->port;

if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "socket_create() a échoué : raison : " . socket_strerror(socket_last_error()) . "\n";
}

if (socket_bind($sock, $address, $port) === false) {
    echo "socket_bind() a échoué : raison : " . socket_strerror(socket_last_error($sock)) . "\n";
}

if (socket_listen($sock, 5) === false) {
    echo "socket_listen() a échoué : raison : " . socket_strerror(socket_last_error($sock)) . "\n";
}

do {
    if (($msgsock = socket_accept($sock)) === false) {
        echo "socket_accept() a échoué : raison : " . socket_strerror(socket_last_error($sock)) . "\n";
        break;
    }
    /* Send instructions. */
    $msg = "\Welcome to the cronify server data.\n" .
        "To quit, enter 'quit'. To stop the server, enter 'shutdown'.\n";
    socket_write($msgsock, $msg, strlen($msg));

    do {
        if (false === ($buf = socket_read($msgsock, 2048, PHP_NORMAL_READ))) {
            echo "socket_read() a échoué : raison : " . socket_strerror(socket_last_error($msgsock)) . "\n";
            break 2;
        }
        if (!$buf = trim($buf)) {
            continue;
        }
        if ($buf == 'quit') {
            break;
        }
        if ($buf == 'shutdown') {
            socket_close($msgsock);
            break 2;
        }
        try {
            $command = explodeAttributes($buf);
        } catch (Exception $e) {
            writeLine($e->getMessage(), $msgsock);
            continue;
        }
        var_export($command);

        switch ($command['command']) {
            case 'quit':
                break;
            case 'shutdown':
                break;
            case 'add':
                execAdd($command['arguments']);
                break;
            default:
                writeLine("[ERROR] Unknow command \"{$command['command']}\"", $msgsock);
        }
    } while (true);
    socket_close($msgsock);
} while (true);

socket_close($sock);

function execAdd($arguments)
{
    // @TODO

    // Validate

    // Execute
}

function writeLine($message, $sock)
{
    $message .= "\n";
    socket_write($sock, $message, strlen($message));
}

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
?>

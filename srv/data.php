#!/usr/local/bin/php -q
<?php
/**
 * Buffer server to send commande to the data server
 *
 * Command supported :
 *    add class:<class> execMethod:<execMethod> [succesMethod:<succesMethod>] [errorMethod:<errorMethod> [date:<date>] [priority:<priority>(128)] [ttl:<ttl>(1h)] [retry:<retry counter>(0)] [data:<json data>({})]
 *    exec [id:<id>[,<id>]] [executed:<start date>..<end date>] [planned:<start date>..<end date>] [added:<start date>..<end date>] [class:<class>] [execMethod:<execMethod>] [succesMethod:<succesMethod>] [errorMethod:<errorMethod> [status:<status>] [priority:<priority>(128)] [ttl:<ttl>(1h)] [retry:<retry counter>(0)] [data:<json data>({})] [format:json|text(text)]
 *    list [id:<id>[,<id>]] [executed:<start date>..<end date>] [planned:<start date>..<end date>] [added:<start date>..<end date>] [class:<class>] [execMethod:<execMethod>] [succesMethod:<succesMethod>] [errorMethod:<errorMethod> [status:<status>] [priority:<priority>(128)] [ttl:<ttl>(1h)] [retry:<retry counter>(0)] [data:<json data>({})] [format:json|text(text)]
 */

require 'classes/attributes.php';
require 'classes/writelines.php';

error_reporting(E_ALL);

/* Autorise l'exécution infinie du script, en attente de connexion. */
set_time_limit(0);

/* Active le vidage implicite des buffers de sortie, pour que nous
 * puissions voir ce que nous lisons au fur et à mesure. */
ob_implicit_flush();

$config = json_decode(file_get_contents('config/srv.json'));

$address = $config->data->address;
$port = $config->data->port;

if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "socket_create() a échoué : raison : " . socket_strerror(socket_last_error()) . "\n";
    die();
}

if (socket_bind($sock, $address, $port) === false) {
    echo "socket_bind() a échoué : raison : " . socket_strerror(socket_last_error($sock)) . "\n";
    die();
}

if (socket_listen($sock, 5) === false) {
    echo "socket_listen() a échoué : raison : " . socket_strerror(socket_last_error($sock)) . "\n";
    die();
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
                writeLine('1', $msgsock);
                break;
            case 'list':
                execList($command['arguments']);
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

?>

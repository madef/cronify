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
require 'classes/output.php';
require 'classes/validator.php';

// Report all errors
error_reporting(E_ALL);

// No execution limit
set_time_limit(0);

// Enable implicit flush output buffers, so we can read the output progressively
ob_implicit_flush();

// Get the configuration
$config = json_decode(file_get_contents('config/srv.json'));

// Load data adapter class
$adapterClass = $config->data->adapterClass;
require 'classes/dataAdapter/'.strtolower($adapterClass).'.php';

// Force pre-loading data
$adapter = $adapterClass::getInstance();

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

        switch ($command['command']) {
            case 'quit':
                break;
            case 'shutdown':
                hardSaveData(true);
                break;
            case 'add':
                try {
                    execAdd($command['arguments']);
                    writeLine('1', $msgsock);
                } catch (Exception $e) {
                    writeLine($e->getMessage(), $msgsock);
                }
                break;
            case 'list':
                execList($command['arguments']);
                break;
            default:
                writeLine("[ERROR] Unknow command \"{$command['command']}\"", $msgsock);
        }

        hardSaveData();
    } while (true);
    socket_close($msgsock);
} while (true);

socket_close($sock);

function execAdd($arguments)
{
    global $adapter;

    // Validate
    validateAndFormatAdd($arguments);

    // Execute
    $data = new Data();
    foreach ($arguments as $key => $value) {
        $data->$key = $value;
    }
    $data->updateRealPriority($adapter->getAvgDelay());
    $adapter->add($data);
}

function hardSaveData($force = false)
{
    global $config, $adapter;

    static $lastSave = 0;

    if ($force || $adapter->hasUpdated() && time() - $lastSave > $config->data->hardSaveDelay) {
        $lastSave = time();
        $adapter->save();
    }
}

?>

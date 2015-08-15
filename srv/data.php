#!/usr/local/bin/php -q
<?php
/**
 * Buffer server to send commande to the data server
 *
 * Command supported :
 *    add class:<class> execMethod:<execMethod> [succesMethod:<succesMethod>] [errorMethod:<errorMethod> [date:<date>] [priority:<priority>(128)] [ttl:<ttl>(1h)] [retry:<retry counter>(0)] [data:<json data>({})]
 *    update [id:<id>[,<id>]] [executed:<start date>..<end date>] [planned:<start date>..<end date>] [added:<start date>..<end date>] [class:<class>] [execMethod:<execMethod>] [succesMethod:<succesMethod>] [errorMethod:<errorMethod> [status:<status>] [priority:<priority>(128)] [ttl:<ttl>(1h)] [retry:<retry counter>(0)] [data:<json data>({})] [format:json|text(text)]
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

// Force pre-loading tasks
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
        "To quit, enter 'quit'. To stop the server, enter 'shutdown'.";
    writeNewLine($msg, $msgsock);

    do {
        if (false === ($buf = socket_read($msgsock, 2048, PHP_NORMAL_READ))) {
            echo "socket_read() a échoué : raison : " . socket_strerror(socket_last_error($msgsock)) . "\n";
            break;
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
            writeNewLine($e->getMessage(), $msgsock);
            continue;
        }

        switch ($command['command']) {
            case 'quit':
                break;
            case 'shutdown':
                hardSaveTasks(true);
                break;
            case 'add':
                try {
                    execAdd($command['arguments']);
                    writeNewLine('1', $msgsock);
                } catch (Exception $e) {
                    writeNewLine($e->getMessage(), $msgsock);
                }
                break;
            case 'list':
                try {
                    execList($command['arguments'], $msgsock);
                } catch (Exception $e) {
                    writeNewLine($e->getMessage(), $msgsock);
                }
                break;
            case 'update':
                try {
                    execUpdate($command['arguments']);
                    writeNewLine('1', $msgsock);
                } catch (Exception $e) {
                    writeNewLine($e->getMessage(), $msgsock);
                }
                break;
            default:
                writeNewLine("[ERROR] Unknow command \"{$command['command']}\"", $msgsock);
        }

        hardSaveTasks();
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
    $task = new Task();
    foreach ($arguments as $key => $value) {
        $task->$key = $value;
    }

    if (empty($task->id)) {
        do {
            $task->id = sha1(mt_rand(0, mt_getrandmax()));
        } while ($adapter->getById($task->id));
    }
    $task->updateRealPriority($adapter->getAvgDelay());
    $task->update();
    $adapter->add($task);
}

function execUpdate($arguments)
{
    global $adapter;

    // Validate
    validateUpdate($arguments);

    // Check the task exists
    if (!($task = $adapter->getById($arguments['id']))) {
        throw new Exception("[ERROR] Unknow task with in \"{$arguments['id']}\"");
    }

    // Execute
    foreach ($arguments as $key => $value) {
        $task->$key = $value;
    }
    $task->updateRealPriority($adapter->getAvgDelay());
    $task->update();


    $adapter->update();
}

function execList($arguments, $msgsock)
{
    global $adapter;

    // Validate
    validateList($arguments);

    $collection = array();
    foreach ($adapter->getCollection() as $task) {
        if (!matchFilter($task, $arguments)) {
            continue;
        }

        $collection[] = $task;
    }
    writeNewLine(json_encode($collection, JSON_PRETTY_PRINT), $msgsock);
}

function hardSaveTasks($force = false)
{
    global $config, $adapter;

    static $lastSave = 0;

    if ($force || $adapter->hasUpdated() && time() - $lastSave > $config->data->hardSaveDelay) {
        $lastSave = time();
        $adapter->save();
    }
}

?>

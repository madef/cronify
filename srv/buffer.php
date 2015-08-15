#!/usr/local/bin/php -q
<?php
/**
 * Buffer server to send commande to the data server
 *
 * Command supported :
 *    add class:<class> execMethod:<execMethod> [succesMethod:<succesMethod>] [errorMethod:<errorMethod> [date:<date>] [priority:<priority>(128)] [ttl:<ttl>(1h)] [retry:<retry counter>(0)] [data:<json data>({})]
 *    update [id:<id>[,<id>]] [executed:<start date>..<end date>] [planned:<start date>..<end date>] [added:<start date>..<end date>] [class:<class>] [execMethod:<execMethod>] [succesMethod:<succesMethod>] [errorMethod:<errorMethod> [status:<status>] [priority:<priority>(128)] [ttl:<ttl>(1h)] [retry:<retry counter>(0)] [data:<json data>({})] [format:json|text(text)]
 *    list [id:<id>[,<id>]] [executed:<start date>..<end date>] [planned:<start date>..<end date>] [added:<start date>..<end date>] [class:<class>] [execMethod:<execMethod>] [succesMethod:<succesMethod>] [errorMethod:<errorMethod> [status:<status>] [priority:<priority>(128)] [ttl:<ttl>(1h)] [retry:<retry counter>(0)] [data:<json data>({})] [format:json|text(text)]
 *    execute [id:<id>[,<id>]] [executed:<start date>..<end date>] [planned:<start date>..<end date>] [added:<start date>..<end date>] [class:<class>] [execMethod:<execMethod>] [succesMethod:<succesMethod>] [errorMethod:<errorMethod> [status:<status>] [priority:<priority>(128)] [ttl:<ttl>(1h)] [retry:<retry counter>(0)] [data:<json data>({})] [format:json|text(text)]
 *    push # push the buffer in the data server
 *    quit # close buffer
 */

require 'classes/attributes.php';
require 'classes/output.php';
require 'classes/validator.php';
require 'classes/task.php';

// Report all errors
error_reporting(E_ALL);

// No execution limit
set_time_limit(0);

// Enable implicit flush output buffers, so we can read the output progressively
ob_implicit_flush();

// Buffer
$buffer = array();

// Get the configuration
$config = json_decode(file_get_contents('config/srv.json'));

$address = $config->buffer->address;
$port = $config->buffer->port;

if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "socket_create() a échoué : raison : " . socket_strerror(socket_last_error()) . "\n";
    die();
}

socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);

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
    socket_getsockname($msgsock, $add, $p);
    $pid = pcntl_fork();
    if ($pid == -1) {
        throw new Exception('Could not fork');
    }

    if (!$pid) {
        /* Send instructions. */
        $msg = "\Welcome to the cronify server data buffer.\n" .
            "To quit, enter 'quit'.";
        writeNewLine($msg, $msgsock);

        do {
            if (false === ($buf = socket_read($msgsock, 2048, PHP_NORMAL_READ))) {
                echo "socket_read() a échoué : raison : " . socket_strerror(socket_last_error($msgsock)) . "\n";
                break 2;
            }

            if (!$buf = trim($buf)) {
                continue;
            }
            if ($buf == 'quit' || $buf == 'shutdown') {
                break;
            }
            try {
                $command = explodeAttributes($buf);
            } catch (Exception $e) {
                writeNewLine($e->getMessage(), $msgsock);
                continue;
            }

            switch ($command['command']) {
                case 'quit':
                case 'shutdown':
                    break;
                case 'add':
                    try {
                        execAdd($command['arguments'], $buf);
                        writeNewLine('1', $msgsock);
                    } catch (Exception $e) {
                        writeNewLine($e->getMessage(), $msgsock);
                    }
                    break;
                case 'update':
                    try {
                        execUpdate($command['arguments'], $buf);
                        writeNewLine('1', $msgsock);
                    } catch (Exception $e) {
                        writeNewLine($e->getMessage(), $msgsock);
                    }
                    break;
                case 'execute':
                    try {
                        execExecute($command['arguments'], $buf);
                        writeNewLine('1', $msgsock);
                    } catch (Exception $e) {
                        writeNewLine($e->getMessage(), $msgsock);
                    }
                    break;
                case 'push':
                    try {
                        push();
                        writeNewLine('Everything is pushed', $msgsock);
                    } catch (Exception $e) {
                        writeNewLine($e->getMessage(), $msgsock);
                    }
                    break;
                default:
                    execCommand($buf);
            }
        } while (true);

        $error = false;
        do {
            try {
                push();
            } catch (Exception $e) {
                writeNewLine($e->getMessage(), $msgsock);
                $error = true;
            }
        } while ($error == true);
    }
    socket_close($msgsock);
} while ($pid > 0);

socket_close($sock);

function execAdd($arguments, $command)
{
    global $buffer;

    // Validate
    validateAndFormatAdd($arguments);

    // Execute
    $buffer[] = $command;
}

function execUpdate($arguments, $command)
{
    global $buffer;

    // Validate
    validateUpdate($arguments);

    // Execute
    $buffer[] = $command;
}

function execExecute($arguments)
{
    // Validate
    validateExecute($arguments);

    // Command
    $command = 'list';
    foreach ($arguments as $key => $value) {
        $command .= ' '.$key.':'.$value;
    }

    // Get list
    $result = sendCommand($command);
    $result = trim($result);

    $dataCollection = json_decode($result, false);

    foreach ($dataCollection as $data) {
        $task = Task::populate($data);

        // Execute
        $task->execute();

        // Update task
        $command = "update id:{$task->id} error:\"".$task->lastError."\" retry:{$task->retry}";
        sendCommand($command);
    }

}

function execCommand($command)
{
    global $msgsock;

    $result = sendCommand($command);

    writeNewLine(trim($result), $msgsock);
}

/**
 * Send command to the data server
 */
function sendCommand($command)
{
    global $config;

    $dataSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (socket_connect($dataSocket, $config->data->address, $config->data->port) === false) {
        throw new Exception("[ERROR] Cannot connect to the data server");
    }

    // Ignore first message
    do {
        $res = socket_read($dataSocket, 2048);
    } while (strlen($res) == 2048 && $res[2047] != "\0");

    writeNewLine($command, $dataSocket);

    $result = '';
    do {
        $res = socket_read($dataSocket, 2048);
        if (!empty($res)) {
            $result .= $res;
        }
    } while (strlen($res) == 2048 && $res[2047] != "\0");

    // Close connection
    writeNewLine('quit', $dataSocket);

    socket_close($dataSocket);

    return $result;
}

/**
 * Push the buffer to the data server
 */
function push()
{
    global $buffer, $config;

    if (!count($buffer)) {
        return;
    }

    $dataSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (socket_connect($dataSocket, $config->data->address, $config->data->port) === false) {
        throw new Exception("[ERROR] Cannot connect to the data server");
    }
    // Ignore first message
    do {
        $res = socket_read($dataSocket, 2048);
    } while ($res != '');

    foreach ($buffer as $i => $command) {
        writeNewLine($command, $dataSocket);
        $result = socket_read($dataSocket, 2048);
        if ($result === "1\n") {
            unset($buffer[$i]);
        }
    }

    // Close connection
    writeNewLine('quit', $dataSocket);

    socket_close($dataSocket);
}

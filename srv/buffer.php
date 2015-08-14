#!/usr/local/bin/php -q
<?php
/**
 * Buffer server to send commande to the data server
 *
 * Command supported :
 *    add class:<class> execMethod:<execMethod> [succesMethod:<succesMethod>] [errorMethod:<errorMethod> [date:<date>] [priority:<priority>(128)] [ttl:<ttl>(1h)] [retry:<retry counter>(0)] [data:<json data>({})]
 *    push # push the buffer in the data server
 *    quit # close buffer
 *    * # all other command will be send to the data server
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
            "To quit, enter 'quit'.\n";
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
            try {
                $command = explodeAttributes($buf);
            } catch (Exception $e) {
                writeLine($e->getMessage(), $msgsock);
                continue;
            }

            switch ($command['command']) {
                case 'quit':
                    break;
                case 'add':
                    try {
                        execAdd($command['arguments'], $buf);
                    } catch (Exception $e) {
                        writeLine($e->getMessage(), $msgsock);
                    }
                    break;
                case 'push':
                    try {
                        push();
                        writeLine('Everything is pushed', $msgsock);
                    } catch (Exception $e) {
                        writeLine($e->getMessage(), $msgsock);
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
                writeLine($e->getMessage(), $msgsock);
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

function execCommand($command)
{
    global $msgsock;

    $result = sendCommand($command);

    foreach ($result as $line) {
        writeLine(trim($line), $msgsock);
    }
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
        $res = socket_read($dataSocket, 1024);
    } while ($res == '');

    writeLine($command, $dataSocket);
    $result = array();
    do {
        $res = socket_read($dataSocket, 1024);
        if (!empty($res)) {
            $result[] = $res;
        }
    } while ($res == '');

    // Close connection
    writeLine('quit', $dataSocket);

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
        $res = socket_read($dataSocket, 1024);
    } while ($res == '');

    foreach ($buffer as $i => $command) {
        writeLine($command, $dataSocket);
        $result = socket_read($dataSocket, 1024);
        if ($result === "1\n") {
            unset($buffer[$i]);
        }
    }

    // Close connection
    writeLine('quit', $dataSocket);

    socket_close($dataSocket);
}

<?php

if (count($argv) <= 3) {
    die();
}

$class = $argv[1];
$method = $argv[2];
$data = isset($argv[3]) ? $argv[3] : null;

try {
    if (!class_exists($class)) {
        throw new Exception("Class \"$class\" do not exists");
    }

    if (!method_exists($class, $method)) {
        throw new Exception("Method \"$method\" of the class \"$class\" do not exists");
    }

    $data = $class::$method($data);
    $return = array(
        'success' => true,
        'message' => '',
        'data' => $data,
    );
} catch (Exception $e) {
    $return = array(
        'success' => false,
        'message' => $e->getMessage(),
    );
}

echo json_encode($return);

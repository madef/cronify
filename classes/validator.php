<?php

function validateAndFormatAdd(&$attributes)
{
    $format = array(
        'class' => array('type' => 'regex', 'value' => '[a-z_0-9]+'),
        'execMethod' => array('type' => 'regex', 'value' => '[a-z_0-9]+'),
        'successMethod' => array('type' => 'regex', 'value' => '[a-z_0-9]+', 'nullable' => true),
        'errorMethod' => array('type' => 'regex', 'value' => '[a-z_0-9]+', 'nullable' => true),
        'date' => array('type' => 'regex', 'value' => '[0-9]+'),
        'priority' => array('type' => 'int', 'ge' => 0, 'lt' => 256),
        'ttl' => array('type' => 'int', 'gt' => 0),
        'retry' => array('type' => 'int', 'ge' => 0),
        'data' => array('type' => 'json'),
    );

    $defaultValues = array(
        'successMethod' => null,
        'errorMethod' => null,
        'date' => time(),
        'priority' => 128,
        'ttl' => 60,
        'retry' => 0,
        'data' => '{}',
    );

    setDefault($attributes, $defaultValues);

    foreach ($format as $attribute => $validator) {
        if (!array_key_exists($attribute, $attributes)) {
            throw new Exception("[ERROR] Missing argument \"{$attribute}\"");
        }
        if (!validateField($attributes[$attribute], $validator)) {
            throw new Exception("[ERROR] Bad format for argument \"{$attribute}\"");
        }
    }
    return true;
}

function validateField($value, $validator)
{
    if (is_null($value) && !empty($validator['nullable'])) {
        return true;
    }

    switch ($validator['type']) {
        case 'regex':
            return (bool)preg_match("/^{$validator['value']}$/Usi", $value);
        case 'int':
            if (!is_numeric($value)) {
                return false;
            }
            if (isset($validator['ge']) && !($value >= $validator['ge'])) {
                return false;
            }
            if (isset($validator['gt']) && !($value > $validator['gt'])) {
                return false;
            }
            if (isset($validator['le']) && !($value <= $validator['le'])) {
                return false;
            }
            if (isset($validator['lt']) && !($value < $validator['lt'])) {
                return false;
            }
            return true;
        case 'json':
            return !(json_decode($value) === null);
    }
}

function setDefault(&$attributes, $defaultValue)
{
    foreach ($defaultValue as $attribute => $value) {
        if (!isset($attributes[$attribute])) {
            $attributes[$attribute] = $value;
        }
    }
}

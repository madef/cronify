<?php

function validateAndFormatAdd(&$attributes)
{
    $format = array(
        'class' => array('type' => 'regex', 'value' => '[a-z_0-9]+'),
        'execMethod' => array('type' => 'regex', 'value' => '[a-z_0-9]+'),
        'successMethod' => array('type' => 'regex', 'value' => '[a-z_0-9]+', 'nullable' => true),
        'errorMethod' => array('type' => 'regex', 'value' => '[a-z_0-9]+', 'nullable' => true),
        'plannedAt' => array('type' => 'regex', 'value' => '[0-9]+'),
        'priority' => array('type' => 'int', 'ge' => 0, 'lt' => 256),
        'ttl' => array('type' => 'int', 'gt' => 0),
        'retry' => array('type' => 'int', 'ge' => 0),
        'data' => array('type' => 'json'),
    );

    $defaultValues = array(
        'successMethod' => null,
        'errorMethod' => null,
        'plannedAt' => time(),
        'priority' => 128,
        'ttl' => 60,
        'retry' => 0,
        'retryDelay' => 60,
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

function validateList($attributes)
{
    $format = array(
        'class' => array('type' => 'regex', 'value' => '[a-z_0-9]+'),
        'execMethod' => array('type' => 'regex', 'value' => '[a-z_0-9]+'),
        'successMethod' => array('type' => 'regex', 'value' => '[a-z_0-9]+'),
        'errorMethod' => array('type' => 'regex', 'value' => '[a-z_0-9]+'),
        'plannedAt' => array('type' => 'regex', 'value' => '[0-9]+'),
        'executedAt' => array('type' => 'regex', 'value' => '[0-9]+'),
        'addedAt' => array('type' => 'regex', 'value' => '[0-9]+'),
        'priority' => array('type' => 'int', 'ge' => 0, 'lt' => 256),
        'ttl' => array('type' => 'int', 'gt' => 0),
        'retry' => array('type' => 'int', 'ge' => 0),
        'data' => array('type' => 'json'),
    );

    foreach ($format as $attribute => $validator) {
        if (empty($attributes[$attribute])) {
            continue;
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

function matchFilter($data, $attributes)
{
    foreach ($attributes as $key => $value) {
        // 3 types of format :
        //   ???..???: between and
        //   ???,??? : list of values
        //   ???     : exact match
        //   ~???    : rexep

        $dataValue = $data->$key;
        if (strpos($value, '..') !== false) {
            list($between, $and) = explode('..', $value);
            $between = converteDate($between);
            $and = converteDate($and);
            if ($dataValue < $between || $dataValue > $and) {
                return false;
            }
        } else if (strpos($value, '~') === 0) {
            $value = substr($value, 1);
            if (!preg_match("/$value/Usi", $dataValue)) {
                return false;
            }
        } else if (preg_match('/^(\d+,)+\d+$/Usi', $value)
            || preg_match('/^([a-z]+,)+[a-z]+$/Usi', $value)
        ) {
            $values = explode(',', $value);
            if (!in_array($dataValue, $values)) {
                return false;
            }
        } else {
            if ($value != $dataValue) {
                return false;
            }
        }
    }
    return true;
}

function converteDate($date)
{
    if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}( \d{1,2}(:\d{1,2}(:\d{1,2})?)?)?$/Usi', $date)) {
        $time = strtotime($date);
        if ($time !== false) {
            throw new Exception("[ERROR] Unknow date format \"{$date}\"");
        }
        return $time;
    } else {
        return $date;
    }
}


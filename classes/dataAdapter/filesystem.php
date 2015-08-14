<?php

require "classes/dataAdapter/interface.php";
require "classes/data.php";

class FileSystem implements AdapterInterface
{
    protected static $instance = null;
    protected $collection;
    protected $hasUpdated = false;

    protected function __construct()
    {
        if (!file_exists('data/data.serialized')) {
            file_put_contents('data/data.serialized', serialize(array()));
        }
        $this->collection = unserialize(file_get_contents('data/data.serialized'));

        return $this;
    }

    public function getAvgDelay()
    {
        $delay = 0;
        $itemCount = 0;
        foreach ($this->collection as $data) {
            if ($data->lastStatus === 'Data::STATUS_PENDING') {
                continue;
            }
            if ($data->plannedAt > time()) {
                continue;
            }
            $itemCount++;
            $delay += time() - $data->plannedAt;
        }

        if (!$itemCount || $delay === 0) {
            return 1;
        }

        return (int)($delay/$itemCount);
    }

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new FileSystem();
        }
        return self::$instance;
    }

    public function getCollection()
    {
        return $this->collection;
    }

    public function save()
    {
        file_put_contents('data/data.serialized', serialize($this->collection));
        $this->hasUpdated = false;
        return $this;
    }

    public function add(Data $data)
    {
        $this->collection[] = $data;
        $this->hasUpdated = true;
        return $this;
    }

    public function hasUpdated()
    {
        return $this->hasUpdated;
    }
}

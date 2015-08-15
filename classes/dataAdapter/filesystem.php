<?php

require "classes/dataAdapter/interface.php";
require "classes/task.php";

class FileSystem implements AdapterInterface
{
    protected static $instance = null;
    protected $collection;
    protected $hasUpdated = false;

    protected function __construct()
    {
        if (!file_exists('data/tasks.serialized')) {
            file_put_contents('data/tasks.serialized', serialize(array()));
        }
        $this->collection = unserialize(file_get_contents('data/tasks.serialized'));

        return $this;
    }

    public function getAvgDelay()
    {
        $delay = 0;
        $itemCount = 0;
        foreach ($this->collection as $task) {
            if ($task->lastStatus === 'Data::STATUS_PENDING') {
                continue;
            }
            if ($task->plannedAt > time()) {
                continue;
            }
            $itemCount++;
            $delay += time() - $task->plannedAt;
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

    public function getById($id)
    {
        if (!isset($this->collection[$id])) {
            return false;
        }
        return $this->collection[$id];
    }

    public function save()
    {
        file_put_contents('data/tasks.serialized', serialize($this->collection));
        $this->hasUpdated = false;
        return $this;
    }

    public function add(Task $task)
    {
        $this->collection[$task->id] = $task;
        $this->hasUpdated = true;
        return $this;
    }

    public function update()
    {
        $this->hasUpdated = true;
        return $this;
    }

    public function hasUpdated()
    {
        return $this->hasUpdated;
    }
}

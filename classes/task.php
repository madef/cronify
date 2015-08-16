<?php

class Task
{
    const STATUS_PENDING = 'pending';
    const STATUS_ERROR = 'error';
    const STATUS_LOCKED = 'locked';
    const STATUS_RUNNING = 'running';
    const STATUS_SUCCESS = 'success';

    public $id = null;
    public $class = null;
    public $execMethod = null;
    public $succesMethod = null;
    public $errorMethod = null;
    public $priority = null;
    public $realPriority = null;
    public $ttl = null;
    public $retry = null;
    public $retryDelay = null;
    public $data = null;
    public $executedAt = null;
    public $plannedAt = null;
    public $insertedAt = null;
    public $updatedAt = null;
    public $lastError = null;
    public $lastStatus = self::STATUS_PENDING;
    public $history = array(); // { status => ..., 'retry' => ..., 'error' => ..., 'date' => ... }

    public function __construct()
    {
        $this->insertedAt = time();
    }

    public function update()
    {
        $this->updatedAt = time();

        $lastHistory = end($this->history);
        if ($lastHistory['status'] == $this->lastStatus
            && $lastHistory['error'] == $this->lastError
            &&  $lastHistory['retry'] == $this->retry
        ) {
            return $this;
        }

        $this->history[] = array(
            'status' => $this->lastStatus,
            'retry' => $this->retry,
            'error' => $this->lastError,
            'date' => $this->updatedAt,
        );
    }

    public function updateRealPriority($avgDelay)
    {
        if (time() < $this->plannedAt) {
            $this->realPriority = $this->priority;
        }
        $this->realPriority = min(256, $this->priority + 128 - (time() - $this->plannedAt) / $avgDelay * 128);
    }

    public function execute()
    {
        // Get the configuration
        $config = json_decode(file_get_contents('config/srv.json'));

        $class = escapeshellcmd($this->class);
        $method = escapeshellcmd($this->execMethod);
        $data = escapeshellcmd($this->data);
        $command = "{$config->task->runCommand} {$class} {$method} {$data}";

        exec($command, $output);
        $result = json_decode($output[0]);

        if (!$result->success) {
            $this->lastError = $result->message;
            $this->lastStatus = self::STATUS_ERROR;
            $this->retry = ($this->retry > 1) ? ($this->retry - 1) : 0;

            if (empty($this->errorMethod)) {
                return;
            }

            $method = escapeshellcmd($this->errorMethod);
            $command = "{$config->task->runCommand} {$class} {$method} {$data}";

            exec($command, $output);
            $result = json_decode($output[0]);

        } else {
            $this->lastError = null;
            $this->lastStatus = self::STATUS_SUCCESS;

            if (empty($this->successMethod)) {
                return;
            }

            $method = escapeshellcmd($this->successMethod);
            $command = "{$config->task->runCommand} {$class} {$method} {$data}";

            exec($command, $output);
            $result = json_decode($output[0]);
        }

        if (isset($result->data->status)) {
            $this->lastStatus = $result->data->status;
        }
        if (isset($result->data->retry)) {
            $this->retry = $result->data->retry;
        }
        if (isset($result->data->error)) {
            $this->lastError = $result->data->error;
        }

        return $this;
    }

    public static function populate($data)
    {
        $task = new Task();
        foreach ($data as $key => $value) {
            $task->$key = $value;
        }
        return $task;
    }
}

<?php

class Task
{
    const STATUS_PENDING = 'pending';
    const STATUS_ERROR = 'error';
    const STATUS_RUNNING = 'running';
    const STATUS_SUCCESS = 'success';

    public $id = null;
    public $class = null;
    public $execMethod = null;
    public $succesMethod = null;
    public $errorMethod = null;
    public $date = null;
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
}

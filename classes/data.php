<?php

class Data
{
    const STATUS_PENDING = 0;
    const STATUS_ERROR = -1;
    const STATUS_RUNNING = 1;
    const STATUS_SUCCESS = 2;

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
    public $lastError = null;
    public $lastStatus = self::STATUS_PENDING;
    public $history = array(); // { status => ..., 'error' => ..., 'date' => ... }

    public function __construct()
    {
        $this->history[] = array(
            'status' => self::STATUS_PENDING,
            'error' => null,
            'date' => time(),
        );
        $this->insertedAt = time();
    }

    public function updateRealPriority($avgDelay)
    {
        if (time() < $this->plannedAt) {
            $this->realPriority = $this->priority;
        }
        $this->realPriority = min(256, $this->priority + 128 - (time() - $this->plannedAt) / $avgDelay * 128);
    }
}

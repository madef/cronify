<?php

interface AdapterInterface
{
    public static function getInstance();
    public function getCollection();
    public function save();
    public function add(Task $task);
    public function update();
    public function getById($id);
    public function hasUpdated();
}

<?php

interface AdapterInterface
{
    public static function getInstance();
    public function getCollection();
    public function save();
    public function add(Data $data);
    public function hasUpdated();
}

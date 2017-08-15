<?php

class Memcached {
    const OPT_BINARY_PROTOCOL = 0;

    public function getServerList() {
        return [];
    }

    public function addServer() {
        return true;
    }

    public function setOptions() {
        return true;
    }

    public function get() {
        return false;
    }

    public function set() {
        return true;
    }

    public function getMulti() {
        return [];
    }

    public function add() {
        return true;
    }

    public function delete() {
        return true;
    }

    public function increment() {
        return true;
    }
}
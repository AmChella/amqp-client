<?php
require_once "./vendor/autoload.php";

use chella\amqp\App;

class Test {
    public function __construct() {
    }

    public function start(String $data) {
        print_r($data);
    }

    public function listen() {
        $consumer = App::context('localhost', 'guest', 'guest', 5672);
        $consumer->listen($this, 'start', 'test', 2);
    }
}

$obj = new Test();
$obj->listen();
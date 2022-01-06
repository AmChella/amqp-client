<?php
require_once "./vendor/autoload.php";

use chella\amqp\App;

class Test {
    public function start(String $data) {
        print_r($data);
    }

    public function listen() {
        $context = App::context('localhost', 'guest', 'guest', 5672, '/', 'ssl');
        // $context->listen($this, 'start', 'test', 2);
        $context->publish('hello world', 'test', 'test_routing');
    }
}

$obj = new Test();
$obj->listen();
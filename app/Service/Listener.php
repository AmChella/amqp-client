<?php
namespace chella\amqp\Service;

use chella\amqp\Exception\InvalidParams;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class Listener {
    private $conn;
    private $service;
    private $method;
    
    /**
     * __construct
     *
     * @param  mixed $object
     * @return void
     */
    public function __construct(Array $object) {
        $this->validate($object);
        $this->conn = $object['connection'];
        $this->service = $object['service'];
        $this->method = $object['method'];
        $this->queue = $object['queue'];
        $this->max = $object['maxIteration'];
    }
    
    /**
     * validate
     *
     * @param  mixed $args
     * @return void
     */
    public function validate(Array $args) {
        $requiredKeys = ['connection', 'service', 'method', 'queue'];
        foreach($requiredKeys as $item) {
            if (\array_key_exists($item, $args) === false) {
                $msg = sprintf("%s is missing", $item);
                throw new InvalidParams($msg);
            }

            if (method_exists($args['service'], $args['method']) === false) {
                $msg = sprintf("method %s is not found", $args['method']);
                throw new InvalidParams($msg);
            }
        }

        if (
            ($args['connection'] instanceof AMQPStreamConnection) === false
        ) {
            throw new InvalidParams("'connection' is invalid");
        }

    }
    
    /**
     * consume
     *
     * @param  mixed $msg
     * @return void
     */
    public function consume(String $msg) {
        \call_user_func_array([$this->service, $this->method], [$msg]);
    }
    
    /**
     * watch
     *
     * @return void
     */
    public function watch() {
        $channel = $this->conn->channel();
        $channel->basic_qos(null, 1, null);
        $channel->basic_consume(
            $this->queue, '', false, false, false, false, 
            function($msg) {
                $body = $msg->body;
                call_user_func_array([$this, 'consume'], [$body]);
                $msg->ack();
            }
        );

        $counter = 0;
        while ($channel->is_open() && $counter < $this->max) {
            $counter++;
            $channel->wait();
        }
        
        if ($counter >= $this->max) {
            echo " [x] Max iteration reached\n";
        }

        $channel->close();
        $this->conn->close();
    }
}
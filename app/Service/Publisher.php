<?php
namespace chella\amqp\Service;

use chella\amqp\Exception\EmptyException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Publisher {
    private $service;
    public function __construct(AMQPStreamConnection $service) {
        $this->service = $service;
    }

    public function publish(String $xchange, String $routing, String $msg) {
        if (empty(trim($xchange)) === true) {
            throw new EmptyException("exchange.is.empty");
        }

        if (empty(trim($msg)) === true) {
            throw new EmptyException("message.is.empty");
        }

        $channel = $this->service->channel();
        $msg = new AMQPMessage($msg);
        $channel->basic_publish($msg, $xchange, $routing);
        $channel->close();
        $this->service->close();
    }
}
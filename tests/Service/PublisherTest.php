<?php

namespace chella\amqp\Tests\Service;

use chella\amqp\Service\Publisher;
use chella\amqp\Exception\EmptyException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;

class PublisherTest extends TestCase
{
    public function testPublishSuccess()
    {
        $mockConnection = $this->createMock(AMQPStreamConnection::class);
        $mockChannel = $this->createMock(AMQPChannel::class);

        $mockConnection->expects($this->once())
            ->method('channel')
            ->willReturn($mockChannel);

        $exchange = 'test_exchange';
        $routingKey = 'test_routing_key';
        $messageBody = 'Test message';

        $mockChannel->expects($this->once())
            ->method('basic_publish')
            ->with(
                $this->callback(function ($msg) use ($messageBody) {
                    return $msg instanceof AMQPMessage && $msg->getBody() === $messageBody;
                }),
                $exchange,
                $routingKey
            );

        $mockChannel->expects($this->once())
            ->method('close');

        $mockConnection->expects($this->once())
            ->method('close');

        $publisher = new Publisher($mockConnection);
        $publisher->publish($exchange, $routingKey, $messageBody);
    }

    public function testPublishThrowsExceptionForEmptyExchange()
    {
        $mockConnection = $this->createMock(AMQPStreamConnection::class);
        $publisher = new Publisher($mockConnection);

        $this->expectException(EmptyException::class);
        $this->expectExceptionMessage("exchange.is.empty");

        $publisher->publish('', 'test_routing_key', 'Test message');
    }

    public function testPublishThrowsExceptionForEmptyMessage()
    {
        $mockConnection = $this->createMock(AMQPStreamConnection::class);
        $publisher = new Publisher($mockConnection);

        $this->expectException(EmptyException::class);
        $this->expectExceptionMessage("message.is.empty");

        $publisher->publish('test_exchange', 'test_routing_key', '');
    }

    public function testPublishThrowsExceptionForWhitespaceMessage()
    {
        $mockConnection = $this->createMock(AMQPStreamConnection::class);
        $publisher = new Publisher($mockConnection);

        $this->expectException(EmptyException::class);
        $this->expectExceptionMessage("message.is.empty");

        $publisher->publish('test_exchange', 'test_routing_key', '   ');
    }

    public function testPublishThrowsExceptionForWhitespaceExchange()
    {
        $mockConnection = $this->createMock(AMQPStreamConnection::class);
        $publisher = new Publisher($mockConnection);

        $this->expectException(EmptyException::class);
        $this->expectExceptionMessage("exchange.is.empty");

        $publisher->publish('   ', 'test_routing_key', 'Test message');
    }
}

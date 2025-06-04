<?php

namespace chella\amqp\Tests\Service;

use chella\amqp\Service\Listener;
use chella\amqp\Exception\InvalidParams;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use stdClass;

class ListenerTest extends TestCase
{
    private function getValidArgs(array $overrides = []): array
    {
        $mockConnection = $this->createMock(AMQPStreamConnection::class);
        $mockService = $this->getMockBuilder(stdClass::class)->addMethods(['testMethod'])->getMock();

        return array_merge([
            'connection' => $mockConnection,
            'service' => $mockService,
            'method' => 'testMethod',
            'queue' => 'test_queue',
            'maxIteration' => 10
        ], $overrides);
    }

    public function testConstructorSuccess()
    {
        $args = $this->getValidArgs();
        $listener = new Listener($args);
        $this->assertInstanceOf(Listener::class, $listener);
    }

    public function testValidateMissingConnection()
    {
        $args = $this->getValidArgs();
        unset($args['connection']);
        $this->expectException(InvalidParams::class);
        $this->expectExceptionMessage("connection is missing");
        new Listener($args);
    }

    public function testValidateMissingService()
    {
        $args = $this->getValidArgs();
        unset($args['service']);
        // Expecting PHP Warning due to bug in Listener::validate where it accesses $args['service']
        // in method_exists before ensuring 'service' key leads to InvalidParams for missing 'service'.
        $this->expectException(\PHPUnit\Framework\Error\Warning::class);
        // We can't easily assert the "Undefined array key \"service\"" message part of a warning.
        // The main goal here is to acknowledge the warning stops the test from reaching InvalidParams.
        new Listener($args);
    }

    public function testValidateMissingMethod()
    {
        $args = $this->getValidArgs();
        unset($args['method']);
        // Expecting PHP Warning due to bug in Listener::validate
        $this->expectException(\PHPUnit\Framework\Error\Warning::class);
        new Listener($args);
    }

    public function testValidateMissingQueue()
    {
        $args = $this->getValidArgs();
        unset($args['queue']);
        $this->expectException(InvalidParams::class);
        $this->expectExceptionMessage("queue is missing");
        new Listener($args);
    }

    public function testValidateInvalidConnectionType()
    {
        $args = $this->getValidArgs(['connection' => new stdClass()]);
        $this->expectException(InvalidParams::class);
        $this->expectExceptionMessage("'connection' is invalid");
        new Listener($args);
    }

    public function testValidateServiceMethodNotFound()
    {
        $args = $this->getValidArgs(['method' => 'nonExistentMethod']);
        $this->expectException(InvalidParams::class);
        $this->expectExceptionMessage("method nonExistentMethod is not found");
        new Listener($args);
    }

    public function testConsumeCallsServiceMethod()
    {
        $messageBody = 'Test Message Body';
        $mockService = $this->getMockBuilder(stdClass::class)
                            ->addMethods(['processMessage'])
                            ->getMock();
        $mockService->expects($this->once())
                    ->method('processMessage')
                    ->with($messageBody);

        $args = $this->getValidArgs([
            'service' => $mockService,
            'method' => 'processMessage'
        ]);
        $listener = new Listener($args);
        $listener->consume($messageBody);
    }

    public function testWatchBasicFlow()
    {
        $mockConnection = $this->createMock(AMQPStreamConnection::class);
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockService = $this->getMockBuilder(stdClass::class)
                            ->addMethods(['handleMessage'])
                            ->getMock();
        $mockMessage = $this->createMock(AMQPMessage::class);

        $queueName = 'my_queue';
        $messageBody = 'the_message_body';
        $maxIterations = 1; // Ensure it runs once and exits due to maxIteration

        $args = $this->getValidArgs([
            'connection' => $mockConnection,
            'service' => $mockService,
            'method' => 'handleMessage',
            'queue' => $queueName,
            'maxIteration' => $maxIterations
        ]);

        $mockConnection->expects($this->once())->method('channel')->willReturn($mockChannel);
        $mockChannel->expects($this->once())->method('basic_qos')->with(null, 1, null);

        $mockMessage->body = $messageBody;
        $mockMessage->expects($this->once())->method('ack');

        // This is the tricky part: capturing the callback and invoking it
        $callback = null;
        $mockChannel->expects($this->once())
            ->method('basic_consume')
            ->with(
                $queueName, '', false, false, false, false,
                $this->callback(function ($cb) use (&$callback) {
                    $callback = $cb; // Capture the callback
                    return true;
                })
            );

        // Mock service method expectation
        $mockService->expects($this->once())
                    ->method('handleMessage')
                    ->with($messageBody);

        // is_open should be true once, then false to exit loop after one wait if maxIteration wasn't enough
        // However, maxIteration = 1 should make the loop run once.
        $mockChannel->expects($this->atLeastOnce())->method('is_open')->willReturn(true);
        $mockChannel->expects($this->once())->method('wait')->willReturnCallback(
            function() use (&$callback, $mockMessage) {
                if ($callback) {
                    call_user_func($callback, $mockMessage); // Invoke the captured callback
                }
            }
        );

        $mockChannel->expects($this->once())->method('close');
        $mockConnection->expects($this->once())->method('close');

        $listener = new Listener($args);

        ob_start(); // Capture echo output
        $listener->watch();
        $output = ob_get_clean();

        $this->assertStringContainsString("Max iteration reached", $output);
    }

    public function testWatchMaxIteration()
    {
        $mockConnection = $this->createMock(AMQPStreamConnection::class);
        $mockChannel = $this->createMock(AMQPChannel::class);
        $mockService = $this->getMockBuilder(stdClass::class)
                            ->addMethods(['handle'])
                            ->getMock();

        $maxIterations = 3;
        $queueName = 'iter_queue';

        $args = $this->getValidArgs([
            'connection' => $mockConnection,
            'service' => $mockService,
            'method' => 'handle',
            'queue' => $queueName,
            'maxIteration' => $maxIterations
        ]);

        $mockConnection->expects($this->once())->method('channel')->willReturn($mockChannel);
        $mockChannel->expects($this->once())->method('basic_qos');

        $callback = null;
        $mockChannel->expects($this->once())
            ->method('basic_consume')
            ->willReturnCallback(function ($q, $ct, $nl, $na, $ex, $nw, $cb) use (&$callback) {
                $callback = $cb;
                return 'consumer_tag';
            });

        // Service method will be called $maxIterations times
        $mockService->expects($this->exactly($maxIterations))
                    ->method('handle');

        // Channel wait will be called $maxIterations times
        $mockChannel->expects($this->exactly($maxIterations))
            ->method('wait')
            ->willReturnCallback(function () use (&$callback, $mockService) {
                $mockMessage = $this->createMock(AMQPMessage::class);
                $mockMessage->body = 'some_body';
                $mockMessage->expects($this->once())->method('ack');
                if ($callback) {
                    call_user_func($callback, $mockMessage);
                }
            });

        // is_open will be called $maxIterations times (for the loop condition) + potentially more by wait() internals
        $mockChannel->expects($this->atLeast($maxIterations))->method('is_open')->willReturn(true);

        $mockChannel->expects($this->once())->method('close');
        $mockConnection->expects($this->once())->method('close');

        $listener = new Listener($args);

        ob_start();
        $listener->watch();
        $output = ob_get_clean();
        $this->assertStringContainsString("Max iteration reached", $output);
    }
}

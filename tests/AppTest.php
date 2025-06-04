<?php

use chella\amqp\App;
use chella\amqp\Exception\InvalidParams;
use chella\amqp\Service\Listener;
use chella\amqp\Service\Publisher;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PHPUnit\Framework\TestCase;

class AppTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset the singleton instance after each test
        $reflection = new ReflectionClass(App::class);
        $instanceProperty = $reflection->getProperty('app');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);
        $instanceProperty->setAccessible(false);

        $connectionModeProperty = $reflection->getProperty('connectionMode');
        $connectionModeProperty->setAccessible(true);
        $connectionModeProperty->setValue(null, null);
        $connectionModeProperty->setAccessible(false);
    }

    public function testContextCreationSuccess()
    {
        $app = App::context('host', 'user', 'pass', 1234, 'vhost');
        $this->assertInstanceOf(App::class, $app);
    }

    public function testContextInvalidConnectionMode()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("invalid.connection.mode.given");
        App::context('host', 'user', 'pass', 1234, 'vhost', 'invalid_mode');
    }

    public function testContextSingleton()
    {
        $app1 = App::context('host', 'user', 'pass', 1234, 'vhost');
        $app2 = App::context('host', 'user', 'pass', 1234, 'vhost');
        $this->assertSame($app1, $app2);
    }

    public function testContextMissingHost()
    {
        $this->expectException(InvalidParams::class);
        $this->expectExceptionMessage("'host' is missing");
        App::context('', 'user', 'pass', 1234, 'vhost');
    }

    public function testContextMissingPort()
    {
        // Assuming 0 for port is considered "missing" or invalid by current App.php logic
        $this->expectException(InvalidParams::class);
        $this->expectExceptionMessage("'port' is missing");
        App::context('host', 'user', 'pass', 0, 'vhost');
    }

    public function testContextMissingUser()
    {
        $this->expectException(InvalidParams::class);
        $this->expectExceptionMessage("'username' is missing");
        App::context('host', '', 'pass', 1234, 'vhost');
    }

    public function testContextMissingPassword()
    {
        $this->expectException(InvalidParams::class);
        $this->expectExceptionMessage("'password' is missing");
        App::context('host', 'user', '', 1234, 'vhost');
    }

    public function testContextMissingVhost()
    {
        $this->expectException(InvalidParams::class);
        $this->expectExceptionMessage("'vhost' is missing");
        App::context('host', 'user', 'pass', 1234, '');
    }

    public function testContextEmptyHost()
    {
        $this->expectException(InvalidParams::class);
        $this->expectExceptionMessage("'host' is missing");
        App::context(' ', 'user', 'pass', 1234, 'vhost');
    }

    public function testGetConnectionStream()
    {
        $params = ['host' => 'h', 'username' => 'u', 'password' => 'p', 'vhost' => 'vh', 'port' => 1234];

        $appMock = $this->getMockBuilder(App::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['connectWithStream']) // Methods to mock on the App instance
            ->getMock();

        // Set static $connectionMode property using reflection
        $reflectionMode = new ReflectionProperty(App::class, 'connectionMode');
        $reflectionMode->setAccessible(true);
        $reflectionMode->setValue(null, 'stream'); // Set static property for 'stream' mode
        $reflectionMode->setAccessible(false);

        // The App instance needs its 'params' property set, which is normally done by 'validate'
        // Since we disabled constructor (which might call validate or be part of context that calls validate)
        // we ensure 'params' is set on our mock if 'connectWithStream' or 'getConnection' logic path needs it.
        // getConnection itself takes $params, so $appMock->params might not be strictly needed for this specific test method.
        // However, it's good practice if other methods on $appMock were to be called that rely on $this->params.
        // For this test, $appMock->getConnection($params) is called, and $params is passed directly.

        $mockAmqpConnection = $this->createMock(AMQPStreamConnection::class);
        $appMock->expects($this->once())
            ->method('connectWithStream')
            ->with($params) // connectWithStream is called with these params
            ->willReturn($mockAmqpConnection);

        $result = $appMock->getConnection($params); // Call getConnection on our mock
        $this->assertInstanceOf(AMQPStreamConnection::class, $result);
    }

    public function testGetConnectionSsl()
    {
        $params = ['host' => 'h', 'username' => 'u', 'password' => 'p', 'vhost' => 'vh', 'port' => 1234];

        $appMock = $this->getMockBuilder(App::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['connectWithSsl'])
            ->getMock();

        $reflectionMode = new ReflectionProperty(App::class, 'connectionMode');
        $reflectionMode->setAccessible(true);
        $reflectionMode->setValue(null, 'ssl'); // Set static property for 'ssl' mode
        $reflectionMode->setAccessible(false);

        $mockAmqpConnection = $this->createMock(AMQPSSLConnection::class);
        $appMock->expects($this->once())
            ->method('connectWithSsl')
            ->with($params)
            ->willReturn($mockAmqpConnection);

        $result = $appMock->getConnection($params);
        $this->assertInstanceOf(AMQPSSLConnection::class, $result);
    }

    public function testListenCallsListenerWatch()
    {
        // Get a real App instance via context() first, this sets up singleton and params
        // We need to ensure that App::$app is our mock for the testing App::listen behavior
        $appInstanceMock = $this->getMockBuilder(App::class)
            ->disableOriginalConstructor() // Avoid issues with constructor
            ->onlyMethods(['getConnection']) // We want to control what getConnection returns
            ->getMock();

        // Manually set the $this->params property on the mock, as validate() would usually do this.
        // App::publish and App::listen use $this->params indirectly via $this->getConnection($this->params)
        // if no argument is passed to getConnection. App::listen calls $this->getConnection($connectionMode) - this is a bug in App.php
        // App::listen should be $this->getConnection($this->params)
        // For now, let's assume $this->params is needed for $this->getConnection.
        $validParams = ['host'=>'h', 'port'=>1234, 'username'=>'u', 'password'=>'p', 'vhost'=>'v'];
        $appInstanceMock->params = $validParams; // Set dynamic property directly

        // Inject this mock as the singleton App::$app
        $appSingletonProperty = new ReflectionProperty(App::class, 'app');
        $appSingletonProperty->setAccessible(true);
        $appSingletonProperty->setValue(null, $appInstanceMock); // self::$app = $appInstanceMock
        $appSingletonProperty->setAccessible(false);

        // Call App::context() to set static $connectionMode and ensure it returns our injected mock.
        // The parameters to context() here will be used to set App::$connectionMode.
        // The actual instance returned will be $appInstanceMock because we pre-set App::$app.
        $appContext = App::context('host', 'user', 'pass', 1234, 'vhost', 'stream');
        $this->assertSame($appInstanceMock, $appContext, "App::context() did not return the injected mock.");

        $mockConnection = $this->createMock(AMQPStreamConnection::class);
        $mockChannel = $this->createMock(\PhpAmqpLib\Channel\AMQPChannel::class);
        $mockConnection->method('channel')->willReturn($mockChannel);
        // If Listener's watch method or constructor does more with channel like queue_declare, etc.
        // $mockChannel->method('queue_declare')->willReturn([...]);
        // $mockChannel->method('basic_consume')->willReturn([...]);

        // Expect getConnection to be called. With the fix in App.php, it's called with $this->params
        $appInstanceMock->expects($this->once())
                        ->method('getConnection')
                        ->with($validParams)
                        ->willReturn($mockConnection);

        $serviceMock = $this->getMockBuilder(stdClass::class)
                            ->addMethods(['someMethod'])
                            ->getMock();
        // Optionally, expect 'someMethod' to be called if Listener is supposed to do that.
        // $serviceMock->expects($this->once())->method('someMethod');

        try {
            $appInstanceMock->listen($serviceMock, 'someMethod', 'someQueue');
            // If Listener class requires a ->channel() method on the connection:
            // $mockChannel->method('queue_declare')->willReturn([...]);
            // $mockChannel->method('basic_consume')->willReturn([...]);
            $this->assertTrue(true);
        } catch (Error $e) {
            $this->fail("Listen method caused an Error: " . $e->getMessage(). " - Check if AMQPConnection mock needs channel() method etc.");
        } catch (Exception $e) {
            $this->fail("Listen method threw an unexpected exception: " . $e->getMessage());
        }
    }

    public function testPublishCallsPublisherPublish()
    {
        $appInstanceMock = $this->getMockBuilder(App::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection'])
            ->getMock();

        $validParams = ['host'=>'h', 'port'=>1234, 'username'=>'u', 'password'=>'p', 'vhost'=>'v'];
        $appInstanceMock->params = $validParams; // Set dynamic property directly

        $appSingletonProperty = new ReflectionProperty(App::class, 'app');
        $appSingletonProperty->setAccessible(true);
        $appSingletonProperty->setValue(null, $appInstanceMock);
        $appSingletonProperty->setAccessible(false);

        $appContext = App::context('host', 'user', 'pass', 1234, 'vhost', 'stream');
        $this->assertSame($appInstanceMock, $appContext, "App::context() did not return the injected mock.");

        $mockConnection = $this->createMock(AMQPStreamConnection::class);
        $mockChannel = $this->createMock(\PhpAmqpLib\Channel\AMQPChannel::class);
        $mockConnection->method('channel')->willReturn($mockChannel);
        // If Publisher's publish method does more with channel like exchange_declare, etc.
        // $mockChannel->method('exchange_declare')->willReturn([...]);
        // We expect basic_publish to be called on the channel
        $mockChannel->expects($this->once())
                    ->method('basic_publish')
                    ->with($this->isInstanceOf(\PhpAmqpLib\Message\AMQPMessage::class), 'exchange', 'routingKey');


        $appInstanceMock->expects($this->once())
                        ->method('getConnection')
                        ->with($validParams) // Publish uses $this->getConnection($this->params)
                        ->willReturn($mockConnection);

        try {
            $appInstanceMock->publish('message', 'exchange', 'routingKey');
            $this->assertTrue(true); // Verified by mockChannel expectation if basic_publish is called
        } catch (Error $e) {
             $this->fail("Publish method caused an Error: " . $e->getMessage() . " - Check if AMQPConnection mock needs channel() method etc.");
        } catch (Exception $e) {
            $this->fail("Publish method threw an unexpected exception: " . $e->getMessage());
        }
    }
}

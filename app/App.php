<?php
namespace chella\amqp;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use chella\amqp\Exception\InvalidParams;
use chella\amqp\Service\{Listener, Publisher};

class App {
    private static $app;
    private static $connectionMode;
    private static $allowedConnMode = ['stream', 'ssl'];
        
    /**
     * context
     *
     * @param  mixed $host
     * @param  mixed $user
     * @param  mixed $pwd
     * @param  mixed $port
     * @param  mixed $vhost
     * @return Array
     */
    public static function context(
        String $host, String $user, String $pwd, Int $port, 
        String $vhost, String $connectionMode = 'stream'
    ): Object {
        if (in_array($connectionMode, self::$allowedConnMode) === false) {
            throw new Exception("invalid.connection.mode.given");
        }

        self::$connectionMode = $connectionMode;
        if (!self::$app) {
            $obj = new App();
            $conn = [
                'host' => $host, 'username' => $user, 'password' => $pwd, 
                'vhost' => $vhost, 'port' => $port
            ];
            $obj->validate($conn);
            self::$app = $obj;
        }

        return self::$app;
    }
    
    /**
     * validate
     *
     * @param  mixed $conn
     * @return void
     */
    public function validate(Array $params) {
        $requiredKeys = ['host', 'port', 'username', 'password', 'vhost'];
        foreach($requiredKeys as $item) {
            if (
                \array_key_exists($item, $params) === false || 
                empty(trim($params[$item])) === true
            ) {
                $msg = sprintf("'%s' is missing", $item);
                throw new InvalidParams($msg);
            }
        }

        $this->params = $params;
    }
        
    /**
     * connect
     *
     * @param  mixed $conn
     * @return Object
     */
    public function connectWithStream(Array $conn): Object {
        return new AMQPStreamConnection(
            $conn['host'], $conn['port'], $conn['username'], $conn['password'],
            $conn['vhost']
        );
    }

    
    /**
     * connectWithSsl
     *
     * @param  mixed $conn
     * @return Object
     */
    public function connectWithSsl(Array $conn): Object {
        return new AMQPSSLConnection(
            $conn['host'], $conn['port'], $conn['username'], $conn['password'],
            $conn['vhost'], ['verify_peer_name' => false], [], 'ssl'
        );
    }
    
    /**
     * getConnection
     *
     * @param  mixed $params
     * @return Object
     */
    public function getConnection(Array $params): Object {
        if (strtolower(self::$connectionMode) === 'ssl') {
            return $this->connectWithSsl($params);
        }

        return $this->connectWithStream($params);
    }
    
    /**
     * listen
     *
     * @param  mixed $service
     * @param  mixed $method
     * @param  mixed $queue
     * @param  mixed $maxItem
     * @return void
     */
    public function listen(
        Object $service, String $method, String $queue, $maxItem = 10
    ) {
        $connection = $this->getConnection($connectionMode);
        $arg = [
            'connection' => $connection,
            'service' => $service,
            'method' => $method,
            'queue' => $queue,
            'maxIteration' => $maxItem
        ];
        $listener = new Listener($arg);
        $listener->watch();
    }
    
    /**
     * publish
     *
     * @param  mixed $msg
     * @param  mixed $xchange
     * @param  mixed $routingKey
     * @return void
     */
    public function publish(String $msg, String $xchange, String $routingKey = null) {
        $publisher = new Publisher($this->getConnection($this->params));
        $publisher->publish($xchange, $routingKey, $msg);
    }
}
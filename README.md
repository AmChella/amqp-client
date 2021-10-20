# amqp-client

[![Latest Stable Version](https://poser.pugx.org/chella/amqp/v/stable?format=flat-square)](https://packagist.org/packages/chella/amqp)
[![Total Downloads](https://poser.pugx.org/chella/amqp/downloads?format=flat-square)](https://packagist.org/packages/chella/amqp)
[![Latest Unstable Version](https://poser.pugx.org/chella/amqp/v/unstable?format=flat-square)](https://packagist.org/packages/chella/amqp)
[![License](https://poser.pugx.org/chella/amqp/license?format=flat-square)](https://packagist.org/packages/chella/amqp)

## Setup

## Composer

composer require chella/amqp

## Write a service

### include autoload

```
require_once "./vendor/autoload.php";
```

### import service

```
use chella\amqp\App;
```

### create instance

```
1st arg is hostname
2nd arg is username
3rd arg is password
4th arg is port (optional)
5th arg is vhost (optional)

$context = App::context('localhost', 'guest', 'guest', 5672, '/');
```

### publish message

```
1st arg is message
2nd arg is exchange
3rd arg is routing_key

$context->publish('hello world', 'test', 'test_routing');
```

Happy coding

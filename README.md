# Redis Priority Queue Client

RPQ-Client is a priority queue implementation in Redis written in pure PHP.

## Installation

RPQ-Client can be added to your application via [Composer](https://getcomposer.org/).

```
composer require rpq/client
```

> Note that RPQ-Client requires [PHPRedis](https://github.com/phpredis/phpredis).

## Usage

```php
// Create a new Redis instance
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

$rqp = new RQP\Client($redis);
//$rpq = new RPQ\Client($redis, 'namespace');
// Push a new task called `Ping` to the priority queue with the default priority
$rqp->push('Ping');

// Alternatively, we can specify args, the queue name, a different priority, and whether or not RQP-Server should attempt to retry the job if it fails.
$args = [
    'arg1',
    'arg2',
    'arg3' => true
];
$retry = true;
$queueName = 'queue';
$priority = 3;

// Push a new task onto the priority queue, get a UUIDv4 JobID back in response
$jobId = $rqp->push('Worker', $args, $retry, $priority, $queueName);

// The front element of the queue can be popped, this returns the entire job as an array
$job = $rqp->pop();
```
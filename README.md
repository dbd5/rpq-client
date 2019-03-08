# Redis Priority Queue Client

[![Travis CI](https://img.shields.io/travis/charlesportwoodii/rpq-client.svg?style=flat-square)](https://travis-ci.com/charlesportwoodii/rpq-client)

RPQ-Client is a priority task queue implementation in Redis written in pure PHP. This repository contains the Client codebase which can be used to schedule jobs from applications. Additionally, this codebase is used by the [RPQ Server](https://github.com/charlesportwoodii/rqp-server) implementation to work with and process jobs.

> Note that this codebase is constantly evolving. Until a tagged release is made, the API may change at any time.

## Installation

RPQ-Client can be added to your application via [Composer](https://getcomposer.org/).

```
composer require rpq/client
```

> Note that RPQ-Client requires [PHPRedis](https://github.com/phpredis/phpredis).

## Usage

The RPQ Client comes with several options to instantiate the queue. To begin using the RPQ client, connect to your Redis instance, then pass that Redis instance to the `RPQ\Client` object.

```php
// Create a new Redis instance
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

// Create a new client object
$client = new RQP\Client($redis);
//$rpq = new RPQ\Client($redis, 'namespace');

// Returns the queue object for interaction
$queue = $client->getQueue();
// $queue = $client->getQueue('another-queue-name');
```

### Adding Jobs

Jobs can be scheduled for immediate execution simply by pushing a Fully Qualified Class Name to the queue.

```php
// Push a new task called `Ping` to the priority queue with the default priority
$queue->push('Ping');
```

Job arguments can be a complex array. As long as the details are JSON serializable, it can be passed to RPQ. Jobs have a default priority of `1`. Jobs with a higher priority will execute before jobs with a lower priority. The priority may range from `PHP_INT_MIN` to `PHP_INT_MAX`.

Retries may either be defined as a `boolean` or as an `integer`. If `retry` is set to `true`, the job will be continuously rescheduled until it passes. If `retry` is set to `false`, no attempt will be made to retry the job. If `retry` is set to an integer, _exactly_ `n` retries will be attempted, after which the job will be failed.

After pushing a job onto the stack, the `push` method will return a `Job` instance which can be used to determine the status and other information of the job.

```php
// Alternatively, we can specify args, the queue name, a different priority, and whether or not RQP-Server should attempt to retry the job if it fails.
$args = [
    'arg1',
    'arg2' => [
        'stuff' => 1,
        'more' => false,
        'foo' => 'bar'
    ],
    'arg3' => true
];
$retry = true;
$priority = 3;

// Push a new task onto the priority queue, get a UUIDv4 JobID back in response
$job = $queue->push('Worker', $args, $retry, $priority);
```

> Note that complex Objects should _NOT_ be passed as an arguement. If you need to access a complex object, you should re-instantiate it within your job class.

#### Future Scheduling

Jobs may be scheduled in the future by specifying the `at` parameter, which represents a unix timestamp of the time you wish for the job to execute at.

```php
$at = \strtotime('+1 hour');

$job = $queue->push('Worker', $args, $retry, $priority, $at);
```

> Note that the `at` parameter declares the _earliest_ a job will execute, and does not guarantee that a job will execute at that time. The scheduler will prioritize future jobs when possible, but other jobs may have priority over it depending upon the priority.
> If you require exact timining, the job should have a priority of `PHP_MAX_INT`, and you should ensure that your job queue has sufficient workers to prevent the job execution from being delayed.

### Queue Statistics

Details about the queue can be retrieved as follows:

```php
$queue->getStats()->get();
```

The stats command will return an array containing the number of elements in the queue, and details about the passed, failed, canceled, and retried jobs for the given day.

To retrieve stats for a different day, call `get()` with a `Y-m-d` formatted date.

### Job Details

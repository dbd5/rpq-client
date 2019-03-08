<?php

namespace RPQ;

use Redis;
use RPQ\Queue;
use RPQ\Job;

final class QueueTest extends AbstractTest
{
    protected $queue;

    public function setUp()
    {
        parent::setUp();
        $this->queue = $this->client->getQueue();
        $this->assertInstanceOf(Queue::class, $this->queue);
    }

    public function testPushAndPop()
    {
        $workerClass = 'SimpleWorker';
        $retry = true;
        $args = [
            'foo' => 'bar',
            [
                1 => 2,
                'foo' => true,
                'bar' => false
            ]
        ];
        $priority = 5;
        $job = $this->queue->push($workerClass, $args, $retry, $priority);

        $this->assertInstanceOf(Job::class, $job);
        $this->assertEquals($job->getArgs(), $args);
        $this->assertEquals($job->getWorkerClass(), $workerClass);
        $this->assertEquals($job->getRetry(), $retry);
        $this->assertEquals($job->getPriority(), $priority);
        $this->assertNotNull($job->getId());

        $job2 = $this->queue->pop();
        $this->assertInstanceOf(Job::class, $job2);
        $this->assertEquals($job2->getArgs(), $args);
        $this->assertEquals($job2->getWorkerClass(), $workerClass);
        $this->assertEquals($job2->getRetry(), $retry);
        $this->assertEquals($job2->getPriority(), $priority);
        $this->assertNotNull($job2->getId());
    }
}

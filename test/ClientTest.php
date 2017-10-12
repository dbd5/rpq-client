<?php

namespace RPQ;

use Redis;
use RPQ\Client;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private $redis;

    private $rpq;

    protected function setUp()
    {
        $this->redis = new Redis();
        $this->redis->connect(getenv('REDIS'));

        // Delete the existing queue
        $this->redis->del("queue:default");
        $this->rpq = new Client($this->redis);
    }

    public function testPushAndPop()
    {
        $jobId = $this->rpq->push('SimpleWorker');
        $this->assertNotNull($jobId);
        $job = $this->rpq->pop();
        $this->assertEquals($jobId, $job['jobId']);
        $this->assertEquals('SimpleWorker', $job['class']);
    }
}
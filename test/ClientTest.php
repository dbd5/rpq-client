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
        $jobName = 'SimpleWorker';
        $retry = 1;
        $args = [
            'foo' => 'bar'
        ];
        $jobId = $this->rpq->push($jobName, $args, $retry);
        $this->assertNotNull($jobId);
        $id = $this->rpq->pop();
        $this->assertEquals($jobId, explode(':', $id)[2]);

        $job = $this->rpq->getJobById(explode(':', $id)[2]);
        $this->assertEquals($jobName, $job['workerClass']);
        $this->assertEquals($retry, \explode(':', $job['retry'])[1]);
        $this->assertEquals($args, $job['args']);

    }
}
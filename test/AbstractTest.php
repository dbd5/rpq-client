<?php

namespace RPQ;

use Redis;
use PHPUnit\Framework\TestCase;

abstract class AbstractTest extends TestCase
{
    protected $redis;

    protected $client;

    protected function setUp()
    {
        $this->redis = new Redis();
        $this->redis->connect(getenv('REDIS'));
        // Delete the existing queue
        $this->redis->del("queue:default");
        $this->client = new Client($this->redis);
    }
}

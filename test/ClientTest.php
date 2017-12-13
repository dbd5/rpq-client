<?php

namespace RPQ;

use Redis;
use RPQ\Queue;

final class ClientTest extends AbstractTest
{
    public function testGetQueue()
    {
        $queue = $this->client->getQueue();
        $this->assertInstanceOf(Queue::class, $queue);
    }
}
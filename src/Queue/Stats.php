<?php declare(strict_types=1);

namespace RPQ\Queue;

use RPQ\Queue;
use Redis;

final class Stats
{
    /**
     * The queue
     * @var Queue
     */
    private $queue;

    /**
     * Constructor
     *
     * @param Queue $queue
     */
    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }
}
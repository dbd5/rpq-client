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

    /**
     * Retrieves stats for a given date
     *
     * @param string $date
     * @return void
     */
    public function get(string $date = null) : array
    {
        if ($date === null) {
            $date = \date('Y-m-d');
        }
        
        return [
            'queued' => $this->queue->count(),
            'pass' => $this->queue->getStatsByType($date, 'pass'),
            'fail' => $this->queue->getStatsByType($date, 'fail'),
            'retry' => $this->queue->getStatsByType($date, 'retry'),
            'cancel' => $this->queue->getStatsByType($date, 'cancel')
        ];
    }
}

<?php declare(strict_types=1);

namespace RPQ;

use RPQ\Exception\JobNotFoundException;
use RPQ\Exception\FailedToCreateJobIdException;
use RPQ\Queue\Stats;
use RPQ\Job;
use Redis;

/**
 * Represents actions that can be performed directly against a given queue by name
 * @class RPQ\Queue
 */
final class Queue
{
    /**
     * The queue name
     * 
     * @var string
     */
    private $name = 'default';

    /**
     * The Client object
     *
     * @var Client $client
     */
    private $client;

    /**
     * The client object
     *
     * @return Client $client
     */
    public function getClient() : Client
    {
        return $this->client;
    }

    /**
     * Rreturns the queue name
     *
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Constructor
     *
     * @param string $queueName
     * @param Client $client
     */
    public function __construct($name = 'default', Client $client)
    {
        $this->name = $name;
        $this->client = $client;
    }

    /**
     * Returns queue statistics
     *
     * @return Stats
     */
    public function getStats()
    {
        return new Stats($this);
    }

    /**
     * Returns a job object for interaction
     * 
     * @param string $jobId
     * @return Job
     */
    public function getJob($jobId)
    {
        return new Job($this, $jobId);
    }

    /**
     * Pushes a new job onto the priority queue
     *
     * @param string $workerClass
     * @param array $args
     * @param boolean|integer $retry
     * @param integer $priority
     * @param integer $at
     * @param string $jobId
     * @return Job
     */
    public function push($workerClass, array $args = [], $retry = false, $priority = 0, $at = null, $jobId = null) :? Job
    {
        $job = new Job($this, $jobId);

        $status = $job->schedule($workerClass, $args, $retry, $priority, $at);

        if ($status) {
            return $job;
        }

        return false;
    }

    /**
     * Pops the highest priority item off of the priority queue
     *
     * @return Job
     */
    public function pop() :? Job
    {
        $key = $this->generateListKey();

        $element = $this->getFirstElementFromQueue($key);
        if ($element === null) {
            return null;
        }

        // Atomic ZPOP
        $this->getClient()->getRedis()->watch($key);
        while (!$this->getClient()->getRedis()->multi()->zrem($key, $element)->exec()) {
            $element = $this->getFirstElementFromQueue($key);
        }

        $this->getClient()->getRedis()->unwatch($key);

        $ref = \explode(':', $element);
        $jobId = \end($ref);
        return new Job($this, $jobId);
    }

    /**
     * Takes jobs from the schuled queue with a ZSCORE <= $time, and pushes it onto the main stack
     * @param string $time
     * @return boolean
     */
    public function rescheduleJobs($time = null) : bool
    {
        if ($time === null) {
            $time = (string)time();
        }

        $key = $this->generateListKey();

        $redis = $this->getClient()->getRedis();
        $k = $key . '-scheduled';
        $redis->watch($k);
        $result = $redis->multi()
            ->zrevrangebyscore($k, $time, "0")
            ->zremrangebyscore($k, "0", $time)
            ->exec();
        $redis->unwatch($k);

        if ($result[0] === false) {
            return true;
        }
        
        foreach ($result[0] as $job) {
           $redis->zincrby($key, 0, $job);
        }

        return $result[1];
    }

    /**
     * Gets the head element off of the list
     *
     * @param string $key
     * @return array
     */
    private function getFirstElementFromQueue($key) :? string
    {
        $result = $this->getClient()->getRedis()->zrevrange($key, 0, 0);
        if (empty($result)) {
            return null;
        }

        return $result[0];
    }

    /**
     * Generates a unique key for the job
     *
     * @return string
     */
    public function generateListKey() : string
    {
        $filter = [
            $this->getClient()->getNamespace(),
            'queue',
            \str_replace(':', '.', $this->name)
        ];
        $parts = \array_filter($filter, 'strlen');
        return \implode(':', $parts);
    }
}
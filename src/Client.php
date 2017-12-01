<?php declare(strict_types=1);

namespace RPQ;

use RPQ\Client\Exception\JobNotFoundException;
use RPQ\Client\Exception\FailedToCreateJobIdException;
use Redis;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

/**
 * @class RQP\Client
 */
final class Client
{
    /**
     * Redis instance
     * @var Redis
     */
    private $redis;

    /**
     * Redis namespace
     * @var string
     */
    private $namespace;

    /**
     * Constructor
     *
     * @param Redis $redis
     * @param string $namespace
     */
    public function __construct(Redis $redis, $namespace = null)
    {
        $this->redis = $redis;
        $this->namespace = $namespace;
    }

    /**
     * Retrieves the Redis instance
     *
     * @return Redis
     */
    public function getRedis()
    {
        return $this->redis;
    }

    /**
     * Pushes a new job onto the priority queue
     *
     * @param string $workerClass
     * @param array $args
     * @param boolean|integer $retry
     * @param integer $priority
     * @param string $queueName
     * @param integer $at
     * @param string $jobId
     * @return string
     */
    public function push($workerClass, array $args = [], $retry = false, $priority = 0, $queueName = 'default', $at = null, $jobId = null) : string
    {
        if ($jobId === null) {
            try {
                $uuid = Uuid::uuid4();
                $jobId = $uuid->toString();
            } catch (UnsatisfiedDependencyException $e) {
                throw new FailedToCreateJobIdException('An error occured when generating the JobID');
            }
        }

        $key = $this->generateListKey($queueName);

        $id = "$key:$jobId";
        $result = $this->redis->multi()
            ->hMset($id, [
                'workerClass' => $workerClass,
                'retry' => \gettype($retry) . ':' . $retry,
                'priority' => $at ?? $priority,
                'args' => \json_encode($args)
            ])
            ->zincrby(($at === null ? $key : $key . '-scheduled'), ($at ?? $priority), $id)
            ->exec();

        return $jobId;
    }

    /**
     * Retrieves a job by it's ID and queue name
     *
     * @param string $id
     * @param string $queueName
     * @return array
     */
    public function getJobById($id, $queueName = 'default') : array
    {
        $key = $this->generateListKey($queueName);
        $job = $this->redis->hGetAll("$key:$id");

        if (empty($job)) {
            throw new JobNotFoundException('Unable to fetch job details from Redis.');
        }

        $job['args'] = \json_decode($job['args'], true);
        return $job;
    }

    /**
     * Pops the highest priority item off of the priority queue
     *
     * @param string $queueName
     * @return array
     */
    public function pop($queueName = 'default') :? string
    {
        $key = $this->generateListKey($queueName);

        $element = $this->getFirstElementFromQueue($key);
        if ($element === null) {
            return null;
        }

        // Atomic ZPOP
        $this->redis->watch($key);
        while (!$this->redis->multi()->zrem($key, $element)->exec()) {
            $element = $this->getFirstElementFromQueue($key);
        }

        $this->redis->unwatch($key);

        return $element;
    }

    /**
     * Takes jobs from the schuled queue with a ZSCORE <= $time, and pushes it onto the main stack
     * @param string $queueName
     * @param string $time
     * @return integer
     */
    public function rescheduleJobs($queueName = 'default', $time = null) : int
    {
        if ($time === null) {
            $time = (string)time();
        }

        $key = $this->generateListKey($queueName);

        $k = $key . '-scheduled';
        $this->redis->watch($k);
        $result = $this->redis->multi()
            ->zrevrangebyscore($k, $time, "0")
            ->zremrangebyscore($k, "0", $time)
            ->exec();
        $this->redis->unwatch($k);

        foreach ($result[0] as $job) {
            $this->redis->zincrby($key, 0, $job);
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
        $result = $this->redis->zrevrange($key, 0, 0);
        if (empty($result)) {
            return null;
        }

        return $result[0];
    }

    /**
     * Generates a unique key for the job
     *
     * @param string $queueName
     * @return string
     */
    private function generateListKey($queueName) : string
    {
        $filter = [
            $this->namespace,
            'queue',
            \str_replace(':', '.', $queueName)
        ];
        $parts = \array_filter($filter, 'strlen');
        return \implode(':', $parts);
    }
}
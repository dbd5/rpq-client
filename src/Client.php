<?php declare(strict_types=1);

namespace RPQ;

use Redis;
use Exception;

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
     * Pushes a new job onto the priority queue
     *
     * @param string $workerClass
     * @param array $args
     * @param boolean $retry
     * @param integer $priority
     * @param string $queueName
     * @return string
     */
    public function push($workerClass, array $args = [], $retry = false, $priority = 0, $queueName = 'default') : string
    {
        try {
            $uuid = Uuid::uuid4();
            $jobId = $uuid->toString();
        } catch (UnsatisfiedDependencyException $e) {
            throw new Exception('An error occured when generating the JobID');
        }

        $key = $this->generateListKey($queueName);

        $this->redis->hMset("$key:$jobId", [
            'workerClass' => $workerClass,
            'retry' => (int)$retry,
            'args' => \json_encode($args)
        ]);
        $this->redis->zincrby($key, $priority, "$key:$jobId");

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
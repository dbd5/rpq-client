<?php declare(strict_types=1);

namespace RPQ;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
use Redis;

final class Job
{
    private $queue;

    private $id;

    private $args = [];

    private $workerClass;

    private $priority = 1;

    private $retry = false;

    /**
     * Constructor
     *
     * @param string $queueName
     * @param Redis $redis
     */
    public function __construct(Queue $queue, $jobId = null)
    {
        $this->queue = $queue;

        if ($jobId !== null) {
            $this->id = $jobId;
            $this->hydrate();
        }
    }

    /**
     * Returns the job ID if defined
     *
     * @return string|null
     */
    public function getId() :? string
    {
        return $this->id;
    }

    /**
     * Returns the fully-qualified class name of the job
     * 
     * @return string|null
     */
    public function getWorkerClass() :? string
    {
        return $this->workerClass;
    }

    /**
     * Returns the job arguements
     * 
     * @return array|null
     */
    public function getArgs() :? array
    {
        return $this->args;
    }

    /**
     * Returns the priority of the job
     * 
     * @return integer|null
     */
    public function getPriority() :? float
    {
        return (int)$this->priority;
    }

    /**
     * Returns the retry status of the job
     * 
     * @return boolean|integer|null
     */
    public function getRetry()
    {
        return $this->retry;
    }

    /**
     * Pushes a new job onto the priority queue
     *
     * @param string $workerClass
     * @param array $args
     * @param boolean|integer $retry
     * @param integer $priority
     * @param integer $at
     * @return boolean
     */
    public function schedule($workerClass, array $args = [], $retry = false, $priority = 0, $at = null)
    {
        $key = $this->queue->generateListKey();
        if ($this->id === null) {
            try {
                $uuid = Uuid::uuid4();
                $jobId = $uuid->toString();
                $this->id = $jobId;
            } catch (UnsatisfiedDependencyException $e) {
                throw new FailedToCreateJobIdException('An error occured when generating the JobID');
            }
        }
        $id = "$key:" . $this->id;

        $result = $this->queue->getClient()->getRedis()->multi()
            ->hMset($id, [
                'workerClass' => $workerClass,
                'retry' => \gettype($retry) . ':' . $retry,
                'priority' => $at ?? $priority,
                'args' => \json_encode($args)
            ])
            ->zincrby(($at === null ? $key : $key . '-scheduled'), ($at ?? $priority), $id)
            ->exec();
        
        // If the queue and hash were added, hydrate the model then return true
        if ($result[0] === true) {
            $this->workerClass = $workerClass;
            $this->args = $args;
            $this->retry = $retry;
            $this->priority = $priority;
            return true;
        } else {
            // If the job was not added to the queue, remove the hash
            $this->cancel();
            return false;
        }

        return false;
    }

    /**
     * Cancels a job from executing
     * 
     * @return boolean
     */
    public function cancel()
    {
        if ($this->id === null) {
            return false;
        }
        $key = $this->queue->generateListKey();
        $id = "$key:" . $this->id;

        // Delete the job from the current and scheduled queue, then remove the job details from Redis
        $result = $this->queue->getClient()->getRedis()->multi()
            ->zrem($key)
            ->zrem($key . '-scheduled')
            ->del($id)
            ->exec();
        
        $this->workerClass = null;
        $this->args = [];
        $this->retry = null;
        $this->priority = null;
        return $result[0] === true || $result[1] === true;
    }

    /**
     * Hydrates the model by pulling data from Redis
     */
    private function hydrate()
    {
        $key = $this->queue->generateListKey();
        $job = $this->queue->getClient()->getRedis()->hGetAll("$key:" . $this->id);

        if (empty($job)) {
            throw new JobNotFoundException('Unable to fetch job details from Redis.');
        }

        $this->workerClass = $job['workerClass'];
        $this->args = \json_decode($job['args'], true);
        $this->priority = $job['priority'];

        $r = \explode(':', $job['retry']);
        if ($r[0] === 'boolean') {
            $this->retry = (bool)$r[1];
        } else if ($r[0] === 'integer') {
            $this->retry = (int)$r[1];
        }
    }
}
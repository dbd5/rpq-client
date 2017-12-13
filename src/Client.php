<?php declare(strict_types=1);

namespace RPQ;

use RPQ\Queue;
use Redis;

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
     * Returns the namespace
     *
     * @return string
     */
    public function getNamespace() :? string
    {
        return $this->namespace;
    }

    /**
     * Retrieves the Redis instance
     *
     * @return Redis
     */
    public function getRedis() : Redis
    {
        return $this->redis;
    }

    /**
     * Returns a queue object which can be used to interact with a given queue
     *
     * @param string $queueName
     * @return void
     */
    public function getQueue($queueName = 'default')
    {
        return new Queue($queueName, $this);
    }
}
<?php declare(strict_types=1);

namespace RPQ;

/**
 * @class RPQ\Serializer
 */
final class Serializer
{
    /**
     * Serializes a given message into a JSON encoded message
     *
     * @param string $workerClass
     * @param array $args
     * @param boolean $retry
     * @param integer $priority
     * @return string
     */
    public function serialize($workerClass, $jobId, array $args = [], $retry = false, $priority = 1) : string
    {
        return \json_encode([
            'jobId' => $jobId,
            'class' => $workerClass,
            'args' => $args,
            'retry' => $retry,
            'priority' => $priority
        ]);
    }

    /**
     * Deserializes a message
     *
     * @param string $message
     * @return array
     */
    public function deserialize($message) : array
    {
        return \json_decode($message, true);
    }
}
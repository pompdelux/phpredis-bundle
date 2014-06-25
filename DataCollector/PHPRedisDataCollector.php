<?php

namespace Pompdelux\PHPRedisBundle\DataCollector;

use Pompdelux\PHPRedisBundle\Logger\Logger;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHPRedisDataCollector
 */
class PHPRedisDataCollector extends DataCollector
{
    /**
     * @var PHPRedisLogger
     */
    protected $logger;

    /**
     * Constructor.
     *
     * @param PHPRedisLogger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = array(
            'commands' => null !== $this->logger ? $this->logger->getCommands() : array(),
        );
    }

    /**
     * Returns an array of collected commands.
     *
     * @return array
     */
    public function getCommands()
    {
        return $this->data['commands'];
    }

    /**
     * Returns the number of collected commands.
     *
     * @return integer
     */
    public function getCommandCount()
    {
        return count($this->data['commands']);
    }

    /**
     * Returns the execution time of all collected commands in seconds.
     *
     * @return float
     */
    public function getTime()
    {
        $time = 0;
        foreach ($this->data['commands'] as $command) {
            $time += $command['executionMS'];
        }

        return $time;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'phpredis';
    }
}

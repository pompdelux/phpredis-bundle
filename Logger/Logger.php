<?php

namespace Pompdelux\PHPRedisBundle\Logger;

use Psr\Log\LoggerInterface;

/**
 * Logger
 */
class Logger
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var int
     */
    protected $commandCount = 0;

    /**
     * @var array
     */
    protected $commands = array();


    /**
     * Constructor.
     *
     * @param LoggerInterface $logger A LoggerInterface instance
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }


    /**
     * Logs a command
     *
     * @param string      $command    Redis command
     * @param float       $duration   Duration in milliseconds
     * @param string      $connection Connection alias
     * @param bool|string $error      Error message or false if command was successful
     */
    public function logCommand($command, $duration, $connection, $error = false)
    {
        ++$this->commandCount;

        if (null !== $this->logger) {
            $this->commands[] = [
                'cmd' => $command,
                'executionMS' => $duration,
                'conn' => $connection,
                'error' => $error
            ];

            if ($error) {
                $this->logger->error('Command "' . $command . '" failed (' . $error . ')');
            } else {
                $this->logger->debug('Executing command "' . $command . '"');
            }
        }
    }


    /**
     * Returns the number of logged commands.
     *
     * @return integer
     */
    public function getNbCommands()
    {
        return $this->commandCount;
    }


    /**
     * Returns an array of the logged commands.
     *
     * @return array
     */
    public function getCommands()
    {
        return $this->commands;
    }


    /**
     * Forward logger calls to the Logger class
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, array $parameters = [])
    {
        if (method_exists($this->logger, $method)) {
            return call_user_func_array([$this->logger, $method], $parameters);
        }
    }
}

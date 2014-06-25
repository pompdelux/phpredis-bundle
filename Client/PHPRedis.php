<?php

namespace Pompdelux\PHPRedisBundle\Client;

use Pompdelux\PHPRedisBundle\Logger\Logger;

/**
 * Class Redis
 *
 * This Resis class extends \Resis to allow us extra control over the object.
 * Adds logging and DataCollector to the mix.
 *
 * @package Hanzo\Bundle\RedisBundle\Client
 */
class PHPRedis extends \Redis
{
    /**
     * Redis instance
     * @var Redis
     */
    private $redis = null;

    /**
     * Logger
     * @var Logger
     */
    private $logger = null;

    /**
     * Whether or not we are connected to the redis server
     * @var boolean
     */
    private $connected = false;

    /**
     * Redis connection parameters
     * @var array
     */
    private $parameters = [];

    /**
     * Name/label of the connection, used for logging
     * @var string
     */
    private $name;


    /**
     * constructor method
     *
     * @param string $name        Name of the connection
     * @param string $environment The kernel environment variable
     * @param array  $parameters  Configuration parameters
     */
    public function __construct($name, $environment, array $parameters = [])
    {
        parent::__construct();

        if ($parameters['skip_env']) {
            $prefix = $name.':';
        } else {
            $prefix = $name.'.'.$environment.':';
        }

        if (empty($parameters['prefix'])) {
            $parameters['prefix'] = $prefix;
        } else {
            $parameters['prefix'] .= '.'.$prefix;
        }

        $this->name = $name;
        $this->parameters = $parameters;
    }


    /**
     * Get the current prefix
     *
     * @return mixed
     */
    public function getPrefix()
    {
        return $this->parameters['prefix'];
    }

    /**
     * Set prefix
     *
     * @param string $s
     */
    public function setPrefix($s)
    {
        $this->parameters['prefix'] = $s;

        if ($this->connected) {
            $this->setOption(\Redis::OPT_PREFIX, $this->parameters['prefix']);
        }
    }

    /**
     * setup logging
     *
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }


    /**
     * wrap all redis calls to handle profiling
     *
     * @param string $name      Redis method
     * @param array  $arguments Arguments to parse on the real method
     *
     * @return boolean
     * @throws \InvalidArgumentException    If the command called does not exist
     * @throws RedisCommunicationException If the call to redis fails
     */
    public function makeCall($name, array $arguments = [])
    {
        if (!$this->connected) {
            $this->_connect();
        }

        // ignore connect commands
        if (in_array($name, ['connect', 'pconnect'])) {
            return;
        }

        $log = true;
        switch (strtolower($name)) {
            case 'open':
            case 'popen':
            case 'close':
            case 'setoption':
            case 'getoption':
            case 'auth':
            case 'select':
                $log = false;
                break;
        }


        $ts = microtime(true);

        $result = call_user_func_array('parent::'.$name, $arguments);
        $ts = (microtime(true) - $ts) * 1000;

        $isError = false;
        if ($error = $this->getLastError()) {
            $this->clearLastError();
            $isError = true;
        }

        if ($log && null !== $this->logger) {
            $this->logger->logCommand($this->getCommandString($name, $arguments), $ts, $this->name, $isError);
        }

        if ($isError) {
            throw new RedisCommunicationException('Redis command failed: '.$error);
        }

        return $result;
    }


    /**
     * Generate cache key
     *
     * @return string
     */
    public function generateKey()
    {
        $arguments = func_get_args();

        if (is_array($arguments[0])) {
            $arguments = $arguments[0];
        }

        return implode(':', $arguments);
    }


    /**
     * handle connection management
     *
     * @param boolean $bailOnError keeps track on connection retry
     *
     * @return boolean
     * @throws RedisCommunicationException If connection fails
     */
    protected function _connect($bailOnError = false)
    {
        $this->connected = $this->connect(
            $this->parameters['host'],
            $this->parameters['port'],
            ($this->parameters['timeout'] ?: 0)
        );

        if ($this->connected) {
            if ($this->parameters['auth']) {
                $this->auth($this->parameters['auth']);
            }

            if ($this->select($this->parameters['database'])) {
                // setup default options
                $this->setOption(\Redis::OPT_PREFIX, $this->parameters['prefix']);
                $this->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);

                return true;
            }
        } elseif (!$bailOnError) {
            // try to re-connect, but only once.
            usleep(1000);
            $this->_connect(true);
        }

        $error = $this->getLastError();
        $this->clearLastError();

        if ($this->logger) {
            $this->logger->err('Could not connect to redis server (' . $error . ')');
        }

        throw new RedisCommunicationException('Could not connect to Redis: '.$error);
    }


    /**
     * Returns a string representation of the given command including arguments
     *
     * @param string $command   A command name
     * @param array  $arguments An array of command arguments
     *
     * @return string
     */
    private function getCommandString($command, array $arguments)
    {
        $list = [];
        foreach ($arguments as $argument) {
            $list[] = is_scalar($argument) ? $argument : '[.. complex type ..]';
        }

        return mb_substr(trim(strtoupper($command) . ' ' . $this->getPrefix() . implode(' ', $list)), 0, 256);
    }


    /**
     * {@inheritDoc}
     */
    public function close()
    {
        return $this->makeCall('close', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function ping()
    {
        return $this->makeCall('ping', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function get($key)
    {
        return $this->makeCall('get', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function set($key, $value, $timeout = 0.0)
    {
        return $this->makeCall('set', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function setex($key, $ttl, $value)
    {
        return $this->makeCall('setex', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function psetex($key, $ttl, $value)
    {
        return $this->makeCall('psetex', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function setnx($key, $value)
    {
        return $this->makeCall('setnx', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getSet($key, $value)
    {
        return $this->makeCall('getSet', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function randomKey()
    {
        return $this->makeCall('randomKey', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function renameKey($srcKey, $dstKey)
    {
        return $this->makeCall('renameKey', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function renameNx($srcKey, $dstKey)
    {
        return $this->makeCall('renameNx', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getMultiple(array $keys)
    {
        return $this->makeCall('getMultiple', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function exists($key)
    {
        return $this->makeCall('exists', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function delete($key1)
    {
        return $this->makeCall('delete', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function incr($key)
    {
        return $this->makeCall('incr', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function incrBy($key, $value)
    {
        return $this->makeCall('incrBy', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function incrByFloat($key, $increment)
    {
        return $this->makeCall('incrByFloat', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function decr($key)
    {
        return $this->makeCall('decr', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function decrBy($key, $value)
    {
        return $this->makeCall('decrBy', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function type($key)
    {
        return $this->makeCall('type', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function append($key, $value)
    {
        return $this->makeCall('append', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getRange($key, $start, $end)
    {
        return $this->makeCall('getRange', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function setRange($key, $offset, $value)
    {
        return $this->makeCall('setRange', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getBit($key, $offset)
    {
        return $this->makeCall('getBit', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function setBit($key, $offset, $value)
    {
        return $this->makeCall('setBit', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function strlen($key)
    {
        return $this->makeCall('strlen', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getKeys($pattern)
    {
        return $this->makeCall('getKeys', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sort($key, $option = null)
    {
        return $this->makeCall('sort', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sortAsc()
    {
        return $this->makeCall('sortAsc', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sortAscAlpha()
    {
        return $this->makeCall('sortAscAlpha', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sortDesc()
    {
        return $this->makeCall('sortDesc', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sortDescAlpha()
    {
        return $this->makeCall('sortDescAlpha', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lPush($key, $value1, $value2 = null, $valueN = null)
    {
        return $this->makeCall('lPush', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function rPush($key, $value1, $value2 = null, $valueN = null)
    {
        return $this->makeCall('rPush', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lPushx($key, $value)
    {
        return $this->makeCall('lPushx', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function rPushx($key, $value)
    {
        return $this->makeCall('rPushx', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lPop($key)
    {
        return $this->makeCall('lPop', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function rPop($key)
    {
        return $this->makeCall('rPop', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function blPop(array $keys)
    {
        return $this->makeCall('blPop', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function brPop(array $keys)
    {
        return $this->makeCall('brPop', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lSize($key)
    {
        return $this->makeCall('lSize', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lRemove($key, $value, $count)
    {
        return $this->makeCall('lRemove', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function listTrim($key, $start, $stop)
    {
        return $this->makeCall('listTrim', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lGet($key, $index)
    {
        return $this->makeCall('lGet', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lGetRange($key, $start, $end)
    {
        return $this->makeCall('lGetRange', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lSet($key, $index, $value)
    {
        return $this->makeCall('lSet', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lInsert($key, $position, $pivot, $value)
    {
        return $this->makeCall('lInsert', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sAdd($key, $value1, $value2 = null, $valueN = null)
    {
        return $this->makeCall('sAdd', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sSize()
    {
        return $this->makeCall('sSize', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sRemove($key, $member1, $member2 = null, $memberN = null)
    {
        return $this->makeCall('sRemove', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sMove($srcKey, $dstKey, $member)
    {
        return $this->makeCall('sMove', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sPop($key)
    {
        return $this->makeCall('sPop', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sRandMember($key)
    {
        return $this->makeCall('sRandMember', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sContains($key, $value)
    {
        return $this->makeCall('sContains', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sMembers($key)
    {
        return $this->makeCall('sMembers', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sInter($key1, $key2, $keyN = null)
    {
        return $this->makeCall('sInter', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sInterStore($dstKey, $key1, $key2, $keyN = null)
    {
        return $this->makeCall('sInterStore', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sUnion($key1, $key2, $keyN = null)
    {
        return $this->makeCall('sUnion', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sUnionStore($dstKey, $key1, $key2, $keyN = null)
    {
        return $this->makeCall('sUnionStore', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sDiff($key1, $key2, $keyN = null)
    {
        return $this->makeCall('sDiff', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sDiffStore($dstKey, $key1, $key2, $keyN = null)
    {
        return $this->makeCall('sDiffStore', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function setTimeout($key, $ttl)
    {
        return $this->makeCall('setTimeout', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function save()
    {
        return $this->makeCall('save', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function bgSave()
    {
        return $this->makeCall('bgSave', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lastSave()
    {
        return $this->makeCall('lastSave', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function flushDB()
    {
        return $this->makeCall('flushDB', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function flushAll()
    {
        return $this->makeCall('flushAll', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function dbSize()
    {
        return $this->makeCall('dbSize', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function auth($password)
    {
        return $this->makeCall('auth', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function ttl($key)
    {
        return $this->makeCall('ttl', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function pttl($key)
    {
        return $this->makeCall('pttl', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function persist($key)
    {
        return $this->makeCall('persist', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function info($option = null)
    {
        return $this->makeCall('info', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function resetStat()
    {
        return $this->makeCall('resetStat', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function select($dbindex)
    {
        return $this->makeCall('select', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function move($key, $dbindex)
    {
        return $this->makeCall('move', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function bgrewriteaof()
    {
        return $this->makeCall('bgrewriteaof', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function slaveof($host = '127.0.0.1', $port = 6379)
    {
        return $this->makeCall('slaveof', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function object($string = '', $key = '')
    {
        return $this->makeCall('object', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function bitop($operation, $retKey, $key1, $key2, $key3 = null)
    {
        return $this->makeCall('bitop', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function bitcount($key)
    {
        return $this->makeCall('bitcount', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function bitpos()
    {
        return $this->makeCall('bitpos', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function mset(array $array)
    {
        return $this->makeCall('mset', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function msetnx(array $array)
    {
        return $this->makeCall('msetnx', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function rpoplpush($srcKey, $dstKey)
    {
        return $this->makeCall('rpoplpush', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function brpoplpush($srcKey, $dstKey, $timeout)
    {
        return $this->makeCall('brpoplpush', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zAdd($key, $score1, $value1, $score2 = null, $value2 = null, $scoreN = null, $valueN = null)
    {
        return $this->makeCall('zAdd', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zDelete($key, $member1, $member2 = null, $memberN = null)
    {
        return $this->makeCall('zDelete', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zRange($key, $start, $end, $withscores = null)
    {
        return $this->makeCall('zRange', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zReverseRange()
    {
        return $this->makeCall('zReverseRange', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zRangeByScore($key, $start, $end, array $options = [])
    {
        return $this->makeCall('zRangeByScore', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zRevRangeByScore($key, $start, $end, array $options = [])
    {
        return $this->makeCall('zRevRangeByScore', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zCount($key, $start, $end)
    {
        return $this->makeCall('zCount', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zDeleteRangeByScore($key, $start, $end)
    {
        return $this->makeCall('zDeleteRangeByScore', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zDeleteRangeByRank($key, $start, $end)
    {
        return $this->makeCall('zDeleteRangeByRank', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zCard($key)
    {
        return $this->makeCall('zCard', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zScore($key, $member)
    {
        return $this->makeCall('zScore', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zRank($key, $member)
    {
        return $this->makeCall('zRank', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zRevRank($key, $member)
    {
        return $this->makeCall('zRevRank', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zInter($Output, $ZSetKeys, array $Weights = null, $aggregateFunction = 'SUM')
    {
        return $this->makeCall('zInter', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zUnion($Output, $ZSetKeys, array $Weights = null, $aggregateFunction = 'SUM')
    {
        return $this->makeCall('zUnion', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zIncrBy($key, $value, $member)
    {
        return $this->makeCall('zIncrBy', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function expireAt($key, $timestamp)
    {
        return $this->makeCall('expireAt', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function pExpire($key, $ttl)
    {
        return $this->makeCall('pExpire', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function pExpireAt($key, $timestamp)
    {
        return $this->makeCall('pExpireAt', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hGet($key, $hashKey)
    {
        return $this->makeCall('hGet', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hSet($key, $hashKey, $value)
    {
        return $this->makeCall('hSet', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hSetNx($key, $hashKey, $value)
    {
        return $this->makeCall('hSetNx', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hDel($key, $hashKey1, $hashKey2 = null, $hashKeyN = null)
    {
        return $this->makeCall('hDel', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hLen($key)
    {
        return $this->makeCall('hLen', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hKeys($key)
    {
        return $this->makeCall('hKeys', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hVals($key)
    {
        return $this->makeCall('hVals', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hGetAll($key)
    {
        return $this->makeCall('hGetAll', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hExists($key, $hashKey)
    {
        return $this->makeCall('hExists', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hIncrBy($key, $hashKey, $value)
    {
        return $this->makeCall('hIncrBy', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hIncrByFloat($key, $field, $increment)
    {
        return $this->makeCall('hIncrByFloat', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hMset($key, $hashKeys)
    {
        return $this->makeCall('hMset', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hMget($key, $hashKeys)
    {
        return $this->makeCall('hMget', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function multi()
    {
        return $this->makeCall('multi', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function discard()
    {
        return $this->makeCall('discard', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function exec()
    {
        return $this->makeCall('exec', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function pipeline()
    {
        return $this->makeCall('pipeline', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function watch($key)
    {
        return $this->makeCall('watch', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function unwatch()
    {
        return $this->makeCall('unwatch', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function publish($channel, $message)
    {
        return $this->makeCall('publish', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function subscribe($channels, $callback)
    {
        return $this->makeCall('subscribe', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function psubscribe($patterns, $callback)
    {
        return $this->makeCall('psubscribe', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function unsubscribe()
    {
        return $this->makeCall('unsubscribe', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function punsubscribe()
    {
        return $this->makeCall('punsubscribe', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function time()
    {
        return $this->makeCall('time', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function evalSha($scriptSha, $args = array(), $numKeys = 0)
    {
        return $this->makeCall('evalSha', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function script($command, $script)
    {
        return $this->makeCall('script', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function dump($key)
    {
        return $this->makeCall('dump', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function restore($key, $ttl, $value)
    {
        return $this->makeCall('restore', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function migrate($host, $port, $key, $db, $timeout)
    {
        return $this->makeCall('migrate', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function _prefix($value)
    {
        return $this->makeCall('prefix', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function _serialize()
    {
        return $this->makeCall('serialize', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function _unserialize($value)
    {
        return $this->makeCall('unserialize', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function client()
    {
        return $this->makeCall('client', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function scan(&$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->makeCall('scan', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function hscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->makeCall('hscan', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->makeCall('zscan', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->makeCall('sscan', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getOption($name)
    {
        return $this->makeCall('getOption', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function setOption($name, $value)
    {
        return $this->makeCall('setOption', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function config($operation, $key, $value)
    {
        return $this->makeCall('config', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function slowlog()
    {
        return $this->makeCall('slowlog', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getHost()
    {
        return $this->makeCall('getHost', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getPort()
    {
        return $this->makeCall('getPort', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getDBNum()
    {
        return $this->makeCall('getDBNum', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getTimeout()
    {
        return $this->makeCall('getTimeout', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getReadTimeout()
    {
        return $this->makeCall('getReadTimeout', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getPersistentID()
    {
        return $this->makeCall('getPersistentID', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function getAuth()
    {
        return $this->makeCall('getAuth', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function isConnected()
    {
        return $this->makeCall('isConnected', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function wait()
    {
        return $this->makeCall('wait', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function pubsub()
    {
        return $this->makeCall('pubsub', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function open($host, $port = 6379, $timeout = 0.0)
    {
        return $this->makeCall('open', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function popen($host, $port = 6379, $timeout = 0.0)
    {
        return $this->makeCall('popen', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lLen($key)
    {
        return $this->makeCall('lLen', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sGetMembers($key)
    {
        return $this->makeCall('sGetMembers', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function mget(array $array)
    {
        return $this->makeCall('mget', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function expire($key, $ttl)
    {
        return $this->makeCall('expire', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zunionstore()
    {
        return $this->makeCall('zunionstore', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zinterstore()
    {
        return $this->makeCall('zinterstore', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zRemove()
    {
        return $this->makeCall('zRemove', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zRem($key, $member1, $member2 = null, $memberN = null)
    {
        return $this->makeCall('zRem', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zRemoveRangeByScore()
    {
        return $this->makeCall('zRemoveRangeByScore', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zRemRangeByScore($key, $start, $end)
    {
        return $this->makeCall('zRemRangeByScore', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zRemRangeByRank($key, $start, $end)
    {
        return $this->makeCall('zRemRangeByRank', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zSize($key)
    {
        return $this->makeCall('zSize', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function substr($key, $start, $end)
    {
        return $this->makeCall('substr', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function rename($srcKey, $dstKey)
    {
        return $this->makeCall('rename', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function del($key1, $key2 = null, $key3 = null)
    {
        return $this->makeCall('del', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function keys($pattern)
    {
        return $this->makeCall('keys', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lRem($key, $value, $count)
    {
        return $this->makeCall('lRem', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lTrim($key, $start, $stop)
    {
        return $this->makeCall('lTrim', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lIndex($key, $index)
    {
        return $this->makeCall('lIndex', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function lRange($key, $start, $end)
    {
        return $this->makeCall('lRange', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sCard($key)
    {
        return $this->makeCall('sCard', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sRem($key, $member1, $member2 = null, $memberN = null)
    {
        return $this->makeCall('sRem', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sIsMember($key, $value)
    {
        return $this->makeCall('sIsMember', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function zRevRange($key, $start, $end, $withscore = null)
    {
        return $this->makeCall('zRevRange', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sendEcho()
    {
        return $this->makeCall('sendEcho', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function evaluate($script, $args = array(), $numKeys = 0)
    {
        return $this->makeCall('evaluate', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function evaluateSha($scriptSha, $args = array(), $numKeys = 0)
    {
        return $this->makeCall('evaluateSha', func_get_args());
    }
}

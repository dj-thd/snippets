<?php

/**
 * PHP Mutex implementation for distributed systems using Redis
 * 
 * Based on C++11 std::mutex class API but with some add-ons
 *
 * Depends on phpredis module and redis server
 *
 * @uses Redis
 * @author dj.thd <dj.thd@hotmail.com>
 */
class Mutex
{
    /**
     * Redis instance
     * @var Redis
     */
    private $redis;
    
    /**
     * Mutex name
     * @var string
     */
    private $mutex_name;
    
    /**
     * Redis key name
     * @var string
     * @internal
     */
    private $key_name;
    
    /**
     * Maximum TTL for the lock
     * @var integer
     */
    private $max_ttl;
    
    /**
     * Mutex constructor
     *
     * @param Redis $redis_instance The redis instance to be used, recommended to be master node connection
     *                              on master-slave setups
     * @param string $mutex_name The name to uniquely identify the mutex
     * @param integer $max_ttl OPTIONAL (default = 0). Time to live for the lock in seconds, 0 means forever.
     *                         Note that if something breaks, the lock will not be released until TTL expires or mutex
     *                         is initialized with lower TTL than the previous set.
     */
    public function __construct(&$redis_instance, $mutex_name, $max_ttl = 0)
    {
        $this->redis =& $redis_instance;
        $this->mutex_name = $mutex_name;
        $this->key_name = "//mutex/$mutex_name";
        $this->max_ttl = intval($max_ttl);
    }
    
    /**
     * Lock the mutex, waiting if it's already locked
     *
     * @param integer $timeout OPTIONAL (default = 0). Maximum time in seconds to wait for the lock to be released,
     *                         0 means forever.
     * @param float $polling_time_ms OPTIONAL (default = 250). Time between lock tries in milliseconds.
     *
     * @returns boolean true if lock was succeeded, false if timeout was reached thus mutex is not acquired
     */
    public function lock($timeout = 0, $polling_time_ms = 250)
    {
        // By default we assume if the function ends, we have the lock
        $result = true;
        
        // Save lock start time to calculate the timeout
        $lock_start = time();
        
        // Ensure timeout parameter is integer
        $timeout = intval($timeout);
        
        // Calculate polling time in microseconds
        $polling_time_us = floatval($polling_time_ms) * 1000;
        
        // Do polling until lock is acquired or timeout is expired
        while(!$this->try_lock()) {
            
            // If timeout is expired we break the polling loop and return false
            if($timeout && (time() - $lock_start) > $timeout) {
                $result = false;
                break;
            }
            
            // Wait until next poll
            usleep($polling_time_us);
        }
        
        return $result;
    }
    
    /**
     * Try lock the mutex without blocking execution
     *
     * @returns boolean true if mutex was acquired, false otherwise
     */
    public function try_lock()
    {
        // Redis set if not exists, save current time into the mutex key
        $result = $this->redis->setnx($this->key_name, time());
        
        // If TTL is set, make it effective
        if($this->max_ttl) {
            if(!$result) {
                // SETNX failed and TTL is set
                
                // Get stored lock time
                $prev_lock_time = $this->redis->get($this->key_name);
                
                // If previous lock time could not be fetched (key may be deleted or expired meanwhile) or
                // according to saved time it surpassed the current set TTL, remove it and try SETNX again
                if($prev_lock_time === FALSE || (time() - $prev_lock_time) > $this->max_ttl) {
                    $this->redis->del($this->key_name);
                    $result = $this->redis->setnx($this->key_name, time());
                }
            } else {
                // SETNX succeeded, set key expiration on Redis, delete the key and return false if we can't do this
                if(!$this->redis->expire($this->key_name, $this->max_ttl)) {
                    $this->redis->del($this->key_name);
                    $result = false;
                }
            }
        }
        return $result;
    }
    
    /**
     * Unlock the mutex (delete Redis key)
     */
    public function unlock()
    {
        $this->redis->del($this->key_name);
    }
    
    /**
     * Check if mutex is already locked
     *
     * Dont rely on this to do locks itself, use lock() and try_lock() properly!
     *
     * Bad usage example (NEVER DO THIS):
     *
     * $mutex = new Mutex($redis, 'my_mutex');
     * if(!$mutex->is_locked()) {
     *     $mutex->try_lock();
     *     ...
     *     (critical section)
     *     ...
     * }
     *
     * @returns boolean
     */
    public function is_locked()
    {
        return $this->redis->exists($this->key_name);
    }
    
    /**
     * Destructor that should be called automatically by PHP
     * Unlocks the mutex
     */
    public function __destruct()
    {
        $this->unlock();
    }
}

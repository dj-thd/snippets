<?php
// Mutex class example
// This is intended to be executed on commandline, i.e. php -f Mutex_example.php

// Include class file
require_once('Mutex.php');

// Instantiate Redis
$redis = new Redis();

// Connect Redis, 5sec timeout and 50ms between connection tries
if(!$redis->connect('localhost', '6379', 5, 50))
    die("Redis connection failed!\n");

// Do Redis auth here if needed

// Instantiate Mutex
// We put 300 seconds of max TTL for the lock, in case of PHP breaks suddenly or it's killed,
// to ensure we never end with a locked mutex forever
// Not recommended to put low values here, this could cause race conditions if the TTL is reached
// before a script ends its execution, releasing the lock too soon
// Recommended to put with high margin to the script expected maximum execution time, i.e. expected time * 4 or * 5
$mutex = new Mutex($redis, 'my_mutex', 300);

// Try lock on mutex, without blocking execution
printf("Trying lock (nonblocking)...\n");
if($mutex->try_lock()) {
    // Critical section here
    printf("Lock successfully acquired! Doing some work...\n");
    
    // Simulate 20 seconds of heavy work
    sleep(10);
    
    // Work finished, release the mutex
    $mutex->unlock();
    printf("Work finished\n");
} else {
    printf("Mutex was already locked!\n");
}

// Lock mutex, blocking execution for max 5 seconds
printf("Trying lock, blocking with 5 seconds timeout\n");
if($mutex->lock(5)) {
    printf("We got the lock! Doing heavy work...\n");
    sleep(10);
    $mutex->unlock();
    printf("Work finished\n");
} else {
    printf("Timeout!\n");
}

// Lock mutex, blocking execution for undefined time
// Note the absence of if(), lock function without timeout always
// gets the lock so next code will be critical section regardless
// of function result (should be true anyway)
// As this function is based on polling, there is no queue or similar to
// process lock requests by order, so if there is high concurrency
// other processes may steal the lock and take it before
printf("Trying lock, blocking execution until unlocked\n");
$mutex->lock();
printf("Got the lock! Doing work...\n");
sleep(10);
printf("Critical section finished\n");

// Note we don't unlock the lock at the end of script, this is done
// on purpose to demonstrate the destructor should unlock it automatically
// (this also should be done on exceptions and failures)

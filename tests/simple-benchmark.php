<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$redis = new Redis();
$redis->pconnect('host.docker.internal');

$count = 100000;

$interval = 0;
dump("count: $count", "interval: $interval μs");
$start = microtime(true);
for ($i = 0; $i < $count; $i ++) {
    $redis->set('test-redis', $i);
}
dump('redis: ' . microtime(true) - $start);
$redis->del('test-redis');

$start = microtime(true);
for ($i = 0; $i < $count; $i ++) {
    \Workbunny\WebmanSharedCache\Cache::Set('test-cache', $i);
}
dump('cache: ' . microtime(true) - $start);
\Workbunny\WebmanSharedCache\Cache::Del('test-cache');
dump('-----------------------------------');

$interval = 1;
dump("count: $count", "interval: $interval μs");
$start = microtime(true);
for ($i = 0; $i < $count; $i ++) {
    $redis->set('test-redis', $i);
    usleep($interval);
}
dump('redis: ' . microtime(true) - $start);
$redis->del('test-redis');

$start = microtime(true);
for ($i = 0; $i < $count; $i ++) {
    \Workbunny\WebmanSharedCache\Cache::Set('test-cache', $i);
    usleep($interval);
}
dump('cache: ' . microtime(true) - $start);
\Workbunny\WebmanSharedCache\Cache::Del('test-cache');
dump('-----------------------------------');

$interval = 10;
dump("count: $count", "interval: $interval μs");
$start = microtime(true);
for ($i = 0; $i < $count; $i ++) {
    $redis->set('test-redis', $i);
    usleep($interval);
}
dump('redis: ' . microtime(true) - $start);
\Workbunny\WebmanSharedCache\Cache::Del('test-cache');
$redis->del('test-redis');

$start = microtime(true);
for ($i = 0; $i < $count; $i ++) {
    \Workbunny\WebmanSharedCache\Cache::Set('test-cache', $i);
    usleep($interval);
}
dump('cache: ' . microtime(true) - $start);
\Workbunny\WebmanSharedCache\Cache::Del('test-cache');
dump('-----------------------------------');

$interval = 100;
dump("count: $count", "interval: $interval μs");
$start = microtime(true);
for ($i = 0; $i < $count; $i ++) {
    $redis->set('test-redis', $i);
    usleep($interval);
}
dump('redis: ' . microtime(true) - $start);
\Workbunny\WebmanSharedCache\Cache::Del('test-cache');
$redis->del('test-redis');

$start = microtime(true);
for ($i = 0; $i < $count; $i ++) {
    \Workbunny\WebmanSharedCache\Cache::Set('test-cache', $i);
    usleep($interval);
}
dump('cache: ' . microtime(true) - $start);
\Workbunny\WebmanSharedCache\Cache::Del('test-cache');
dump('-----------------------------------');

$interval = 1000;
dump("count: $count", "interval: $interval μs");
$start = microtime(true);
for ($i = 0; $i < $count; $i ++) {
    $redis->set('test-redis', $i);
    usleep($interval);
}
dump('redis: ' . microtime(true) - $start);
$redis->del('test-redis');

$start = microtime(true);
for ($i = 0; $i < $count; $i ++) {
    \Workbunny\WebmanSharedCache\Cache::Set('test-cache', $i);
    usleep($interval);
}
dump('cache: ' . microtime(true) - $start);
\Workbunny\WebmanSharedCache\Cache::Del('test-cache');
dump('-----------------------------------');
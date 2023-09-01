<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Tests;

use Workbunny\WebmanSharedCache\Cache;

/**
 * @backupGlobals disabled
 * @runTestsInSeparateProcesses
 */
class GenericTest extends BaseTestCase
{

    public function testLockInfo(): void
    {
        $key = Cache::GetLockKey(__METHOD__);
        $func = __FUNCTION__;
        $timestamp = microtime(true);
        $params = func_get_args();
        $this->assertEquals([], Cache::LockInfo());
        apcu_entry($key, function () use ($func, $timestamp, $params) {
            return [
                'timestamp' => $timestamp,
                'method'    => $func,
                'params'    => $params
            ];
        });
        $this->assertContainsEquals([
            'timestamp' => $timestamp,
            'method'    => $func,
            'params'    => $params
        ], Cache::LockInfo());
        // 清理
        apcu_delete($key);
    }

    public function testKeyInfo(): void
    {
        $key = __METHOD__;
        $func = __FUNCTION__;
        $this->assertEquals([], Cache::KeyInfo($key));
        apcu_store($key, $func);
        $info = Cache::KeyInfo($key);
        $this->assertArrayHasKey('hits', $info);
        $this->assertArrayHasKey('access_time', $info);
        $this->assertArrayHasKey('mtime', $info);
        $this->assertArrayHasKey('creation_time', $info);
        $this->assertArrayHasKey('deletion_time', $info);
        $this->assertArrayHasKey('ttl', $info);
        $this->assertArrayHasKey('refs', $info);
        // 清理
        apcu_delete($key);
    }

    public function testInfo(): void
    {
        $info = Cache::Info();
        $this->assertArrayHasKey('ttl', $info);
        $this->assertArrayHasKey('num_hits', $info);
        $this->assertArrayHasKey('num_misses', $info);
        $this->assertArrayHasKey('num_inserts', $info);
        $this->assertArrayHasKey('num_entries', $info);
        $this->assertArrayHasKey('expunges', $info);
        $this->assertArrayHasKey('start_time', $info);
        $this->assertArrayHasKey('mem_size', $info);
        $this->assertArrayHasKey('memory_type', $info);
        $this->assertArrayHasKey('cache_list', $info);
    }

//    /**
//     * @return void
//     */
//    public function testAtomic(): void
//    {
//        $lockKey = Cache::GetLockKey($key = __METHOD__);
//        $this->assertTrue(Cache::Atomic($key, function () {
//            return true;
//        }));
//
//        $this->assertTrue(Cache::Atomic($key, function () use ($key) {
//            Cache::Set("$key-1", "$key-1");
//            Cache::Set("$key-2", "$key-2");
//        }));
//        $this->assertEquals("$key-1", Cache::Get("$key-1"));
//        $this->assertEquals("$key-2", Cache::Get("$key-2"));
//        apcu_delete("$key-1");
//        apcu_delete("$key-2");
//
//        apcu_store($lockKey, 1);
//        $this->assertFalse(Cache::Atomic($key, function () {
//            return true;
//        }));
//        apcu_delete($lockKey);
//
//        apcu_store($lockKey, 1);
//        $this->assertFalse(Cache::Atomic($key, function () use ($key) {
//            Cache::Set("$key-1", "$key-1");
//            Cache::Set("$key-2", "$key-2");
//        }));
//        $this->assertNull(Cache::Get("$key-1"));
//        $this->assertNull(Cache::Get("$key-2"));
//        apcu_delete($lockKey);
//    }
}
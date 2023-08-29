<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Tests;

use Workbunny\WebmanSharedCache\Cache;

class CacheTest extends BaseTestCase
{
    /**
     * @runInSeparateProcess
     * @return void
     */
    public function testCacheGet(): void
    {
        $key = __METHOD__;
        // 单进程执行
        $this->assertEquals(null, Cache::Get($key));
        apcu_add($key, $key);
        $this->assertEquals($key, Cache::Get($key));
        // 清理
        apcu_delete($key);

        // 子进程执行
        $this->assertEquals(null, Cache::Get($key));
        $this->childExec(static function (string $key) {
            apcu_add($key, $key);
        }, $key);
        $this->assertEquals($key, Cache::Get($key));
        // 清理
        apcu_delete($key);
    }

    /**
     * @runInSeparateProcess
     * @return void
     */
    public function testCacheSet(): void
    {
        $key = __METHOD__;
        // 单进程执行
        $this->assertFalse(apcu_fetch($key));
        $this->assertTrue(Cache::Set($key, $key));
        $this->assertEquals($key, apcu_fetch($key));
        // 清理
        apcu_delete($key);

        // 子进程执行
        $this->assertFalse(apcu_fetch($key));
        $this->childExec(static function (string $key) {
            Cache::Set($key, $key);
        }, $key);
        $this->assertEquals($key, apcu_fetch($key));
        // 清理
        apcu_delete($key);
    }

    /**
     * @runInSeparateProcess
     * @return void
     */
    public function testCacheDel(): void
    {
        $key = __METHOD__;
        // 在单进程内
        apcu_add($key, $key);
        $this->assertEquals([], Cache::Del($key));
        $this->assertFalse(apcu_fetch($key));
        // 清理
        apcu_delete($key);

        // 在子进程内
        apcu_add($key, $key);
        $this->childExec(static function (string $key) {
            Cache::Del($key);
        }, $key);
        $this->assertFalse(apcu_fetch($key));
        // 清理
        apcu_delete($key);
    }

    /**
     * @runInSeparateProcess
     * @return void
     */
    public function testCacheExists(): void
    {
        $key = __METHOD__;

        $this->assertEquals([], Cache::Exists($key));
        apcu_add($key, $key);
        $this->assertEquals([
            'Workbunny\WebmanSharedCache\Tests\CacheTest::testCacheExists' => true
        ], Cache::Exists($key));
        // 清理
        apcu_delete($key);
    }

    /**
     * @runInSeparateProcess
     * @return void
     */
    public function testCacheIncr(): void
    {
        $key = __METHOD__;
        // 在单进程内
        $this->assertFalse(apcu_fetch($key));
        Cache::Incr($key);
        $this->assertEquals(1, apcu_fetch($key));
        Cache::Incr($key);
        $this->assertEquals(2, apcu_fetch($key));
        // 清理
        apcu_delete($key);

        // 在子进程内
        $this->assertFalse(apcu_fetch($key));
        $this->childExec(static function (string $key) {
            Cache::Incr($key);
        }, $key);
        $this->assertEquals(1, apcu_fetch($key));
        $this->childExec(static function (string $key) {
            Cache::Incr($key);
        }, $key);
        $this->assertEquals(2, apcu_fetch($key));
        // 清理
        apcu_delete($key);
    }

    /**
     * @runInSeparateProcess
     * @return void
     */
    public function testCacheDecr(): void
    {
        $key = __METHOD__;
        // 在单进程内
        $this->assertFalse(apcu_fetch($key));
        Cache::Decr($key);
        $this->assertEquals(-1, apcu_fetch($key));
        Cache::Decr($key);
        $this->assertEquals(-2, apcu_fetch($key));
        // 清理
        apcu_delete($key);

        // 在子进程内
        $this->assertFalse(apcu_fetch($key));
        $this->childExec(static function (string $key) {
            Cache::Decr($key);
        }, $key);
        $this->assertEquals(-1, apcu_fetch($key));
        $this->childExec(static function (string $key) {
            Cache::Decr($key);
        }, $key);
        $this->assertEquals(-2, apcu_fetch($key));
        // 清理
        apcu_delete($key);
    }
}
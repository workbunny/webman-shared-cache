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
    public function testCacheHashGet(): void
    {
        $key = __METHOD__;
        $hash = 'test';
        // 单进程执行
        $this->assertEquals(null, Cache::HGet($key, $hash));
        apcu_add($key, [
            $hash => $hash
        ]);
        $this->assertEquals([], Cache::LockInfo());
        $this->assertEquals($hash, Cache::HGet($key, $hash));
        // 清理
        apcu_delete($key);

        // 子进程执行
        $this->assertEquals(null, Cache::HGet($key, $hash));
        $this->childExec(static function (string $key, string $hash) {
            apcu_add($key, [
                $hash => $hash
            ]);
        }, $key, $hash);
        $this->assertEquals([], Cache::LockInfo());
        $this->assertEquals($hash, Cache::HGet($key, $hash));
        // 清理
        apcu_delete($key);
    }

    /**
     * @runInSeparateProcess
     * @return void
     */
    public function testCacheHashSet(): void
    {
        $key = __METHOD__;
        $hash = 'test';
        // 单进程执行
        $this->assertFalse(apcu_fetch($key));
        $this->assertTrue(Cache::HSet($key, $hash, $hash));
        $this->assertEquals([], Cache::LockInfo());
        $this->assertEquals([
            $hash => $hash
        ], apcu_fetch($key));
        // 清理
        apcu_delete($key);

        // 子进程执行
        $this->assertFalse(apcu_fetch($key));
        $this->childExec(static function (string $key, string $hash) {
            Cache::HSet($key, $hash, $hash);
        }, $key, $hash);
        $this->assertEquals([], Cache::LockInfo());
        $this->assertEquals([
            $hash => $hash
        ], apcu_fetch($key));
        // 清理
        apcu_delete($key);
    }

    /**
     * @runInSeparateProcess
     * @return void
     */
    public function testCacheHashDel(): void
    {
        $key = __METHOD__;
        // 在单进程内
        apcu_add($key, [
            'a' => 1,
            'b' => 2
        ]);
        $this->assertTrue(Cache::HDel($key, 'a'));
        $this->assertEquals([], Cache::LockInfo());
        $this->assertEquals([
            'b' => 2
        ], apcu_fetch($key));
        // 清理
        apcu_delete($key);

        // 在子进程内
        apcu_add($key, [
            'a' => 1,
            'b' => 2
        ]);
        $this->childExec(static function (string $key) {
            Cache::HDel($key, 'b');
        }, $key);
        $this->assertEquals([], Cache::LockInfo());
        $this->assertEquals([
            'a' => 1
        ],apcu_fetch($key));
        // 清理
        apcu_delete($key);
    }

    /**
     * @runInSeparateProcess
     * @return void
     */
    public function testCacheHashExists(): void
    {
        $key = __METHOD__;

        $this->assertEquals([], Cache::HExists($key, 'a'));
        apcu_add($key, [
            'a' => 1,
            'b' => 2
        ]);
        $this->assertEquals([
            'a' => true, 'b' => true
        ], Cache::HExists($key, 'a', 'b', 'c'));
        $this->assertEquals([], Cache::LockInfo());
        // 清理
        apcu_delete($key);
    }
}
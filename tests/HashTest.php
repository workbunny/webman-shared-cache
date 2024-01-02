<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Tests;

use Workbunny\WebmanSharedCache\Cache;

class HashTest extends BaseTestCase
{
    public function testHashGet(): void
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

    public function testHashSet(): void
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

    public function testHashDel(): void
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

    public function testHashExists(): void
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

    public function testHashIncr(): void
    {
        $key = __METHOD__;
        // 在单进程内
        $this->assertFalse(apcu_fetch($key));
        $this->assertEquals(1, Cache::HIncr($key, 'a'));
        $this->assertEquals([
            'a' => 1
        ], apcu_fetch($key));
        $this->assertEquals(3, Cache::HIncr($key, 'a', 2));
        $this->assertEquals([
            'a' => 3
        ], apcu_fetch($key));
        $this->assertEquals(4.1, Cache::HIncr($key, 'a', 1.1));
        $this->assertEquals([
            'a' => 4.1
        ], apcu_fetch($key));
        // 清理
        apcu_delete($key);

        // 在子进程内
        $this->assertFalse(apcu_fetch($key));
        $this->childExec(static function (string $key) {
            Cache::HIncr($key, 'a');
        }, $key);
        $this->assertEquals([
            'a' => 1
        ], apcu_fetch($key));
        $this->childExec(static function (string $key) {
            Cache::HIncr($key, 'a',2);
        }, $key);
        $this->assertEquals([
            'a' => 3
        ], apcu_fetch($key));
        $this->childExec(static function (string $key) {
            Cache::HIncr($key, 'a',1.1);
        }, $key);
        $this->assertEquals([
            'a' => 4.1
        ], apcu_fetch($key));
        // 清理
        apcu_delete($key);
    }

    public function testHashDecr(): void
    {
        $key = __METHOD__;
        // 在单进程内
        $this->assertFalse(apcu_fetch($key));
        $this->assertEquals(-1, Cache::HDecr($key, 'a'));
        $this->assertEquals([
            'a' => -1
        ], apcu_fetch($key));
        $this->assertEquals(-3, Cache::HDecr($key, 'a', 2));
        $this->assertEquals([
            'a' => -3
        ], apcu_fetch($key));
        $this->assertEquals(-4.1, Cache::HDecr($key, 'a', 1.1));
        $this->assertEquals([
            'a' => -4.1
        ], apcu_fetch($key));
        // 清理
        apcu_delete($key);

        // 在子进程内
        $this->assertFalse(apcu_fetch($key));
        $this->childExec(static function (string $key) {
            Cache::HDecr($key, 'a');
        }, $key);
        $this->assertEquals([
            'a' => -1
        ], apcu_fetch($key));
        $this->childExec(static function (string $key) {
            Cache::HDecr($key, 'a', 2);
        }, $key);
        $this->assertEquals([
            'a' => -3
        ], apcu_fetch($key));
        $this->childExec(static function (string $key) {
            Cache::HDecr($key, 'a', 1.1);
        }, $key);
        $this->assertEquals([
            'a' => -4.1
        ], apcu_fetch($key));
        // 清理
        apcu_delete($key);
    }
}
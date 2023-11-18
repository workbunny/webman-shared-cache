<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Tests;

use Workbunny\WebmanSharedCache\Cache;
use Workbunny\WebmanSharedCache\Future;

class ChannelTest extends BaseTestCase
{
    public function testChannelKey(): void
    {
        $channel = __FUNCTION__;
        $this->assertEquals('#Channel#' . $channel, Cache::GetChannelKey($channel));
    }

    public function testChannelPublish(): void
    {
        $channel = __FUNCTION__;
        $message = 'test';
        // 初始化确认
        $this->assertEquals([], Cache::GetChannel($channel));
        $this->assertEquals([], Cache::LockInfo());
        // 当前进程执行
        Cache::ChPublish($channel, $message);
        // 确认数据
        $this->assertEquals([
            '--default--' => [
                'value' => [$message]
            ]
        ], apcu_fetch(Cache::GetChannelKey($channel)));
        // 确认无锁
        $this->assertEquals([], Cache::LockInfo());
        // 清理
        apcu_delete(Cache::GetChannelKey($channel));
    }

    public function testChannelPublishByChild(): void
    {
        $channel = __FUNCTION__;
        $message = 'test';
        // 初始化确认
        $this->assertEquals([], Cache::GetChannel($channel));
        $this->assertEquals([], Cache::LockInfo());
        // 子进程执行
        $this->childExec(static function (string $channel, string $message) {
            Cache::ChPublish($channel, $message);
        }, $channel, $message);
        // 确认数据
        $this->assertEquals([
            '--default--' => [
                'value' => [$message]
            ]
        ], apcu_fetch(Cache::GetChannelKey($channel)));
        // 确认无锁
        $this->assertEquals([], Cache::LockInfo());
        // 清理
        apcu_delete(Cache::GetChannelKey($channel));
    }

    public function testChannelCreateListener(): void
    {
        Future::$debug = true;
        $channel = __FUNCTION__;
        $message = 'test';
        // 初始化确认
        $this->assertEquals([], Cache::GetChannel($channel));
        $this->assertEquals([], Cache::LockInfo());
        // 当前进程执行
        Cache::ChCreateListener($channel, '1', function (string $key, string|int $workerId, mixed $message) {
            dump($key, $workerId, $message);
        });
        // 确认数据
        $this->assertEquals([
            '1' => [
                'futureId' => 1,
                'value'    => []
            ]
        ], apcu_fetch(Cache::GetChannelKey($channel)));
        // 确认无锁
        $this->assertEquals([], Cache::LockInfo());
        // 清理
        apcu_delete(Cache::GetChannelKey($channel));
    }

    public function testChannelCreateListenerByChild(): void
    {
        Future::$debug = true;
        $channel = __FUNCTION__;
        $message = 'test';

        // 初始化确认
        $this->assertEquals([], Cache::GetChannel($channel));
        $this->assertEquals([], Cache::LockInfo());
        // 子进程执行
        $this->childExec(static function (string $channel, string $message) {
            Cache::ChCreateListener($channel, '1', function (string $key, string|int $workerId, mixed $message) {
                dump($key, $workerId, $message);
            });
        }, $channel, $message);
        // 确认数据
        $this->assertEquals([
            '1' => [
                'futureId' => 1,
                'value'    => []
            ]
        ], apcu_fetch(Cache::GetChannelKey($channel)));
        // 确认无锁
        $this->assertEquals([], Cache::LockInfo());
        // 清理
        apcu_delete(Cache::GetChannelKey($channel));
    }

    public function testChannelPublishAfterCreateListener(): void
    {
        Future::$debug = true;
        $channel = __FUNCTION__;
        $message = 'test';
        // 初始化确认
        $this->assertEquals([], Cache::GetChannel($channel));
        $this->assertEquals([], Cache::LockInfo());
        // 当前进程执行
        Cache::ChCreateListener($channel, '1', function (string $key, string|int $workerId, mixed $message) {
            dump($key, $workerId, $message);
        });
        Cache::ChPublish($channel, $message);
        // 确认数据
        $this->assertEquals([
            '1' => [
                'futureId' => 1,
                'value'    => [$message]
            ]
        ], apcu_fetch(Cache::GetChannelKey($channel)));
        // 确认无锁
        $this->assertEquals([], Cache::LockInfo());
        // 清理
        apcu_delete(Cache::GetChannelKey($channel));
    }

    public function testChannelPublishAfterCreateListenerByChild(): void
    {
        Future::$debug = true;
        $channel = __FUNCTION__;
        $message = 'test';
        // 初始化确认
        $this->assertEquals([], Cache::GetChannel($channel));
        $this->assertEquals([], Cache::LockInfo());
        // 子进程执行
        $this->childExec(static function (string $channel, string $message) {
            Cache::ChCreateListener($channel, '1', function (string $key, string|int $workerId, mixed $message) {
                dump($key, $workerId, $message);
            });
            Cache::ChPublish($channel, $message);
        }, $channel, $message);
        // 确认数据
        $this->assertEquals([
            '1' => [
                'futureId' => 1,
                'value'    => [$message]
            ]
        ], apcu_fetch(Cache::GetChannelKey($channel)));
        // 确认无锁
        $this->assertEquals([], Cache::LockInfo());
        // 清理
        apcu_delete(Cache::GetChannelKey($channel));
    }

    public function testChannelRemoveListener(): void
    {
        Future::$debug = true;
        $channel = __FUNCTION__;
        // 初始化确认
        $this->assertEquals([], Cache::GetChannel($channel));
        $this->assertEquals([], Cache::LockInfo());
        // 当前进程执行
        Cache::ChCreateListener($channel, '1', function (string $key, string|int $workerId, mixed $message) {
            dump($key, $workerId, $message);
        });
        // 确认数据
        $this->assertEquals([
            '1' => [
                'futureId' => 1,
                'value'    => []
            ]
        ], apcu_fetch(Cache::GetChannelKey($channel)));
        // 确认无锁
        $this->assertEquals([], Cache::LockInfo());
        Cache::ChRemoveListener($channel, '1', false);
        // 确认数据
        $this->assertEquals([
            '1' => [
                'futureId' => 1,
                'value'    => []
            ]
        ], apcu_fetch(Cache::GetChannelKey($channel)));
        // 确认无锁
        $this->assertEquals([], Cache::LockInfo());
        // 清理
        apcu_delete(Cache::GetChannelKey($channel));
    }

    public function testChannelRemoveListenerByChild(): void
    {
        Future::$debug = true;
        $channel = __FUNCTION__;
        $message = 'test';
        // 初始化确认
        $this->assertEquals([], Cache::GetChannel($channel));
        $this->assertEquals([], Cache::LockInfo());
        // 子进程执行
        $this->childExec(static function (string $channel, string $message) {
            Cache::ChCreateListener($channel, '1', function (string $key, string|int $workerId, mixed $message) {
                dump($key, $workerId, $message);
            });
        }, $channel, $message);
        // 确认数据
        $this->assertEquals([
            '1' => [
                'futureId' => 1,
                'value'    => []
            ]
        ], apcu_fetch(Cache::GetChannelKey($channel)));
        // 确认无锁
        $this->assertEquals([], Cache::LockInfo());
        Cache::ChRemoveListener($channel, '1', false);
        // 确认数据
        $this->assertEquals([
            '1' => [
                'futureId' => 1,
                'value'    => []
            ]
        ], apcu_fetch(Cache::GetChannelKey($channel)));
        // 确认无锁
        $this->assertEquals([], Cache::LockInfo());
        // 清理
        apcu_delete(Cache::GetChannelKey($channel));
    }
}
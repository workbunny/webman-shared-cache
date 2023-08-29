<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Tests;

use Workbunny\WebmanSharedCache\Cache;

class DebugTest extends BaseTestCase
{
    /**
     * @runInSeparateProcess
     * @return void
     */
    public function testDebugLockInfo(): void
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
}
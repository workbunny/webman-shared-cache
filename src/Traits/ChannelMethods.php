<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Traits;

use Closure;
use Workbunny\WebmanSharedCache\Cache;
use Workbunny\WebmanSharedCache\Future;
use Error;

trait ChannelMethods
{
    use BasicMethods;

    /** @var string 通道前缀 */
    protected static string $_CHANNEL = '#Channel#';

    /**
     * @param string $key
     * @return string
     */
    public static function GetChannelKey(string $key): string
    {
        return self::$_CHANNEL . $key;
    }

    /**
     * 通道获取
     *
     * @param string $key
     * @return array = [
     *   workerId = [
     *      'futureId' => futureId,
     *      'value'    => array
     *   ]
     * ]
     */
    protected static function _GetChannel(string $key): array
    {
        return self::_Get(self::GetChannelKey($key), []);
    }

    /**
     * 通道投递
     *  - 阻塞最大时长受fuse保护，默认60s
     *  - 抢占式锁
     *
     * @param string $key
     * @param mixed $message
     * @param string|int|null $workerId 指定的workerId
     * @param bool $store 在没有监听器时是否进行储存
     * @return bool
     */
    protected static function _Publish(string $key, mixed $message, null|string|int $workerId = null, bool $store = true): bool
    {
        $func = __FUNCTION__;
        $params = func_get_args();
        $key = self::GetChannelKey($key);
        self::_Atomic($key, function () use (
            $key, $message, $func, $params, $store
        ) {
            /**
             * [
             *  workerId = [
             *      'futureId' => futureId,
             *      'value'    => array
             *  ]
             * ]
             */
            $channel = self::_Get($channelName = self::GetChannelKey($key), []);
            foreach ($channel as $workerId => $item) {
                if ($store or isset($item['futureId'])) {
                    $channel[$workerId]['value'][] = $message;
                }
            }
            self::_Set($channelName, $channel);
            return [
                'timestamp' => microtime(true),
                'method'    => $func,
                'params'    => $params,
                'result'    => null
            ];
        }, true);
        return true;
    }

    /**
     * 创建通道监听器
     *  - 同一个进程只能创建一个监听器来监听相同的通道
     *  - 同一个进程可以同时监听不同的通道
     *
     * @param string $key
     * @param string|int $workerId
     * @param Closure $listener = function(string $channelName, string|int $workerId, mixed $message) {}
     * @return bool|int 监听器id
     */
    protected static function _CreateListener(string $key, string|int $workerId, Closure $listener): bool|int
    {
        $func = __FUNCTION__;
        $result = false;
        $params = func_get_args();
        self::_Atomic($key, function () use (
            $key, $workerId, $listener, $func, $params, &$result
        ) {
            /**
             * [
             *  workerId = [
             *      'futureId' => futureId,
             *      'value'    => array
             *  ]
             * ]
             */
            $channel = self::_Get($channelName = self::GetChannelKey($key), []);
            if (isset($channel[$workerId]['futureId'])) {
                throw new Error("Channel $key listener already exist. ");
            }
            // 设置回调
            $channel[$workerId] = $result = Future::add(function () use ($key, $workerId, $listener) {
                // 原子性执行
                Cache::Atomic($key, function () use ($key, $workerId, $listener) {
                    $channel = self::_Get($channelName = self::GetChannelKey($key), []);
                    if ((!empty($value = $channel[$workerId]['value'] ?? []))) {
                        $msg = array_pop($value);
                        $channel[$workerId]['value'] = $value;
                        call_user_func($listener, $key, $workerId, $msg);
                        self::_Set($channelName, $channel);
                    }

                });
            });
            self::_Set($channelName, $channel);
            return [
                'timestamp' => microtime(true),
                'method'    => $func,
                'params'    => $params,
                'result'    => null
            ];
        }, true);
        return $result;
    }

    /**
     * 移除通道监听器
     *
     * @param string $key
     * @param string|int $workerId
     * @return void
     */
    protected static function _RemoveListener(string $key, string|int $workerId): void
    {
        $func = __FUNCTION__;
        $params = func_get_args();
        self::_Atomic($key, function () use (
            $key, $workerId, $func, $params
        ) {
            /**
             * [
             *  workerId = [
             *      'futureId' => futureId,
             *      'value'    => array
             *  ]
             * ]
             */
            $channel = self::_Get($channelName = self::GetChannelKey($key), []);
            if ($id = $channel[$workerId]['futureId'] ?? null) {
                Future::del($id);
                unset($channel[$workerId]['futureId']);
                self::_Set($channelName, $channel);
            }

            return [
                'timestamp' => microtime(true),
                'method'    => $func,
                'params'    => $params,
                'result'    => null
            ];
        }, true);
    }
}

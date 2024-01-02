<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Traits;

use APCUIterator;
use Closure;

/**
 * @method static bool Set(string $key, mixed $value, array $optional = []) 设置缓存值
 * @method static mixed Get(string $key, mixed $default = null) 获取缓存值
 * @method static array Del(string ...$keys) 移除缓存
 * @method static array Keys(null|string $regex = null) 获取缓存键
 * @method static bool|int|float Incr(string $key, int|float $value = 1, int $ttl = 0) 自增
 * @method static bool|int|float Decr(string $key, int|float $value = 1, int $ttl = 0) 自减
 * @method static array Exists(string ...$keys) 判断缓存键
 *
 * @method static void Search(string $regex, Closure $handler, int $chunkSize = 100) 搜索键值 - 正则匹配
 * @method static bool Atomic(string $lockKey, Closure $handler, bool $blocking = false) 原子操作
 *
 * @method static array LockInfo() 获取锁信息
 * @method static array KeyInfo(string $key) 获取键信息
 * @method static array Info(bool $limited = false) 获取信息
 * @method static bool Clear() 清理所有缓存
 */
trait BasicMethods
{
    /** @var string 写锁 */
    protected static string $_LOCK = '#lock#';

    /**
     * @param string $key
     * @return string
     */
    public static function GetLockKey(string $key): string
    {
        return self::$_LOCK . $key;
    }

    /**
     * 通配符转正则
     *
     * @param string $match 通配符
     * <tr> * 匹配一个或多个字符</tr>
     * <tr> ? 匹配一个字符</tr>
     * @return string
     */
    public static function WildcardToRegex(string $match): string
    {
        $regex = str_replace('*', '.+', $match);
        $regex = str_replace('?', '.', $regex);
        return '/^' . $regex . '$/';
    }

    /**
     * 获取键信息
     *
     * @param string $key
     * @return array
     */
    protected static function _KeyInfo(string $key): array
    {
        return apcu_key_info($key) ?: [];
    }

    /**
     * 获取缓存信息
     *
     * @param bool $limited
     * @return array
     */
    protected static function _Info(bool $limited = false): array
    {
        return apcu_cache_info($limited);
    }

    /**
     * 搜索
     *
     * @param string $regex 正则
     * @param Closure $handler = function ($key, $value) {}
     * @param int $chunkSize
     * @return void
     */
    protected static function _Search(string $regex, Closure $handler, int $chunkSize = 100): void
    {
        $iterator = new APCUIterator($regex, APC_ITER_ALL, $chunkSize);
        while ($iterator->valid()) {
            $data = $iterator->current();
            call_user_func($handler, $data['key'], $data['value']);
            $iterator->next();
        }
    }

    /**
     * 缓存释放
     *
     * @return bool
     */
    protected static function _Clear(): bool
    {
        return apcu_clear_cache();
    }

    /**
     * 获取锁信息
     *
     * @return array = [
     *  lockKey => [
     *      'timestamp' => 创建时间(float),
     *      'method'    => 创建的方法(string),
     *      'params'    => 方法参数(array),
     *  ]
     * ]
     */
    protected static function _LockInfo(): array
    {
        $res = [];
        self::_Search(self::WildcardToRegex(self::$_LOCK . '*'), function ($key, $value) use (&$res) {
            $res[$key] = $value;
        });
        return $res;
    }

    /**
     * 原子操作
     *  - 无法对锁本身进行原子性操作
     *  - 只保证handler是否被原子性触发，对其逻辑是否抛出异常不负责
     *  - handler尽可能避免超长阻塞
     *  - lockKey会被自动设置特殊前缀#lock#，可以通过Cache::LockInfo进行查询
     *
     * @param string $lockKey
     * @param Closure $handler
     * @param bool $blocking
     * @return bool
     */
    protected static function _Atomic(string $lockKey, Closure $handler, bool $blocking = false): bool
    {
        $func = __FUNCTION__;
        $result = false;
        if ($blocking) {
            $startTime = time();
            while ($blocking) {
                // 阻塞保险
                if (time() >= $startTime + self::$fuse) {return false;}
                // 创建锁
                apcu_entry($lock = self::GetLockKey($lockKey), function () use (
                    $lockKey, $handler, $func, &$result, &$blocking
                ) {
                    $res = call_user_func($handler);
                    $result = true;
                    $blocking = false;
                    return [
                        'timestamp' => microtime(true),
                        'method'    => $func,
                        'params'    => [$lockKey, '\Closure'],
                        'result'    => $res
                    ];
                });
            }
        } else {
            // 创建锁
            apcu_entry($lock = self::GetLockKey($lockKey), function () use (
                $lockKey, $handler, $func, &$result
            ) {
                $res = call_user_func($handler);
                $result = true;
                return [
                    'timestamp' => microtime(true),
                    'method'    => $func,
                    'params'    => [$lockKey, '\Closure'],
                    'result'    => $res
                ];
            });
        }
        if ($result) {
            apcu_delete($lock);
        }
        return $result;
    }

    /**
     * 设置缓存
     *  - NX和XX将会阻塞直至成功
     *  - 阻塞最大时长受fuse保护，默认60s
     *  - 抢占式锁
     *
     * @param string $key
     * @param mixed $value
     * @param array $optional = [
     *  'NX',
     *      Only set the key if it doesn't exist.
     *  'XX',
     *      Only set the key if it already exists.
     *  'EX'   => 60,
     *      expire 60 seconds.
     *  'EXAT' => time() + 10,
     *      expire in 10 seconds.
     * ]
     * @return bool
     */
    protected static function _Set(string $key, mixed $value, array $optional = []): bool
    {
        $ttl = intval($optional['EX'] ?? (isset($optional['EXAT']) ? ($optional['EXAT'] - time()) : 0));
        if (in_array('NX', $optional)) {
            $startTime = time();
            while (!apcu_add($key, $value, $ttl)) {
                // 阻塞保险
                if (time() >= $startTime + self::$fuse) {return false;}
            }
            return true;
        }
        if (in_array('XX', $optional)) {
            $startTime = time();
            $blocking = true;
            while ($blocking) {
                apcu_entry($key, function () use ($value, &$blocking) {
                    $blocking = false;
                    return $value;
                }, $ttl);
                // 阻塞保险
                if (time() >= $startTime + self::$fuse) {return false;}
            }
            return true;
        }
        return (bool)apcu_store($key, $value, $ttl);
    }

    /**
     * 自增
     *
     * @param string $key
     * @param int|float $value
     * @param int $ttl
     * @return bool|int|float
     */
    protected static function _Incr(string $key, int|float $value = 1, int $ttl = 0): bool|int|float
    {
        $func = __FUNCTION__;
        $result = false;
        $params = func_get_args();
        self::_Atomic($key, function () use (
            $key, $value, $ttl, $func, $params, &$result
        ) {
            $v = self::_Get($key, 0);
            if (is_numeric($v)) {
                self::_Set($key, $value = $v + $value, [
                    'EX' => $ttl
                ]);
                $result = $value;
            }
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
     * @param string $key
     * @param int|float $value
     * @param int $ttl
     * @return bool|int|float
     */
    protected static function _Decr(string $key, int|float $value = 1, int $ttl = 0): bool|int|float
    {
        $func = __FUNCTION__;
        $result = false;
        $params = func_get_args();
        self::_Atomic($key, function () use (
            $key, $value, $ttl, $func, $params, &$result
        ) {
            $v = self::_Get($key, 0);
            if (is_numeric($v)) {
                self::_Set($key, $value = $v - $value, [
                    'EX' => $ttl
                ]);
                $result = $value;
            }
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
     * 判断缓存键
     *
     * @param string ...$key
     * @return array returned that contains all existing keys, or an empty array if none exist.
     */
    protected static function _Exists(string ...$key): array
    {
        return apcu_exists($key);
    }

    /**
     * 获取缓存值
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    protected static function _Get(string $key, mixed $default = null): mixed
    {
        $res = apcu_fetch($key, $success);
        return $success ? $res : $default;
    }

    /**
     * 移除缓存
     *
     * @param string ...$keys
     * @return array returns list of failed keys.
     */
    protected static function _Del(string ...$keys): array
    {
        return apcu_delete($keys);
    }

    /**
     * 获取所有key
     *
     * @param string|null $regex
     * @return array
     */
    protected static function _Keys(null|string $regex = null): array
    {
        $keys = [];
        if ($info = apcu_cache_info()) {
            $keys = array_column($info['cache_list'] ?? [], 'info');
            if ($regex !== null) {
                $keys = array_values(array_filter($keys, function($key) use ($regex) {
                    return preg_match($regex, $key);
                }));
            }
        }
        return $keys;
    }
}

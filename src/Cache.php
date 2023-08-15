<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache;

use APCUIterator;
use Closure;
use Error;
use const APC_ITER_ALL;

/**
 * 基于APCu的进程共享内存
 *
 * @method static bool Set(string $key, mixed $value, array $optional = []) 设置缓存值
 * @method static mixed Get(string $key, mixed $default = null) 获取缓存值
 * @method static bool Del(string ...$keys) 移除缓存
 * @method static array Keys(null|string $regex = null) 获取缓存键
 * @method static array Exists(string ...$keys) 判断缓存键
 *
 * @method static bool HSet(string $key, string|int $hashKey, mixed $hashValue) Hash 设置
 * @method static bool HDel(string $key, string|int ...$hashKey) Hash 移除
 * @method static mixed HGet(string $key, string|int $hashKey, mixed $default = null) Hash 获取
 * @method static array HKeys(string $key, null|string $regex = null) Hash keys
 * @method static array HExists(string $key, string|int ...$hashKey) Hash key 判断
 *
 * @method static void Search(string $regex, Closure $handler, int $chunkSize = 100) 搜索键值 - 正则匹配
 * @method static array LockInfo() 获取锁信息
 * @method static bool Clear() 清理所有缓存
 */
class Cache
{
    /** @var string 写锁 */
    const LOCK = '#write-lock#';

    /** @var bool 忽略使用 */
    public static bool $ignore = true;
    /** @var int 阻塞保险 */
    public static int $fuse = 60;

    /**
     * @link self::_Set() Set()
     * @link self::_Get() Get()
     * @link self::_Del() Del()
     * @link self::_Keys() Keys()
     * @link self::_Exists() Exists()
     * @link self::_HSet() HSet()
     * @link self::_HGet() HGet()
     * @link self::_HDel() HDel()
     * @link self::_HKeys() HKeys()
     * @link self::_HExists() HExists()
     * @link self::_Search() Search()
     * @link self::_Clear() Clear()
     * @link self::_LockInfo() LockInfo()
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if (method_exists(self::class, "_$name")) {
            if (!self::$ignore) {
                if (!extension_loaded('apcu')) {
                    throw new Error('PHP-ext apcu not enable. ');
                }
                if (!apcu_enabled()) {
                    throw new Error('You need run cache-enable.sh. ');
                }
            }
            return call_user_func([self::class, "_$name"], ...$arguments);
        }
        $name = self::class . '::' . $name;
        throw new Error("Call to undefined method $name. ");
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
    private static function _Set(string $key, mixed $value, array $optional = []): bool
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
     * 判断缓存键
     *
     * @param string ...$key
     * @return array
     */
    private static function _Exists(string ...$key): array
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
    private static function _Get(string $key, mixed $default = null): mixed
    {
        $res = apcu_fetch($key, $success);
        return $success ? $res : $default;
    }

    /**
     * 移除缓存
     *
     * @param string ...$keys
     * @return bool
     */
    private static function _Del(string ...$keys): bool
    {
        return (bool)apcu_delete($keys);
    }

    /**
     * 获取所有key
     *
     * @param string|null $regex
     * @return array
     */
    private static function _Keys(null|string $regex = null): array
    {
        $keys = [];
        if ($info = apcu_cache_info()) {
            $keys = array_column($info['cache_list'] ?? [], 'key');
            if ($regex !== null) {
                $keys = array_values(array_filter($keys, function($key) use ($regex) {
                    return preg_match($regex, $key);
                }));
            }
        }
        return $keys;
    }

    /**
     * 搜索
     *
     * @param string $regex 正则
     * @param Closure $handler = function (array $current) {}
     * @param int $chunkSize
     * @return void
     */
    private static function _Search(string $regex, Closure $handler, int $chunkSize = 100): void
    {
        $iterator = new APCUIterator($regex, APC_ITER_ALL, $chunkSize);
        while ($iterator->valid()) {
            call_user_func($handler, $iterator->current());
            $iterator->next();
        }
    }

    /**
     * hash 设置
     *  - 阻塞最大时长受fuse保护，默认60s
     *  - 抢占式锁
     *
     * @param string $key
     * @param string|int $hashKey
     * @param mixed $hashValue
     * @return bool
     */
    private static function _HSet(string $key, string|int $hashKey, mixed $hashValue): bool
    {
        $startTime = time();
        $blocking = true;
        while ($blocking) {
            // 创建锁
            apcu_entry(self::LOCK, function () use ($key, $hashKey, $hashValue, &$blocking) {
                $blocking = false;
                $hash = self::Get($key, []);
                $hash[$hashKey] = $hashValue;
                self::Set($key, $hash);
                return [
                    'timestamp' => microtime(true),
                    'method'    => __FUNCTION__,
                    'params'    => func_get_args()
                ];
            });
            // 移除锁
            if ($blocking) {
                self::Del(self::LOCK);
            }
            // 阻塞保险
            if (time() >= $startTime + self::$fuse) {return false;}
        }
        return true;
    }

    /**
     * hash 移除
     *  - 阻塞最大时长受fuse保护，默认60s
     *  - 抢占式锁
     *
     * @param string $key
     * @param string|int ...$hashKey
     * @return bool
     */
    private static function _HDel(string $key, string|int ...$hashKey): bool
    {
        $startTime = time();
        $blocking = true;
        while ($blocking) {
            // 创建锁
            apcu_entry(self::LOCK, function () use ($key, $hashKey, &$blocking) {
                $blocking = false;
                $hash = self::Get($key, []);
                foreach ($hashKey as $hk) {
                    unset($hash[$hk]);
                }
                self::Set($key, $hash);
                return [
                    'timestamp' => microtime(true),
                    'method'    => __FUNCTION__,
                    'params'    => func_get_args()
                ];
            });
            // 移除锁
            if ($blocking) {
                self::Del(self::LOCK);
            }
            // 阻塞保险
            if (time() >= $startTime + self::$fuse) {return false;}
        }
        return true;
    }

    /**
     * hash 获取
     *
     * @param string $key
     * @param string|int $hashKey
     * @param mixed|null $default
     * @return mixed
     */
    private static function _HGet(string $key, string|int $hashKey, mixed $default = null): mixed
    {
        $hash = self::Get($key, []);
        return $hash[$hashKey] ?? $default;
    }

    /**
     * hash key 判断
     *
     * @param string $key
     * @param string|int ...$hashKey
     * @return array
     */
    private static function _HExists(string $key, string|int ...$hashKey): array
    {
        $hash = self::Get($key, []);
        $result = [];
        foreach ($hashKey as $hk) {
            if (isset($hash[$hk])) {
                $result[$hk] = true;
            }
        }
        return $result;
    }

    /**
     * hash keys
     *
     * @param string $key
     * @param string|null $regex
     * @return array
     */
    private static function _HKeys(string $key, null|string $regex = null): array
    {
        $hash = self::Get($key, []);
        $keys = array_keys($hash);
        if ($regex !== null) {
            $keys = array_values(array_filter($keys, function($key) use ($regex) {
                return preg_match($regex, $key);
            }));
        }
        return $keys;
    }

    /**
     * 缓存释放
     *
     * @return bool
     */
    private static function _Clear(): bool
    {
        return apcu_clear_cache();
    }

    /**
     * 获取锁信息
     *
     * @return array = [
     *  'timestamp' => float,
     *  'method'    => string,
     *  'params'    => array,
     * ]
     */
    private static function _LockInfo(): array
    {
        return self::Get(self::LOCK, []);
    }
}
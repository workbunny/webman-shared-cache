<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache;

use Closure;
use Error;
use Workbunny\WebmanSharedCache\Traits\BasicMethods;
use Workbunny\WebmanSharedCache\Traits\HashMethods;

/**
 * 基于APCu的进程共享内存
 *
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
 * @method static bool HSet(string $key, string|int $hashKey, mixed $hashValue) Hash 设置
 * @method static bool HDel(string $key, string|int ...$hashKey) Hash 移除
 * @method static mixed HGet(string $key, string|int $hashKey, mixed $default = null) Hash 获取
 * @method static array HKeys(string $key, null|string $regex = null) Hash keys
 * @method static bool|int|float HIncr(string $key, string|int $hashKey, int|float $value = 1) Hash 自增
 * @method static bool|int|float HDecr(string $key, string|int $hashKey, int|float $value = 1) Hash 自减
 * @method static array HExists(string $key, string|int ...$hashKey) Hash key 判断
 *
 * @method static array LockInfo() 获取锁信息
 * @method static array KeyInfo(string $key) 获取键信息
 * @method static array Info(bool $limited = false) 获取信息
 * @method static bool Clear() 清理所有缓存
 */
class Cache
{
    use BasicMethods;
    use HashMethods;

    /** @var int 阻塞保险 */
    public static int $fuse = 60;

    /**
     * @link HashMethods
     * @link BasicMethods
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments)
    {
        if (method_exists(self::class, "_$name")) {
            if (!extension_loaded('apcu')) {
                throw new Error('PHP-ext apcu not enable. ');
            }
            if (!apcu_enabled()) {
                throw new Error('You need run shared-cache-enable.sh. ');
            }
            return call_user_func([self::class, "_$name"], ...$arguments);
        }
        $name = self::class . '::' . $name;
        throw new Error("Call to undefined method $name. ");
    }
}
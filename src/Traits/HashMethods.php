<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Traits;

/**
 * @method static bool HSet(string $key, string|int $hashKey, mixed $hashValue) Hash 设置
 * @method static bool HDel(string $key, string|int ...$hashKey) Hash 移除
 * @method static mixed HGet(string $key, string|int $hashKey, mixed $default = null) Hash 获取
 * @method static array HKeys(string $key, null|string $regex = null) Hash keys
 * @method static bool|int|float HIncr(string $key, string|int $hashKey, int|float $value = 1) Hash 自增
 * @method static bool|int|float HDecr(string $key, string|int $hashKey, int|float $value = 1) Hash 自减
 * @method static array HExists(string $key, string|int ...$hashKey) Hash key 判断
 */
trait HashMethods
{
    use BasicMethods;

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
    protected static function _HSet(string $key, string|int $hashKey, mixed $hashValue): bool
    {
        $func = __FUNCTION__;
        $params = func_get_args();
        self::_Atomic($key, function () use (
            $key, $hashKey, $hashValue, $func, $params
        ) {
            $hash = self::_Get($key, []);
            $hash[$hashKey] = $hashValue;
            self::_Set($key, $hash);
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
     * hash 自增
     *
     * @param string $key
     * @param string|int $hashKey
     * @param int|float $hashValue
     * @return bool|int|float
     */
    protected static function _HIncr(string $key, string|int $hashKey, int|float $hashValue = 1): bool|int|float
    {
        $func = __FUNCTION__;
        $result = false;
        $params = func_get_args();
        self::_Atomic($key, function () use (
            $key, $hashKey, $hashValue, $func, $params, &$result
        ) {
            $hash = self::_Get($key, []);
            if (is_numeric($v = ($hash[$hashKey] ?? 0))) {
                $hash[$hashKey] = $result = $v + $hashValue;
                self::_Set($key, $hash);
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
     * hash 自减
     *
     * @param string $key
     * @param string|int $hashKey
     * @param int|float $hashValue
     * @return bool|int|float
     */
    protected static function _HDecr(string $key, string|int $hashKey, int|float $hashValue = 1): bool|int|float
    {
        $func = __FUNCTION__;
        $result = false;
        $params = func_get_args();
        self::_Atomic($key, function () use (
            $key, $hashKey, $hashValue, $func, $params, &$result
        ) {
            $hash = self::_Get($key, []);
            if (is_numeric($v = ($hash[$hashKey] ?? 0))) {
                $hash[$hashKey] = $result = $v - $hashValue;
                self::_Set($key, $hash);
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
     * hash 移除
     *  - 阻塞最大时长受fuse保护，默认60s
     *  - 抢占式锁
     *
     * @param string $key
     * @param string|int ...$hashKey
     * @return bool
     */
    protected static function _HDel(string $key, string|int ...$hashKey): bool
    {
        $func = __FUNCTION__;
        $params = func_get_args();
        self::_Atomic($key, function () use (
            $key, $hashKey, $func, $params
        ) {
            $hash = self::_Get($key, []);
            foreach ($hashKey as $hk) {
                unset($hash[$hk]);
            }
            self::_Set($key, $hash);
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
     * hash 获取
     *
     * @param string $key
     * @param string|int $hashKey
     * @param mixed|null $default
     * @return mixed
     */
    protected static function _HGet(string $key, string|int $hashKey, mixed $default = null): mixed
    {
        $hash = self::_Get($key, []);
        return $hash[$hashKey] ?? $default;
    }

    /**
     * hash key 判断
     *
     * @param string $key
     * @param string|int ...$hashKey
     * @return array
     */
    protected static function _HExists(string $key, string|int ...$hashKey): array
    {
        $hash = self::_Get($key, []);
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
    protected static function _HKeys(string $key, null|string $regex = null): array
    {
        $hash = self::_Get($key, []);
        $keys = array_keys($hash);
        if ($regex !== null) {
            $keys = array_values(array_filter($keys, function($key) use ($regex) {
                return preg_match($regex, $key);
            }));
        }
        return $keys;
    }
}

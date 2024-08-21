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
 * @method static void HRecycle(string $key) Hash key 过期回收
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
     * @param int $ttl
     * @return bool
     */
    protected static function _HSet(string $key, string|int $hashKey, mixed $hashValue, int $ttl = 0): bool
    {
        $func = __FUNCTION__;
        $params = func_get_args();
        self::_Atomic($key, function () use (
            $key, $hashKey, $hashValue, $ttl, $func, $params
        ) {
            $hash = self::_Get($key, []);
            $hash[$hashKey] = [
                '_value'        => $hashValue,
                '_ttl'          => $ttl,
                '_timestamp'    => time()
            ];
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
     * @param int $ttl
     * @return bool|int|float
     */
    protected static function _HIncr(string $key, string|int $hashKey, int|float $hashValue = 1, int $ttl = 0): bool|int|float
    {
        $func = __FUNCTION__;
        $result = false;
        $params = func_get_args();
        self::_Atomic($key, function () use (
            $key, $hashKey, $hashValue, $ttl, $func, $params, &$result
        ) {
            $hash = self::_Get($key, []);
            $value = $hash[$hashKey]['_value'] ?? 0;
            $oldTtl = $hash[$hashKey]['_ttl'] ?? 0;
            $timestamp = $hash[$hashKey]['_timestamp'] ?? 0;
            if (is_numeric($value)) {
                $now = time();
                $value = ($oldTtl <= 0 or (($timestamp + $oldTtl) >= $now)) ? $value : 0;
                $hash[$hashKey] = [
                    '_value'        => $result = $value + $hashValue,
                    '_ttl'          => ($ttl > 0) ? $ttl : ($timestamp > 0 ? $now - $timestamp : 0),
                    '_timestamp'    => $now,
                ];
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
     * @param int $ttl
     * @return bool|int|float
     */
    protected static function _HDecr(string $key, string|int $hashKey, int|float $hashValue = 1, int $ttl = 0): bool|int|float
    {
        $func = __FUNCTION__;
        $result = false;
        $params = func_get_args();
        self::_Atomic($key, function () use (
            $key, $hashKey, $hashValue, $ttl, $func, $params, &$result
        ) {
            $hash = self::_Get($key, []);
            $value = $hash[$hashKey]['_value'] ?? 0;
            $oldTtl = $hash[$hashKey]['_ttl'] ?? 0;
            $timestamp = $hash[$hashKey]['_timestamp'] ?? 0;
            if (is_numeric($value)) {
                $now = time();
                $value = ($oldTtl <= 0 or (($timestamp + $oldTtl) >= $now)) ? $value : 0;
                $hash[$hashKey] = [
                    '_value'        => $result = $value - $hashValue,
                    '_ttl'          => ($ttl > 0) ? $ttl : ($timestamp > 0 ? $now - $timestamp : 0),
                    '_timestamp'    => $now,
                ];
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
        $now = time();
        $hash = self::_Get($key, []);
        $value = $hash[$hashKey]['_value'] ?? $default;
        $ttl = $hash[$hashKey]['_ttl'] ?? 0;
        $timestamp = $hash[$hashKey]['_timestamp'] ?? 0;
        return ($ttl <= 0 or (($timestamp + $ttl) >= $now)) ? $value : $default;
    }

    /**
     * 回收过期 hashKey
     *
     * @param string $key
     * @return void
     */
    protected static function _HRecycle(string $key): void
    {
        $func = __FUNCTION__;
        $params = func_get_args();
        self::_Atomic($key, function () use (
            $key, $func, $params
        ) {
            $hash = self::_Get($key, []);
            if (isset($hash['_ttl']) and isset($hash['_timestamp'])) {
                $now = time();
                $set = false;
                foreach ($hash as $hashKey => $hashValue) {
                    $ttl = $hashValue['_ttl'] ?? 0;
                    $timestamp = $hashValue['_timestamp'] ?? 0;
                    if ($ttl > 0 and $timestamp > 0 and $timestamp + $ttl < $now) {
                        $set = true;
                        unset($hash[$hashKey]);
                    }
                }
                if ($set) {
                    self::_Set($key, $hash);
                }
            }
            return [
                'timestamp' => microtime(true),
                'method'    => $func,
                'params'    => $params,
                'result'    => null
            ];
        }, true);
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
        $now = time();
        foreach ($hashKey as $hk) {
            $ttl = $hash[$hk]['_ttl'] ?? 0;
            $timestamp = $hash[$hk]['_timestamp'] ?? 0;
            if (($ttl <= 0 or (($timestamp + $ttl) >= $now)) and isset($hash[$hk]['_value'])) {
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
        $keys = [];
        $now = time();
        foreach ($hash as $hashKey => $hashValue) {
            $ttl = $hashValue['_ttl'] ?? 0;
            $timestamp = $hashValue['_timestamp'] ?? 0;
            if (($ttl <= 0 or (($timestamp + $ttl) >= $now)) and isset($hashValue['_value'])) {
                if ($regex !== null and preg_match($regex, $key)) {
                    continue;
                }
                $keys[] = $hashKey;
            }
        }
        return $keys;
    }
}

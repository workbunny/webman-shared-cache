<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache;

class RateLimiter
{
    public static bool $_debug = false;

    /**
     * 限流
     *
     * @param string $limitKey
     * @param string $configKey
     * @return false|array = [
     *  'limit'      => (int)窗口限制数量,
     *  'remaining'  => (int)当前窗口剩余数量,
     *  'reset'      => (int)当前窗口剩余时间,
     *  'is_limit'   => (bool)是否达到限流
     * ]
     */
    public static function traffic(string $limitKey, string $configKey = 'default'): false|array
    {
        if (
            $config = self::$_debug ?
            [
                'limit'       => 10, // 请求次数
                'window_time' => 10, // 窗口时间，单位：秒
            ] :
            config("plugin.workbunny.webman-shared-cache.rate-limit.$configKey", [])
        ) {
            $data = [];
            $blocking = false;
            while (!$blocking) {
                $blocking = Cache::Atomic($limitKey, function () use ($limitKey, $config, &$data) {
                    if (
                        $cache = Cache::Get($limitKey) and
                        ($reset = $cache['expired'] - time()) >= 0
                    ) {
                        $cache['count'] += 1;
                        Cache::Set($limitKey, $cache, [
                            'EX' => $config['window_time']
                        ]);
                        return $data = [
                            'limit'     => $limit = $config['limit'],
                            'remaining' => max($limit - $cache['count'], 0),
                            'reset'     => $reset,
                            'is_limit'  => $cache['count'] > $config['limit'],
                        ];
                    }
                    Cache::Set($limitKey, [
                        'created' => $now = time(),
                        'expired' => $now + $config['window_time'],
                        'count'   => 1
                    ], [
                        'EX' => $config['window_time']
                    ]);
                    return $data = [
                        'limit'     => $limit = $config['limit'],
                        'remaining' => max($limit - 1, 0),
                        'reset'     => $config['window_time'],
                        'is_limit'  => false,
                    ];
                });
            }
            return $data;
        }
        return false;
    }
}

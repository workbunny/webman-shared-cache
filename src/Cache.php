<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache;

use Closure;
use Error;
use Workbunny\WebmanSharedCache\Traits\BasicMethods;
use Workbunny\WebmanSharedCache\Traits\ChannelMethods;
use Workbunny\WebmanSharedCache\Traits\HashMethods;

/**
 * 基于APCu的进程共享内存
 *
 * @link HashMethods hash相关
 * @link BasicMethods 基础功能
 * @link ChannelMethods 通道相关
 */
class Cache
{
    use BasicMethods;
    use HashMethods;
    use ChannelMethods;

    /** @var int 阻塞保险 */
    public static int $fuse = 60;

    /**
     * @link HashMethods hash相关
     * @link BasicMethods 基础功能
     * @link ChannelMethods 通道相关
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
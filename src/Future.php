<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache;

use Closure;
use Error;
use Workerman\Events\Event;
use Workerman\Events\EventInterface;
use Workerman\Worker;

class Future
{
    public static bool $debug = false;
    public static ?Closure $debugFunc = null;
    public static array $debugArgs = [];

    /** @var bool 使用信号监听 */
    public static bool $useSignal = false;

    // todo 因为event等事件循环库是对标准信号的监听，所以不能使用自定实时信号SIGRTMIN ~ SIGRTMAX
    // todo 暂时使用SIGPOLL，异步IO监听信号，可能影响异步文件IO相关的触发
    /** @var int 监听的信号 */
    public static int $signal = \SIGPOLL;

    /**
     * @var array = [id => func]
     */
    protected static array $_futures = [];

    /**
     * @param Closure $func
     * @param array $args
     * @param float|int $interval
     * @return int|false
     */
    public static function add(Closure $func, array $args = [], float|int $interval = 0): int|false
    {
        if (self::$debug) {
            self::$debugFunc = $func;
            self::$debugArgs = $args;
            return 1;
        }

        if (!Worker::$globalEvent) {
            throw new Error("Event driver error. ");
        }

        // 使用信号监听
        if (self::$useSignal) {
            $id = false;
            if (
                method_exists(Worker::$globalEvent, 'onSignal')
                    ? Worker::$globalEvent->onSignal(self::$signal, function () use ($func, $args) {
                        call_user_func($func, $args);
                    })
                    : Worker::$globalEvent->add(self::$signal, EventInterface::EV_SIGNAL, $func, $args)
            ) {
                self::$_futures[$id = 0] = $func;
            }
        }
        // 使用定时器轮询
        else {
            $interval = $interval > 0 ? $interval : (Worker::$eventLoopClass === Event::class ? 0 : 0.001);
            if (
                $id = method_exists(Worker::$globalEvent, 'delay')
                    ? Worker::$globalEvent->delay($interval, $func, $args)
                    : Worker::$globalEvent->add($interval, EventInterface::EV_TIMER, $func, $args)
            ) {
                self::$_futures[$id] = $func;
            }
        }


        return $id;
    }

    /**
     * @param int|null $id
     * @return void
     */
    public static function del(int|null $id = null): void
    {
        if (self::$debug) {
            self::$debugFunc = null;
            self::$debugArgs = [];
            return;
        }

        if (!Worker::$globalEvent) {
            throw new Error("Event driver error. ");
        }

        $futures = $id === null ? self::$_futures : [$id => (self::$_futures[$id] ?? null)];
        foreach ($futures as $id => $fuc) {
            // 使用信号监听
            if (self::$useSignal and $id === 0) {
                if (method_exists(Worker::$globalEvent, 'offSignal')) {
                    Worker::$globalEvent->offSignal(self::$signal);
                } else {
                    Worker::$globalEvent->del(self::$signal, EventInterface::EV_SIGNAL);
                }
            }
            // 使用定时器轮询
            else {
                if (method_exists(Worker::$globalEvent, 'offDelay')) {
                    Worker::$globalEvent->offDelay($id);
                } else {
                    Worker::$globalEvent->del($id, EventInterface::EV_TIMER);
                }
            }
            unset(self::$_futures[$id]);
        }
    }

}

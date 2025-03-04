<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache;

use Closure;
use Error;
use Workerman\Coroutine;
use Workerman\Events\Event;
use Workerman\Events\EventInterface;
use Workerman\Worker;

class Future
{
    public static bool $debug = false;
    public static ?Closure $debugFunc = null;
    public static array $debugArgs = [];


    public const DRIVER_TIMER      = 'timer';
    public const DRIVER_SIGNAL     = 'signal';
    public const DRIVER_COROUTINE  = 'coroutine';

    /** @var string 默认驱动 */
    public static string $driver = self::DRIVER_TIMER;

    // todo 因为event等事件循环库是对标准信号的监听，所以不能使用自定实时信号SIGRTMIN ~ SIGRTMAX
    // todo 暂时使用SIGPOLL，异步IO监听信号，可能影响异步文件IO相关的触发
    /** @var int 监听的信号 */
    public static int $signal = 29;//SIGPOLL

    /**
     * @var array<int|string, callable|Coroutine\Coroutine\CoroutineInterface> = [id => func|coroutine]
     */
    protected static array $_futures = [];

    /**
     * @var Closure|null
     */
    protected static ?Closure $_signalCallback = null;

    /**
     * @param Closure|null $func
     * @return void
     */
    public static function setSignalCallback(?Closure $func): void
    {
        self::$_signalCallback = $func;
    }

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

        switch (self::$driver) {
            # 协程
            case self::DRIVER_COROUTINE:
                $coroutine = Coroutine::create(function () use ($func, $args) {
                    Coroutine::suspend();
                    call_user_func($func, $args);
                });
                $id = $coroutine->id();
                self::$_futures[$id] = $coroutine;
                break;
            # 信号
            case self::DRIVER_SIGNAL:
                $func = function () use ($func, $args) {
                    // 触发信号原回调
                    if (self::$_signalCallback) {
                        call_user_func(self::$_signalCallback);
                    }
                    // 触发信号通道回调
                    call_user_func($func, $args);
                };
                if (method_exists(Worker::$globalEvent, 'onSignal')) {
                    Worker::$globalEvent->onSignal(self::$signal, $func);
                } else {
                    Worker::$globalEvent->add(self::$signal, EventInterface::EV_SIGNAL, $func);
                }
                self::$_futures[$id = 0] = $func;
                break;
            # 默认定时器
            case self::DRIVER_TIMER:
            default:
                $interval = $interval > 0 ? $interval : (Worker::$eventLoopClass === Event::class ? 0 : 0.001);
                if (
                    $id = method_exists(Worker::$globalEvent, 'delay')
                        ? Worker::$globalEvent->delay($interval, $func, $args)
                        : Worker::$globalEvent->add($interval, EventInterface::EV_TIMER, $func, $args)
                ) {
                    self::$_futures[$id] = $func;
                }
                break;
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
            switch (self::$driver) {
                # 协程
                case self::DRIVER_COROUTINE:
                    if (
                        ($coroutine = self::$_futures[$id] ?? null) and
                        $coroutine instanceof Coroutine\Coroutine\CoroutineInterface
                    ) {
                        $coroutine->resume();
                    }
                    break;
                case self::DRIVER_SIGNAL:
                    if ($id === 0) {
                        // 如果有信号回调，则恢复信号回调
                        if (self::$_signalCallback) {
                            if (method_exists(Worker::$globalEvent, 'onSignal')) {
                                Worker::$globalEvent->onSignal(self::$signal, self::$_signalCallback);
                            } else {
                                Worker::$globalEvent->add(self::$signal, EventInterface::EV_SIGNAL, self::$_signalCallback);
                            }
                        } else {
                            if (method_exists(Worker::$globalEvent, 'offSignal')) {
                                Worker::$globalEvent->offSignal(self::$signal);
                            } else {
                                Worker::$globalEvent->del(self::$signal, EventInterface::EV_SIGNAL);
                            }
                        }
                    }
                    break;
                # 默认定时器
                case self::DRIVER_TIMER:
                    if (method_exists(Worker::$globalEvent, 'offDelay')) {
                        Worker::$globalEvent->offDelay($id);
                    } else {
                        Worker::$globalEvent->del($id, EventInterface::EV_TIMER);
                    }
                    break;
                default:
                    break;
            }
            unset(self::$_futures[$id]);
        }
    }

}

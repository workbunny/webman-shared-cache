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

    /**
     * @var array = [id => func]
     */
    protected static array $_futures = [];

    /**
     * @param Closure $func
     * @param array $args
     * @param float|int|null $interval
     * @return int|false
     */
    public static function add(Closure $func, array $args = [], float|int|null $interval = null): int|false
    {
        if (self::$debug) {
            self::$debugFunc = $func;
            self::$debugArgs = $args;
            return 1;
        }

        if (!Worker::$globalEvent) {
            throw new Error("Event driver error. ");
        }

        $interval = Worker::$eventLoopClass === Event::class ? 0 : 0.001;
        if ($id = method_exists(Worker::$globalEvent, 'delay')
            ? Worker::$globalEvent->delay($interval, $func, $args)
            : Worker::$globalEvent->add($interval, EventInterface::EV_TIMER, $func, $args)
        ) {
            self::$_futures[$id] = $func;
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

        if ($id !== null) {
            if (method_exists(Worker::$globalEvent, 'offDelay')) {
                Worker::$globalEvent->offDelay($id);
            } else {
                Worker::$globalEvent->del($id, EventInterface::EV_TIMER);
            }
            unset(self::$_futures[$id]);
            return;
        }

        foreach(self::$_futures as $id => $fuc) {
            if (method_exists(Worker::$globalEvent, 'offDelay')) {
                Worker::$globalEvent->offDelay($id);
            } else {
                Worker::$globalEvent->del($id, EventInterface::EV_TIMER);
            }
            unset(self::$_futures[$id]);
        }
    }

}

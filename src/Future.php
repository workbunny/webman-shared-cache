<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache;

use Closure;
use Error;
use Workerman\Events\Event;
use Workerman\Events\EventInterface;
use Workerman\Worker;

class Future
{
    /**
     * @var array = [id => func]
     */
    protected static array $_futures = [];

    /**
     * @param Closure $func
     * @param array $args
     * @return int|false
     */
    public static function add(Closure $func, array $args = []): int|false
    {
        if (!Worker::$globalEvent) {
            throw new Error("Event driver error. ");
        }

        if ($id = Worker::$globalEvent->add(
            Worker::$eventLoopClass === Event::class ? 0 : 0.001,
            EventInterface::EV_TIMER,
            $func,
            $args
        )) {
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
        if (!Worker::$globalEvent) {
            throw new Error("Event driver error. ");
        }

        if ($id !== null) {
            Worker::$globalEvent->del(
                $id, EventInterface::EV_TIMER);
            unset(self::$_futures[$id]);
            return;
        }

        foreach(self::$_futures as $id => $fuc) {
            Worker::$globalEvent->del(
                $id, EventInterface::EV_TIMER);
            unset(self::$_futures[$id]);
        }
    }

}

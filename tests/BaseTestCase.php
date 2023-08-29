<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Tests;

use Closure;
use PHPUnit\Framework\TestCase;

class BaseTestCase extends TestCase
{
    protected function childExec(Closure $closure, ... $param): void
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new \Error('Fork faild. ');
        } else if ($pid) {
            pcntl_waitpid(-1, $status);
        } else {
            call_user_func($closure, ...$param);
            exit(0);
        }
    }
}

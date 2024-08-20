<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{
    /**
     * 兼容webman console，需重写
     *
     * @var string
     */
    protected static string $defaultName = '';

    /**
     * 兼容webman console，需重写
     *
     * @var string
     */
    protected static string $defaultDescription = '';

    /**
     * 输出info
     *
     * @param OutputInterface $output
     * @param string $message
     * @return void
     */
    protected function info(OutputInterface $output, string $message): void
    {
        $output->writeln("ℹ️ $message");
    }

    /**
     * 输出error
     *
     * @param OutputInterface $output
     * @param string $message
     * @return int
     */
    protected function error(OutputInterface $output, string $message): int
    {
        $output->writeln("❌ $message");
        return self::FAILURE;
    }

    /**
     * 输出success
     *
     * @param OutputInterface $output
     * @param string $message
     * @return int
     */
    protected function success(OutputInterface $output, string $message): int
    {
        $output->writeln("✅ $message");
        return self::SUCCESS;
    }
}
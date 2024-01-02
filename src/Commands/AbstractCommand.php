<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{

    protected function info(OutputInterface $output, string $message): void
    {
        $output->writeln("ℹ️ $message");
    }

    protected function error(OutputInterface $output, string $message): int
    {
        $output->writeln("❌ $message");
        return self::FAILURE;
    }

    protected function success(OutputInterface $output, string $message): int
    {
        $output->writeln("✅ $message");
        return self::SUCCESS;
    }
}
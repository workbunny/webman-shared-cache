<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Workbunny\WebmanSharedCache\Cache;

class WorkbunnyWebmanSharedCacheClean extends AbstractCommand
{
    protected static string $defaultName = 'workbunny:shared-cache-clean';
    protected static string $defaultDescription = 'Remove all workbunny/webman-shared-cache caches. ';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName(static::$defaultName)->setDescription(static::$defaultDescription);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Cache::Clear();
        return $this->success($output, "All caches removed successfully. ");
    }
}

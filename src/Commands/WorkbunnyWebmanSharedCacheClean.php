<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Workbunny\WebmanSharedCache\Cache;

class WorkbunnyWebmanSharedCacheClean extends AbstractCommand
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('workbunny:shared-cache-clean')
            ->setDescription('Remove all workbunny/webman-shared-cache caches. ');
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

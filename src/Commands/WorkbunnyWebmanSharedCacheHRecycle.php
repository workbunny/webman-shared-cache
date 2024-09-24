<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Commands;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Workbunny\WebmanSharedCache\Cache;

class WorkbunnyWebmanSharedCacheHRecycle extends AbstractCommand
{
    protected static $defaultName = 'workbunny:shared-cache-hrecycle';
    protected static $defaultDescription = 'Manually recycle expired hashKeys. ';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName(static::$defaultName)->setDescription(static::$defaultDescription);
        $this->addOption('key', 'k', InputOption::VALUE_OPTIONAL, 'Cache Key. ');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = $input->getOption('key');
        if ($key) {
            Cache::HRecycle($key);
        } else {
            $progressBar = new ProgressBar($output);
            $progressBar->start();
            $keys = Cache::Keys();
            $progressBar->setMaxSteps(count($keys));
            foreach ($keys as $key) {
                Cache::HRecycle($key);
                $progressBar->advance();
            }
            $progressBar->finish();
        }
        return $this->success($output, 'HRecycle Success. ');
    }
}

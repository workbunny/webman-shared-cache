<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Commands;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Workbunny\WebmanSharedCache\Cache;

class WorkbunnyWebmanSharedCacheHRecycle extends AbstractCommand
{
    protected static string $defaultName = 'workbunny:shared-cache-hrecycle';
    protected static string $defaultDescription = 'Manually recycle expired hashKeys. ';

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
                $value = Cache::Get($key);
                if (
                    ($value['_ttl'] ?? null) and ($value['_timestamp'] ?? null)) {
                    Cache::HRecycle($key);
                }
            }
        }
        $headers = ['name', 'value'];
        $rows = [];
        // todo

        $table = new Table($output);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $table->render();

        return self::SUCCESS;
    }
}

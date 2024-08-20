<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Commands;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkbunnyWebmanSharedCacheList extends AbstractCommand
{
    protected static string $defaultName = 'workbunny:shared-cache-list';
    protected static string $defaultDescription = 'Show workbunny/webman-shared-cache caches list. ';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName(static::$defaultName)->setDescription(static::$defaultDescription);
        $this->addOption('page', 'p', InputOption::VALUE_OPTIONAL, 'Page. ', 1);
        $this->addOption('size', 's', InputOption::VALUE_OPTIONAL, 'Page size. ', 20);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $page = $input->getOption('page');
        $size = $input->getOption('size');
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

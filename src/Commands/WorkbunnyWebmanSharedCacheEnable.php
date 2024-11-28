<?php declare(strict_types=1);

namespace Workbunny\WebmanSharedCache\Commands;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class WorkbunnyWebmanSharedCacheEnable extends AbstractCommand
{
    protected static $defaultName = 'workbunny:shared-cache-enable';
    protected static $defaultDescription = 'Enable APCu cache with specified settings. ';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Enable APCu cache with specified settings.')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Specify configuration name', 'apcu-cache.ini')
            ->addOption('target', 't', InputOption::VALUE_REQUIRED, 'Specify target location', '/usr/local/etc/php/conf.d')
            ->addOption('size', 'si', InputOption::VALUE_REQUIRED, 'Configure apcu.shm_size', '1024M')
            ->addOption('segments', 'se', InputOption::VALUE_REQUIRED, 'Configure apcu.shm_segments', 1)
            ->addOption('mmap', 'm', InputOption::VALUE_REQUIRED, 'Configure apcu.mmap_file_mask', '')
            ->addOption('gc_ttl', 'gc', InputOption::VALUE_REQUIRED, 'Configure apcu.gc_ttl', 3600);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fileName = $input->getOption('file');
        $target = $input->getOption('target');
        $shmSize = $input->getOption('size');
        $shmSegments = $input->getOption('segments');
        $mmapFileMask = $input->getOption('mmap');
        $gcTtl = $input->getOption('gc_ttl');

        if (!is_dir($target)) {
            return $this->error($output, "Target directory does not exist: $target. ");
        }
        $configContent = <<<EOF
apc.enabled=1
apc.enable_cli=1
apc.shm_segments=$shmSegments
apc.shm_size=$shmSize
apc.mmap_file_mask=$mmapFileMask
apc.gc_ttl=$gcTtl
EOF;
        $filePath = "$target/$fileName";

        if (file_exists($filePath)) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion("Configuration file already exists at $filePath. Overwrite? (y/N) ", false);

            if (!$helper->ask($input, $output, $question)) {
                return $this->success($output, "Operation aborted. ");
            }
        }

        file_put_contents($filePath, $configContent);
        return $this->success($output, "Configuration file created at: $filePath. ");
    }

}

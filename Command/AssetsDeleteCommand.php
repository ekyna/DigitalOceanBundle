<?php

declare(strict_types=1);

namespace Ekyna\Bundle\DigitalOceanBundle\Command;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class AssetsDeleteCommand
 * @package Ekyna\Bundle\DigitalOceanBundle\Command
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class AssetsDeleteCommand extends Command
{
    protected static $defaultName = 'ekyna:digital-ocean:assets:delete';

    public function __construct(private readonly Filesystem $filesystem)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'The CDN directory path to delete.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $path = $input->getArgument('path');

        try {
            $this->filesystem->fileExists($path);
        } catch (FilesystemException) {
            $output->writeln("<error>Path '$path' does not exist on space.</error>");

            return;
        }

        $question = new ConfirmationQuestion("Are you sure you want to delete '$path' from CDN ?", false);
        $helper   = $this->getHelper('question');
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Abort by user.</comment>');

            return;
        }

        try {
            $this->filesystem->deleteDirectory($path);
        } catch (FilesystemException) {
            $output->writeln("<error>Failed to delete '$path' from CDN.</error>");

            return;
        }

        $output->writeln("<info>Successfully delete '$path' from CDN.</info>");
    }
}

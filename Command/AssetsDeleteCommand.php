<?php

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

    /**
     * @var Filesystem
     */
    private $filesystem;


    /**
     * Constructor.
     *
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;

        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'The CDN directory path to delete.');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');

        if (!$this->filesystem->has($path)) {
            $output->writeln("<error>Path '$path' does not exist on space.</error>");

            return;
        }

        $question = new ConfirmationQuestion("Are you sure you want to delete '$path' from CDN ?", false);
        $helper   = $this->getHelper('question');
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln("<comment>Abort by user.</comment>");

            return;
        }

        try {
            $result = $this->filesystem->deleteDir($path);
        } catch (FilesystemException $e) {
            $result = false;
        }

        if ($result) {
            $output->writeln("<info>Successfully delete '$path' from CDN.</info>");

            return;
        }

        $output->writeln("<error>Failed to delete '$path' from CDN.</error>");
    }
}

<?php

namespace Ekyna\Bundle\DigitalOceanBundle\Command;

use Ekyna\Bundle\DigitalOceanBundle\Service\CDNHelper;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class AssetsDeployCommand
 * @package Ekyna\Bundle\DigitalOceanBundle\Command
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class AssetsDeployCommand extends Command
{
    protected static $defaultName = 'ekyna:digital-ocean:assets:deploy';

    /**
     * @var CDNHelper
     */
    private $helper;

    /**
     * @var array
     */
    private $publicDir;

    /**
     * @var array
     */
    private $files;


    /**
     * Constructor.
     *
     * @param CDNHelper $helper
     * @param string    $publicDir
     */
    public function __construct(CDNHelper $helper, string $publicDir)
    {
        $this->helper    = $helper;
        $this->publicDir = rtrim($publicDir, '/') . '/';
        $this->files     = [];

        parent::__construct();
    }

    /**
     * Sets the files.
     *
     * @param array $files
     */
    public function setFiles(array $files): void
    {
        $this->files = $files;
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->deployBundles($output);

        $this->deployFiles($output);

        $this->helper->purge();
    }

    private function deployBundles(OutputInterface $output): void
    {
        /** @var \Symfony\Component\HttpKernel\KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();

        foreach ($kernel->getBundles() as $bundle) {
            if (!is_dir($originDir = $bundle->getPath() . '/Resources/public')) {
                continue;
            }

            $output->write($bundle->getName() . ': ');

            $assetDir = 'bundles/' . preg_replace('/bundle$/', '', strtolower($bundle->getName())) . '/';
            $files    = Finder::create()->ignoreDotFiles(false)->in($originDir);
            $assets   = $validDirs = $validAssets = [];

            foreach ($files as $file) {
                $path = $assetDir . $file->getRelativePathName();

                if ($file->isDir()) {
                    $validDirs[] = $path;
                } else {
                    $assets[$file->getPathname()] = $validAssets[] = $path;
                }
            }

            $this->helper->deploy($assets, function (bool $result) use ($output) {
                $output->write($result ? '<info>+</info>' : '<error>!</error>');
            });

            $callback = function (bool $result) use ($output) {
                $output->write($result ? '<info>-</info>' : '<error>!</error>');
            };

            if (!empty($dirs = array_diff($this->helper->list($assetDir, true), $validDirs))) {
                $this->helper->deleteDirectories($dirs, $callback);
            }
            if (!empty($files = array_diff($this->helper->list($assetDir, false), $validAssets))) {
                $this->helper->deleteFiles($files, $callback);
            }

            $output->writeln('');
        }
    }

    private function deployFiles(OutputInterface $output): void
    {
        if (empty($this->files)) {
            return;
        }

        $output->write('Files: ');

        $files = [];

        foreach ($this->files as $path) {
            if (!is_file($filePath = $this->publicDir . $path)) {
                throw new RuntimeException("File '$path' not found.");
            }

            $files[$filePath] = $path;
        }

        $this->helper->deploy($files, function (bool $result) use ($output) {
            $output->write($result ? '<info>+</info>' : '<error>!</error>');
        });

        $output->writeln('');
    }
}

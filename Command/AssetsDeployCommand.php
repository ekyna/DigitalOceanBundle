<?php

namespace Ekyna\Bundle\DigitalOceanBundle\Command;

use Ekyna\Bundle\DigitalOceanBundle\Service\Api;
use League\Flysystem\Filesystem;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use function file_get_contents;

/**
 * Class AssetsDeployCommand
 * @package Ekyna\Bundle\DigitalOceanBundle\Command
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class AssetsDeployCommand extends Command
{
    protected static $defaultName = 'ekyna:digital-ocean:assets:deploy';

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Api
     */
    private $api;

    /**
     * @var string
     */
    private $spaceName;

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
     * @param Filesystem $filesystem
     * @param Api        $api
     * @param string     $spaceName
     * @param string     $publicDir
     */
    public function __construct(Filesystem $filesystem, Api $api, string $spaceName, string $publicDir)
    {
        $this->filesystem  = $filesystem;
        $this->api         = $api;
        $this->spaceName   = $spaceName;
        $this->publicDir   = rtrim($publicDir, '/') . '/';
        $this->files       = [];

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

        $this->api->purgeSpace($this->spaceName);
    }

    private function deployBundles(OutputInterface $output): void
    {
        /** @var \Symfony\Component\HttpKernel\KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();

        foreach ($kernel->getBundles() as $bundle) {
            if (!is_dir($originDir = $bundle->getPath() . '/Resources/public')) {
                continue;
            }

            $output->writeln($bundle->getName());

            $assetDir    = 'bundles/' . preg_replace('/bundle$/', '', strtolower($bundle->getName())) . '/';
            $assets      = Finder::create()->ignoreDotFiles(false)->files()->in($originDir);
            $validDirs = $validAssets = [];

            $progressBar = new ProgressBar($output, $assets->count());

            foreach ($assets as $asset) {
                $path = $assetDir . $asset->getRelativePathName();

                if ($asset->isDir()) {
                    $validDirs[] = $path;
                } else {
                    $validAssets[] = $path;
                    $this->filesystem->write($path, $asset->getContents());
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $output->writeln('');

            $assets = $this->filesystem->listContents($assetDir, true);
            foreach ($assets as $object) {
                $asset = $object['path'];

                if ($object['type'] === 'dir') {
                    if (in_array($asset, $validDirs, true)) {
                        continue;
                    }
                } elseif (in_array($asset, $validAssets, true)) {
                    continue;
                }

                $this->filesystem->delete($asset);
            }
        }
    }

    private function deployFiles(OutputInterface $output): void
    {
        if (empty($this->files)) {
            return;
        }

        $output->writeln('Files');

        $progressBar = new ProgressBar($output, count($this->files));

        foreach ($this->files as $path) {
            if (!is_file($filePath = $this->publicDir . $path)) {
                throw new RuntimeException("File '$path' not found.");
            }

            $this->filesystem->write($path, file_get_contents($filePath));

            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');
    }
}

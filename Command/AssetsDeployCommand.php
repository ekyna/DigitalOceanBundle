<?php

namespace Ekyna\Bundle\DigitalOceanBundle\Command;

use Ekyna\Bundle\DigitalOceanBundle\Service\Api;
use League\Flysystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
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
     * Constructor.
     *
     * @param Filesystem $filesystem
     * @param Api        $api
     * @param string     $spaceName
     */
    public function __construct(Filesystem $filesystem, Api $api, string $spaceName)
    {
        $this->filesystem = $filesystem;
        $this->api        = $api;
        $this->spaceName  = $spaceName;

        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $kernel = $this->getApplication()->getKernel();

        foreach ($kernel->getBundles() as $bundle) {
            if (!is_dir($originDir = $bundle->getPath() . '/Resources/public')) {
                continue;
            }

            $output->writeln($bundle->getName());

            $assetDir         = 'bundles/' . preg_replace('/bundle$/', '', strtolower($bundle->getName())) . '/';
            $validAssetDirs[] = $assetDir;

            $assets      = Finder::create()->ignoreDotFiles(false)->in($originDir);
            $validAssets = $validDirs = [];

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

        $this->api->purgeSpace($this->spaceName);
    }
}

<?php

namespace Ekyna\Bundle\DigitalOceanBundle\Command;

use Ekyna\Bundle\DigitalOceanBundle\Service\CDNHelper;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

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
     * @var string
     */
    private $prefix;

    /**
     * @var array
     */
    private $files;

    /**
     * @var BundleInterface[]
     */
    private $bundles;


    /**
     * Constructor.
     *
     * @param CDNHelper   $helper
     * @param string      $publicDir
     * @param string|null $prefix
     */
    public function __construct(CDNHelper $helper, string $publicDir, string $prefix = null)
    {
        $this->helper = $helper;
        $this->publicDir = rtrim($publicDir, '/') . '/';
        $this->prefix = empty($prefix) ? '' : trim($prefix, '/') . '/';
        $this->files = [];

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
    protected function configure()
    {
        $this
            ->addOption(
                'bundle',
                'b',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Filter which bundle(s) should be deployed on CDN.'
            )
            ->addOption(
                'purge',
                'p',
                InputOption::VALUE_NONE,
                'Whether to purge CDN cache.'
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filter = $input->getOption('bundle');
        $purge = $input->getOption('purge');

        // Load bundles with public directory.
        /** @var \Symfony\Component\HttpKernel\KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();
        $this->bundles = array_filter($kernel->getBundles(), function (BundleInterface $bundle) {
            return is_dir($bundle->getPath() . '/Resources/public');
        });

        // Check filter option consistency with bundles names.
        $bundles = array_map(function (BundleInterface $bundle) {
            return preg_replace('/bundle$/', '', strtolower($bundle->getName()));
        }, $this->bundles);
        $bundles[] = 'files';
        if (!empty($filter)) {
            $unknown = array_diff($filter, $bundles);
            if (!empty($unknown)) {
                throw new InvalidOptionException("Unknown bundles: " . implode(', ', $unknown));
            }
        }

        // Confirmation message
        $message = '<info>Deploying</info>';
        if ($purge) {
            $message .= ' and <info>purging</info>';
        }
        if (empty($filter)) {
            $message .= ' all assets';
        } else {
            $names = array_values(array_map(function (string $name) {
                return "<comment>$name</comment>";
            }, $filter));
            $message .= ' ' . (
                1 < count($names)
                    ? implode(', ', array_slice($names, 0, -1)) . ' and ' . $names[count($names) - 1]
                    : $names[0]
                );

        }
        $message .= sprintf(' on <info>%s</info> CDN', $this->helper->getSpace());
        if (!empty($this->prefix)) {
            $message .= sprintf(' with prefix <info>%s</info>', trim($this->prefix, '/'));
        }
        $message .= '.';

        $output->writeln($message);

        $confirmation = new ConfirmationQuestion("Confirm deployment ?");
        if (!$this->getHelper('question')->ask($input, $output, $confirmation)) {
            $output->writeln('Abort by user.');

            return 0;
        }

        $this->deployBundles($output, $filter, $purge);

        if (empty($filter) || in_array('files', $filter, true)) {
            $this->deployFiles($output, $purge);
        }

        return 0;
    }

    /**
     * Deploys bundles assets.
     *
     * @param OutputInterface $output
     * @param array           $filter
     * @param bool            $purge Whether to purge CDN cache.
     */
    private function deployBundles(OutputInterface $output, array $filter = [], bool $purge = false): void
    {
        $purgeList = [];

        foreach ($this->bundles as $bundle) {
            $bundleName = preg_replace('/bundle$/', '', strtolower($bundle->getName()));

            if (!empty($filter) && !in_array($bundleName, $filter, true)) {
                continue;
            }

            $output->write($bundle->getName() . ': ');

            $assetDir = $this->prefix . 'bundles/' . $bundleName . '/';
            $files = Finder::create()->ignoreDotFiles(false)->in($bundle->getPath() . '/Resources/public');
            $assets = $validDirs = $validAssets = [];

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

            if ($purge) {
                $purgeList[] = $assetDir . '*';
            }
        }

        if (!empty($purgeList)) {
            $output->write('Purging ... ');
            $this->helper->purge($purgeList);
            $output->writeln('<info>done</info>');
        }
    }

    /**
     * Deploys extra files.
     *
     * @param OutputInterface $output
     * @param bool            $purge
     */
    private function deployFiles(OutputInterface $output, bool $purge = false): void
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

            $files[$filePath] = $this->prefix . $path;
        }

        $this->helper->deploy($files, function (bool $result) use ($output) {
            $output->write($result ? '<info>+</info>' : '<error>!</error>');
        });

        $output->writeln('');

        if ($purge) {
            $output->write('Purging ... ');
            $this->helper->purge(array_values($files));
            $output->writeln('<info>done</info>');
        }
    }
}

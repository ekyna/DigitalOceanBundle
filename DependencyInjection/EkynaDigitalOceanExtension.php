<?php

namespace Ekyna\Bundle\DigitalOceanBundle\DependencyInjection;

use Aws\S3\S3Client;
use Behat\Transliterator\Transliterator;
use Ekyna\Bundle\DigitalOceanBundle\Command\AssetsDeployCommand;
use Ekyna\Bundle\DigitalOceanBundle\Service\Api;
use Ekyna\Bundle\DigitalOceanBundle\Service\Registry;
use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use LogicException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class EkynaDigitalOceanExtension
 * @package Ekyna\Bundle\DigitalOceanBundle\DependencyInjection
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class EkynaDigitalOceanExtension extends Extension
{
    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $this->configureApi($config, $container);
        $this->configureSpaces($config, $container);
        $this->configureCommands($config, $container);
    }

    /**
     * Configures the registry.
     *
     * @param array            $config
     * @param ContainerBuilder $container
     */
    private function configureApi(array $config, ContainerBuilder $container): void
    {
        // Registry
        $container
            ->getDefinition(Api::class)
            ->replaceArgument(1, $config['api']['token']);
    }

    /**
     * Configures the spaces filesystems.
     *
     * @param array            $config
     * @param ContainerBuilder $container
     */
    private function configureSpaces(array $config, ContainerBuilder $container): void
    {
        if (empty($spaces = $config['spaces'])) {
            return;
        }

        // Registry
        $container
            ->getDefinition(Registry::class)
            ->replaceArgument(0, $spaces);

        // Filesystems
        foreach ($spaces as $name => $space) {
            $id = Transliterator::urlize($name, '_');

            // Client
            $container
                ->register(
                    $clientId = "ekyna_digital_ocean.{$id}.client",
                    S3Client::class
                )
                ->setArgument(0, [
                    'version'     => $space['version'],
                    'region'      => $space['region'],
                    'endpoint'    => "https://{$space['region']}.digitaloceanspaces.com",
                    'credentials' => [
                        'key'    => $space['key'],
                        'secret' => $space['secret'],
                    ],
                ])
                ->setPublic(false);

            // Adapter
            $container
                ->register(
                    $adapterId = "ekyna_digital_ocean.{$id}.adapter",
                    AwsS3Adapter::class
                )
                ->setArgument(0, new Reference($clientId)) // Client
                ->setArgument(1, $name)                    // Bucket
                ->setArgument(2, $space['prefix'])         // Prefix
                ->setPublic(false);

            // Filesystem
            $container
                ->register(
                    "ekyna_digital_ocean.{$id}.filesystem",
                    Filesystem::class
                )
                ->setArgument(0, new Reference($adapterId))
                ->setArgument(1, [
                    'visibility'      => AdapterInterface::VISIBILITY_PUBLIC,
                    'disable_asserts' => true,
                ])
                ->setPublic(false);
        }
    }

    /**
     * Configures the commands.
     *
     * @param array            $config
     * @param ContainerBuilder $container
     */
    public function configureCommands(array $config, ContainerBuilder $container): void
    {
        if (empty($name = $config['usage']['bundles'])) {
            return;
        }

        if (!array_key_exists($name, $config['spaces'])) {
            throw new LogicException(
                "Can't use '$name' space for bundles assets deploy command, as it is not defined."
            );
        }

        $id = Transliterator::urlize($name, '_');

        $container
            ->register(AssetsDeployCommand::class, AssetsDeployCommand::class)
            ->setArgument(0, new Reference("ekyna_digital_ocean.{$id}.filesystem"))
            ->setArgument(1, new Reference(Api::class))
            ->setArgument(2, $name)
            ->addTag('console.command')
            ->setPublic(false);
    }
}

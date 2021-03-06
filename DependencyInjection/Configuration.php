<?php

namespace Ekyna\Bundle\DigitalOceanBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 * @package Ekyna\Bundle\DigitalOceanBundle\DependencyInjection
 * @author  Étienne Dauvergne <contact@ekyna.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ekyna_digital_ocean');

        $this->addApiSection($rootNode);
        $this->addSpaceSection($rootNode);
        $this->addUsageSection($rootNode);

        return $treeBuilder;
    }

    /**
     * Adds the `api` section.
     *
     * @param ArrayNodeDefinition $node
     */
    private function addApiSection(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('api')
                    ->children()
                        ->scalarNode('token')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Adds the `space` section.
     *
     * @param ArrayNodeDefinition $node
     */
    private function addSpaceSection(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('spaces')
                    ->useAttributeAsKey('name', false)
                    ->arrayPrototype()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('name')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('version')
                                ->defaultValue('latest')
                            ->end()
                            ->scalarNode('region')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('prefix')
                                ->defaultNull()
                            ->end()
                            ->scalarNode('key')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('secret')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('options')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Adds the `usage` section.
     *
     * @param ArrayNodeDefinition $node
     */
    private function addUsageSection(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('assets')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('space')
                            ->info('The space name to use to deploy assets.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('prefix')
                            ->info('CDN assets root folder.')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('files')
                            ->info(
                                'An array of files paths, relative to the public ' .
                                '(or web) directory, to copy to the CDN.'
                            )
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('mime_types')
                            ->info(
                                'An associative array used to override Content-Type metadata (extension: ' .
                                'mime type), when mime type detection does not work properly.'
                            )
                            ->useAttributeAsKey('extension')
                            ->scalarPrototype()->end()
                            ->defaultValue([
                                'woff'  => 'font/woff',
                                'woff2' => 'font/woff2',
                                'ttf'   => 'font/ttf',
                                'otf'   => 'font/otf',
                                'eot'   => 'application/vnd.ms-fontobject',
                                'svg'   => 'image/svg+xml',
                            ])
                        ->end()
                        ->variableNode('gzip')
                            ->info(
                                'An array of file extensions that will be gzipped. ' .
                                'Tue or False to enable or disable for all file types.'
                            )
                            ->validate()
                                ->ifTrue(function($value) {
                                    return !is_bool($value) && !is_array($value);
                                })
                                ->thenInvalid("Configuration for 'gzip' must be a boolean or an array of extensions.")
                            ->end()
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}

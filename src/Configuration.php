<?php

declare(strict_types=1);

namespace Solido\Cors;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

class Configuration
{
    private TreeBuilder $treeBuilder;

    public function __construct()
    {
        $this->treeBuilder = new TreeBuilder('cors_configuration');
        $rootNode = $this->treeBuilder->getRootNode();
        $rootNode->addDefaultsIfNotSet();

        self::buildTree($rootNode->children());
    }

    /**
     * Builds a configuration tree.
     */
    public static function buildTree(NodeBuilder $builder): void
    {
        $builder
            ->booleanNode('enabled')->defaultTrue()->end()
            ->booleanNode('allow_credentials')->defaultTrue()->end()
            ->arrayNode('allow_origin')
                ->defaultValue(['*'])
                ->scalarPrototype()->end()
            ->end()
            ->arrayNode('allow_headers')
                ->defaultValue([])
                ->scalarPrototype()->end()
            ->end()
            ->arrayNode('expose_headers')
                ->defaultValue([])
                ->scalarPrototype()->end()
            ->end()
            ->integerNode('max_age')->defaultValue(0)->end()
            ->arrayNode('paths')
                ->defaultValue([])
                ->arrayPrototype()
                ->children()
                    ->booleanNode('enabled')->defaultTrue()->end()
                    ->booleanNode('allow_credentials')->end()
                    ->scalarNode('host')->end()
                    ->scalarNode('path')->isRequired()->end()
                    ->arrayNode('allow_origin')
                        ->scalarPrototype()->end()
                    ->end()
                    ->arrayNode('allow_headers')
                        ->scalarPrototype()->end()
                    ->end()
                    ->arrayNode('expose_headers')
                        ->scalarPrototype()->end()
                    ->end()
                    ->integerNode('max_age')->end()
                ->end()
            ->end();
    }

    /**
     * Process configurations and return the resulting array.
     *
     * @param mixed[] $configurations
     *
     * @return mixed[]
     */
    public function process(array $configurations): array
    {
        return (new Processor())->process($this->treeBuilder->buildTree(), ['cors_configuration' => $configurations]);
    }
}

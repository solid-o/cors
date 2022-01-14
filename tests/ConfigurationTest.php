<?php

declare(strict_types=1);

namespace Solido\Cors\Tests;

use Solido\Cors\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $configuration = new Configuration();
        $config = $configuration->process([]);

        self::assertSame([
            'enabled' => true,
            'allow_credentials' => true,
            'allow_origin' => ['*'],
            'allow_headers' => [],
            'expose_headers' => [],
            'max_age' => 0,
            'paths' => [],
        ], $config);
    }

    public function testBuildTreeShouldBeCallable(): void
    {
        $this->expectNotToPerformAssertions();

        $builder = new ArrayNodeDefinition('root');
        Configuration::buildTree($builder->children());
    }
}

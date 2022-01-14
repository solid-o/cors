<?php

declare(strict_types=1);

namespace Solido\Cors;

use Solido\Common\AdapterFactory;
use Solido\Common\AdapterFactoryInterface;

use function Safe\preg_match;

class HandlerFactory implements HandlerFactoryInterface
{
    /**
     * @internal
     *
     * @var array<string, mixed>
     * @phpstan-var array{enabled: bool, allow_credentials: bool, allow_origin: string[], allow_headers: string[], expose_headers: string[], max_age: int, paths: array{enabled: bool, allow_credentials?: bool, host?: string, path: string, allow_origin?: string[], allow_headers?: string[], expose_headers?: string[], max_age?: int}[]}
     */
    protected array $config;
    private AdapterFactoryInterface $adapterFactory;

    /**
     * @param array<string, mixed> $configurations
     */
    public function __construct(array $configurations = [])
    {
        $this->config = (new Configuration())->process($configurations);
        $this->adapterFactory = new AdapterFactory();
    }

    public function setAdapterFactory(AdapterFactoryInterface $adapterFactory): void
    {
        $this->adapterFactory = $adapterFactory;
    }

    public function factory(string $path, string $host): ?RequestHandlerInterface
    {
        $configuration = $this->config;
        foreach ($this->config['paths'] as $config) {
            if (isset($config['host']) && ! preg_match('#' . $config['host'] . '#', $host)) {
                continue;
            }

            $pathRegex = $config['path'];
            if (! preg_match('#' . $pathRegex . '#', $path)) {
                continue;
            }

            $configuration = $config;
            break;
        }

        if ($configuration['enabled'] === false) {
            return null;
        }

        $handler = new RequestHandler(
            $configuration['allow_credentials'] ?? $this->config['allow_credentials'],
            $configuration['allow_origin'] ?? $this->config['allow_origin'],
            $configuration['allow_headers'] ?? $this->config['allow_headers'],
            $configuration['expose_headers'] ?? $this->config['expose_headers'],
            $configuration['max_age'] ?? $this->config['max_age']
        );

        $handler->setAdapterFactory($this->adapterFactory);

        return $handler;
    }
}

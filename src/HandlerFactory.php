<?php

declare(strict_types=1);

namespace Solido\Cors;

use function Safe\preg_match;

class HandlerFactory
{
    /**
     * @internal
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * @param array<string, mixed> $configurations
     */
    public function __construct(array $configurations = [])
    {
        $this->config = (new Configuration())->process($configurations);
    }

    /**
     * Creates a new RequestHandler based on passed path.
     */
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
        }

        if ($configuration['enabled'] === false) {
            return null;
        }

        return new RequestHandler(
            $configuration['allow_credentials'] ?? $this->config['allow_credentials'],
            $configuration['allow_origin'] ?? $this->config['allow_origin'],
            $configuration['allow_headers'] ?? $this->config['allow_headers'],
            $configuration['expose_headers'] ?? $this->config['expose_headers'],
            $configuration['max_age'] ?? $this->config['max_age']
        );
    }
}

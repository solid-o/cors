<?php

declare(strict_types=1);

namespace Solido\Cors;

use function Safe\preg_match;

class HandlerFactory
{
    /** @var array<string, mixed> */
    private array $config;

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
        foreach ($this->config['paths'] as $pathRegex => $config) {
            if (isset($config['host']) && ! preg_match('#' . $config['host'] . '#', $host)) {
                continue;
            }

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

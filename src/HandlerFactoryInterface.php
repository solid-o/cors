<?php

declare(strict_types=1);

namespace Solido\Cors;

interface HandlerFactoryInterface
{
    /**
     * Creates a new RequestHandler based on passed path.
     */
    public function factory(string $path, string $host): ?RequestHandlerInterface;
}

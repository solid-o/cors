<?php

declare(strict_types=1);

namespace Solido\Cors;

interface RequestHandlerInterface
{
    /**
     * Generates a response for a preflight check request (OPTIONS).
     */
    public function handleCorsRequest(object $request, string $allowedMethods = 'GET, POST, HEAD, PUT, PATCH, DELETE'): object;

    /**
     * Decorates the response adding access control headers.
     */
    public function enhanceResponse(object $request, object $response): object;
}

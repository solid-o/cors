<?php

declare(strict_types=1);

namespace Solido\Cors;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface RequestHandlerInterface
{
    /**
     * Generates a response for a preflight check request (OPTIONS).
     */
    public function handleCorsRequest(Request $request, string $allowedMethods = 'GET, POST, HEAD, PUT, PATCH, DELETE'): Response;

    /**
     * Decorates the response adding access control headers.
     */
    public function enhanceResponse(Request $request, Response $response): void;
}

<?php

declare(strict_types=1);

namespace Solido\Cors\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solido\Cors\Exception\InvalidOriginException;
use Solido\Cors\HandlerFactoryInterface;

use function assert;

class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(private HandlerFactoryInterface $handlerFactory)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $uri = $request->getUri();
        $corsHandler = $this->handlerFactory->factory($uri->getPath(), $uri->getHost());
        if ($corsHandler === null) {
            return $response;
        }

        if ($request->getMethod() === 'OPTIONS' && $response->getStatusCode() === 405) {
            try {
                $response = $corsHandler->handleCorsRequest($request, $response->getHeader('Allow')[0] ?? '');
            } catch (InvalidOriginException) {
                // @ignoreException
            }
        } else {
            $response = $corsHandler->enhanceResponse($request, $response);
        }

        assert($response instanceof ResponseInterface);

        return $response;
    }
}

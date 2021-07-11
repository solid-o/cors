<?php

declare(strict_types=1);

namespace Solido\Cors\Tests\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Solido\Common\AdapterFactory;
use Solido\Cors\HandlerFactory;
use Solido\Cors\Middleware\CorsMiddleware;

class CorsMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    private CorsMiddleware $middleware;

    protected function setUp(): void
    {
        $factory = new HandlerFactory([
            'allow_credentials' => false,
            'max_age' => 100,
            'paths' => [
                [
                    'path' => '^/api/',
                    'allow_origin' => ['*'],
                    'allow_headers' => ['X-User-Auth'],
                    'max_age' => 0,
                ],
                [
                    'path' => '^/api_on_second/',
                    'max_age' => 300,
                    'host' => 'test.example.org',
                ],
            ],
        ]);

        $factory->setAdapterFactory(new AdapterFactory(new Psr17Factory()));

        $this->middleware = new CorsMiddleware($factory);
    }

    public function testShouldAddHeadersWithDefaultConfiguration(): void
    {
        $request = (new ServerRequest('GET', '/not_an_api'))
            ->withHeader('Origin', 'http://localhost');

        $response = $this->middleware->process($request, new RequestHandler(new Response()));

        self::assertEquals(['100'], $response->getHeader('Access-Control-Max-Age'));
        self::assertEquals(['false'], $response->getHeader('Access-Control-Allow-Credentials'));
    }

    public function testShouldAddHeadersWithSpecificConfiguration(): void
    {
        $request = (new ServerRequest('GET', 'https://test.example.org/api_on_second/index'))
            ->withHeader('Origin', 'https://test.example.org');

        $response = $this->middleware->process($request, new RequestHandler(new Response()));

        self::assertEquals(['300'], $response->getHeader('Access-Control-Max-Age'));
        self::assertEquals(['false'], $response->getHeader('Access-Control-Allow-Credentials'));
    }

    public function testShouldHandleCorsPreflightRequest(): void
    {
        $request = (new ServerRequest('OPTIONS', 'http://localhost/api/index'))
            ->withHeader('Origin', 'http://localhost')
            ->withHeader('Access-Control-Request-Headers', 'Authorization, X-User-Auth');

        $response = $this->middleware->process($request, new RequestHandler(new Response(405, ['Allow' => 'GET, POST'])));

        self::assertEquals(['0'], $response->getHeader('Access-Control-Max-Age'));
        self::assertEquals(['GET, POST'], $response->getHeader('Access-Control-Allow-Methods'));
        self::assertEquals(['false'], $response->getHeader('Access-Control-Allow-Credentials'));
        self::assertEquals(['X-User-Auth'], $response->getHeader('Access-Control-Allow-Headers'));
    }
}

class RequestHandler implements RequestHandlerInterface
{
    private ResponseInterface $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}

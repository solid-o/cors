<?php declare(strict_types=1);

namespace Solido\Cors\Tests;

use Solido\Cors\HandlerFactory;
use PHPUnit\Framework\TestCase;
use Solido\Cors\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

class HandlerFactoryTest extends TestCase
{
    public function testShouldSelectTheCorrectHandlerForTheGivenPath(): void
    {
        $factory = new HandlerFactory([
            'allow_credentials' => false,
            'max_age' => 100,
            'paths' => [
                '^/api/' => [
                    'allow_origin' => ['*'],
                    'allow_headers' => ['X-User-Auth'],
                    'max_age' => 0,
                ],
                '^/api_on_second/' => [
                    'max_age' => 300,
                    'host' => 'test.example.org',
                ]
            ],
        ]);

        $request = Request::create('/not_an_api');
        $request->headers->set('Origin', 'http://localhost');

        $handler = $factory->factory($request->getPathInfo(), $request->getHost());
        self::assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handleCorsRequest($request);

        self::assertEquals(100, $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals('false', $response->headers->get('Access-Control-Allow-Credentials'));

        $request = Request::create('/api_on_second/index');
        $request->headers->set('Origin', 'http://localhost');

        $handler = $factory->factory($request->getPathInfo(), $request->getHost());
        self::assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handleCorsRequest($request);

        self::assertEquals(100, $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals('false', $response->headers->get('Access-Control-Allow-Credentials'));

        $request->headers->set('Host', 'test.example.org');
        $handler = $factory->factory($request->getPathInfo(), $request->getHost());
        self::assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handleCorsRequest($request);

        self::assertEquals(300, $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals('false', $response->headers->get('Access-Control-Allow-Credentials'));

        $request = Request::create('/api/resource_one');
        $request->headers->set('Origin', 'http://localhost');
        $request->headers->set('Access-Control-Request-Headers', 'X-User-Auth');

        $handler = $factory->factory($request->getPathInfo(), $request->getHost());
        self::assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handleCorsRequest($request);

        self::assertEquals(0, $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals('false', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertEquals('X-User-Auth', $response->headers->get('Access-Control-Allow-Headers'));

        $request = Request::create('/not_an_api');
        $request->headers->set('Origin', 'http://localhost');

        $handler = $factory->factory($request->getPathInfo(), $request->getHost());
        self::assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handleCorsRequest($request);

        self::assertEquals(100, $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals('false', $response->headers->get('Access-Control-Allow-Credentials'));

    }

    public function testCanReturnNullIfDisabled(): void
    {
        $factory = new HandlerFactory([
            'allow_credentials' => false,
            'max_age' => 100,
            'enabled' => false,
            'paths' => [
                '^/api/' => [
                    'allow_origin' => ['*'],
                    'allow_headers' => ['X-User-Auth'],
                    'max_age' => 0,
                ],
            ],
        ]);

        $request = Request::create('/not_an_api');
        $request->headers->set('Origin', 'http://localhost');
        $handler = $factory->factory($request->getPathInfo(), $request->getHost());
        self::assertNull($handler);

        $request = Request::create('/api/resource_one');
        $request->headers->set('Origin', 'http://localhost');
        $request->headers->set('Access-Control-Request-Headers', 'X-User-Auth');

        $handler = $factory->factory($request->getPathInfo(), $request->getHost());
        self::assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handleCorsRequest($request);

        self::assertEquals(0, $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals('false', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertEquals('X-User-Auth', $response->headers->get('Access-Control-Allow-Headers'));
    }
}

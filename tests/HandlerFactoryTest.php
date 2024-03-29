<?php

declare(strict_types=1);

namespace Solido\Cors\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Solido\Common\AdapterFactory;
use Solido\Cors\HandlerFactory;
use Solido\Cors\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

class HandlerFactoryTest extends TestCase
{
    public function testShouldSelectTheCorrectHandlerForTheGivenPath(): void
    {
        $factory = new HandlerFactory([
            'allow_credentials' => false,
            'max_age' => 100,
            'allow_origin' => ['http://localhost'],
            'paths' => [
                [
                    'host' => 'api\.host\.loc.+',
                    'path' => '^/api/',
                    'allow_credentials' => true,
                    'allow_origin' => ['http://xyz.example.org'],
                    'expose_headers' => ['X-Header'],
                    'max_age' => 1800,
                ],
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

        $request = Request::create('/not_an_api');
        $request->headers->set('Origin', 'http://localhost');

        $factory->setAdapterFactory(new AdapterFactory(new Psr17Factory()));
        $handler = $factory->factory($request->getPathInfo(), $request->getHost());
        self::assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handleCorsRequest($request);

        self::assertSame('100', $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals('false', $response->headers->get('Access-Control-Allow-Credentials'));

        $request = Request::create('/api_on_second/index');
        $request->headers->set('Origin', 'http://localhost');

        $handler = $factory->factory($request->getPathInfo(), $request->getHost());
        self::assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handleCorsRequest($request);

        self::assertSame('100', $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals('false', $response->headers->get('Access-Control-Allow-Credentials'));

        $request->headers->set('Host', 'test.example.org');
        $handler = $factory->factory($request->getPathInfo(), $request->getHost());
        self::assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handleCorsRequest($request);

        self::assertSame('300', $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals('false', $response->headers->get('Access-Control-Allow-Credentials'));

        $request = Request::create('/api/resource_one');
        $request->headers->set('Origin', 'http://localhost');
        $request->headers->set('Access-Control-Request-Headers', 'X-User-Auth');

        $handler = $factory->factory($request->getPathInfo(), $request->getHost());
        self::assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handleCorsRequest($request);

        self::assertSame('0', $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals('false', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertEquals('X-User-Auth', $response->headers->get('Access-Control-Allow-Headers'));

        $request = Request::create('http://api.host.local/api/on_other_host');
        $request->headers->set('Origin', 'http://xyz.example.org');
        $request->headers->set('Access-Control-Request-Headers', 'X-User-Auth');

        $handler = $factory->factory($request->getPathInfo(), $request->getHost());
        self::assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handleCorsRequest($request);

        self::assertSame('1800', $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertNull($response->headers->get('Access-Control-Allow-Headers'));
        self::assertEquals('X-Header', $response->headers->get('Access-Control-Expose-Headers'));

        $request = Request::create('/not_an_api');
        $request->headers->set('Origin', 'http://localhost');

        $handler = $factory->factory($request->getPathInfo(), $request->getHost());
        self::assertInstanceOf(RequestHandlerInterface::class, $handler);
        $response = $handler->handleCorsRequest($request);

        self::assertSame('100', $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals('false', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function testCanReturnNullIfDisabled(): void
    {
        $factory = new HandlerFactory([
            'allow_credentials' => false,
            'max_age' => 100,
            'enabled' => false,
            'paths' => [
                [
                    'path' => '^/api/',
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

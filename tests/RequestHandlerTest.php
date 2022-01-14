<?php

declare(strict_types=1);

namespace Solido\Cors\Tests;

use Nyholm\Psr7;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Solido\Common\AdapterFactory;
use Solido\Cors\Exception\InvalidOriginException;
use Solido\Cors\RequestHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestHandlerTest extends TestCase
{
    private RequestHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new RequestHandler(true, ['http://localhost', 'http://example.com']);
        $this->handler->setAdapterFactory(new AdapterFactory(new Psr17Factory()));
    }

    public function testDefaultHandler(): void
    {
        $handler = new RequestHandler();

        $request = Request::create('/api/resource_one', 'OPTIONS');
        $request->headers->set('Origin', 'http://localhost');
        $request->headers->set('Access-Control-Request-Headers', 'X-User-Auth');

        $response = $handler->handleCorsRequest($request);

        self::assertSame('0', $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertEquals('', $response->headers->get('Access-Control-Allow-Headers'));
        self::assertEquals('', $response->headers->get('Access-Control-Expose-Headers'));
    }

    public function testShouldThrowIfRequestIsOriginIsNotSet(): void
    {
        $this->expectException(InvalidOriginException::class);
        $request = Request::create('/');
        $this->handler->handleCorsRequest($request);
    }

    public function testShouldThrowIfRequestHasABadOrigin(): void
    {
        $this->expectException(InvalidOriginException::class);

        $request = Request::create('/');
        $request->headers->set('Origin', 'http://example.org');

        $this->handler->handleCorsRequest($request);
    }

    public function testShouldSetTheCorrectOrigin(): void
    {
        $request = Request::create('/');

        $request->headers->set('Origin', 'http://example.com');
        $response = $this->handler->handleCorsRequest($request);
        self::assertEquals('http://example.com', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertEquals('Origin', $response->headers->get('Vary'));

        $request->headers->set('Origin', 'http://localhost');
        $response = $this->handler->handleCorsRequest($request);
        self::assertEquals('http://localhost', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertEquals('Origin', $response->headers->get('Vary'));
    }

    public function testShouldNotAddHeadersIfOriginIsNotPresent(): void
    {
        $request = Request::create('/');
        $response = new Response();

        $this->handler->enhanceResponse($request, $response);
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        $request = new Psr7\ServerRequest('GET', '/');
        $response = $this->handler->enhanceResponse($request, new Psr7\Response());
        self::assertEmpty($response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testShouldAddStarOriginIfNoOriginIsSpecified(): void
    {
        $request = Request::create('/');
        $request->headers->set('Origin', 'http://example.org');
        $response = new Response();

        $this->handler = new RequestHandler(true);
        $this->handler->enhanceResponse($request, $response);
        self::assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));

        $request = new Psr7\ServerRequest('GET', '/');
        $request = $request->withHeader('Origin', 'http://example.org');

        $this->handler = new RequestHandler(true);
        $response = $this->handler->enhanceResponse($request, new Psr7\Response());
        self::assertEquals('*', $response->getHeader('Access-Control-Allow-Origin')[0]);
    }

    public function testShouldNotAddHeadersIfOriginIsNotValid(): void
    {
        $request = Request::create('/');
        $request->headers->set('Origin', 'http://example.org');

        $response = new Response();
        $this->handler->enhanceResponse($request, $response);
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));

        $request = new Psr7\ServerRequest('GET', '/');
        $request = $request->withHeader('Origin', 'http://example.org');

        $response = $this->handler->enhanceResponse($request, new Psr7\Response());
        self::assertEmpty($response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testShouldAddAllowedHeaders(): void
    {
        $request = Request::create('/');
        $request->headers->set('Origin', 'http://example.org');
        $request->headers->set('Access-Control-Request-Headers', 'X-Custom-Header, Range, X-Unknown');
        $response = new Response();

        $this->handler = new RequestHandler(true, ['*'], ['X-Custom-Header', 'Range', 'Accept']);
        $this->handler->enhanceResponse($request, $response);

        self::assertEquals('X-Custom-Header,Range', $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function testShouldMatchGlobOrigin(): void
    {
        $request = Request::create('/');
        $request->headers->set('Origin', 'http://example.org');
        $response = new Response();

        $this->handler = new RequestHandler(true, ['http://localhost', 'http://example.*']);
        $this->handler->enhanceResponse($request, $response);
        self::assertEquals('http://example.org', $response->headers->get('Access-Control-Allow-Origin'));

        $this->handler = new RequestHandler(true, ['http://example.?rg', 'xx\*?']);
        $this->handler->enhanceResponse($request, $response);
        self::assertEquals('http://example.org', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testShouldSetTheCorrectAllowControlOriginOnResponse(): void
    {
        $request = Request::create('/');
        $response = new Response();

        $request->headers->set('Origin', 'http://example.com');
        $this->handler->enhanceResponse($request, $response);
        self::assertEquals('http://example.com', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertEquals('Origin', $response->headers->get('Vary'));

        $request->headers->set('Origin', 'http://localhost');
        $response = new Response();

        $this->handler->enhanceResponse($request, $response);
        self::assertEquals('http://localhost', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertEquals('Origin', $response->headers->get('Vary'));
    }

    public function testShouldAddVaryHeader(): void
    {
        $request = Request::create('/');
        $request->headers->set('Origin', 'http://localhost');

        $response = new Response();
        $this->handler->enhanceResponse($request, $response);
        self::assertEquals('http://localhost', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertEquals('Origin', $response->headers->get('Vary'));

        $response = new Response();
        $response->headers->set('Vary', 'Accept');
        $this->handler->enhanceResponse($request, $response);
        self::assertEquals('http://localhost', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertEquals(['Accept', 'Origin'], $response->headers->all('Vary'));
    }

    public function testShouldNotAddVaryHeaderIfOriginIsStar(): void
    {
        $this->handler = new RequestHandler(true, ['*']);
        $request = Request::create('/');
        $request->headers->set('Origin', 'http://localhost');

        $response = new Response();
        $this->handler->enhanceResponse($request, $response);
        self::assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertNull($response->headers->get('Vary'));
    }

    public function testShouldNotAddVaryHeaderIfAlreadyDefined(): void
    {
        $request = Request::create('/');
        $request->headers->set('Origin', 'http://localhost');

        $response = new Response();
        $response->headers->set('Vary', ['Accept', 'ORIGIN', 'Content-Type']);

        $this->handler->enhanceResponse($request, $response);
        self::assertEquals('http://localhost', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertEquals(['Accept', 'ORIGIN', 'Content-Type'], $response->headers->all('Vary'));
    }

    public function testShouldCorrectlyMatchOrigin(): void
    {
        $shouldFail = function (string $origin) {
            $request = Request::create('/');
            $request->setMethod(Request::METHOD_OPTIONS);
            $request->headers->set('Origin', $origin);

            try {
                $this->handler->handleCorsRequest($request);
                self::fail('Should throw invalid origin on "' . $origin . '"');
            } catch (InvalidOriginException $e) {
                self::assertEquals('Given origin is not valid.', $e->getMessage());
            }
        };

        $this->handler = new RequestHandler(true, ['http://localhost']);
        $request = Request::create('/');
        $request->setMethod(Request::METHOD_OPTIONS);
        $request->headers->set('Origin', 'http://localhost');

        $response = $this->handler->handleCorsRequest($request);
        self::assertEquals('http://localhost', $response->headers->get('Access-Control-Allow-Origin'));
        array_map($shouldFail, ['*', 'https://localhost', 'http://www.example.org']);

        $this->handler = new RequestHandler(true, ['http://localhost', 'http://www.example.org']);

        $request->headers->set('Origin', 'http://localhost');
        $response = $this->handler->handleCorsRequest($request);
        self::assertEquals('http://localhost', $response->headers->get('Access-Control-Allow-Origin'));

        $request->headers->set('Origin', 'http://www.example.org');
        $response = $this->handler->handleCorsRequest($request);
        self::assertEquals('http://www.example.org', $response->headers->get('Access-Control-Allow-Origin'));
        array_map($shouldFail, ['*', 'https://www.example.org', 'http://loc_a.example.org']);

        $this->handler = new RequestHandler(true, ['http://*.example.org']);

        $request->headers->set('Origin', 'http://www.example.org');
        $response = $this->handler->handleCorsRequest($request);
        self::assertEquals('http://www.example.org', $response->headers->get('Access-Control-Allow-Origin'));

        $request->headers->set('Origin', 'http://it.example.org');
        $response = $this->handler->handleCorsRequest($request);
        self::assertEquals('http://it.example.org', $response->headers->get('Access-Control-Allow-Origin'));
        array_map($shouldFail, ['*', 'https://www.example.org']);

        $this->handler = new RequestHandler(true, ['http://#.example.org']);

        $request->headers->set('Origin', 'http://#.example.org');
        $response = $this->handler->handleCorsRequest($request);
        self::assertEquals('http://#.example.org', $response->headers->get('Access-Control-Allow-Origin'));
        array_map($shouldFail, ['*', 'http://www.example.org', 'http://localhost']);

        $this->handler = new RequestHandler(true, ['http://\?.ex?mple.*']);

        $request->headers->set('Origin', 'http://?.example.org');
        $response = $this->handler->handleCorsRequest($request);
        self::assertEquals('http://?.example.org', $response->headers->get('Access-Control-Allow-Origin'));

        $request->headers->set('Origin', 'http://?.exemple.org');
        $response = $this->handler->handleCorsRequest($request);
        self::assertEquals('http://?.exemple.org', $response->headers->get('Access-Control-Allow-Origin'));

        $request->headers->set('Origin', 'http://?.example.com');
        $response = $this->handler->handleCorsRequest($request);
        self::assertEquals('http://?.example.com', $response->headers->get('Access-Control-Allow-Origin'));
        array_map($shouldFail, ['*', 'http://a.example.org', 'http://b.example.org']);

        $this->handler = new RequestHandler(true, ['http://loc_?.example.org']);

        $request->headers->set('Origin', 'http://loc_a.example.org');
        $response = $this->handler->handleCorsRequest($request);
        self::assertEquals('http://loc_a.example.org', $response->headers->get('Access-Control-Allow-Origin'));

        $request->headers->set('Origin', 'http://loc_b.example.org');
        $response = $this->handler->handleCorsRequest($request);
        self::assertEquals('http://loc_b.example.org', $response->headers->get('Access-Control-Allow-Origin'));
        array_map($shouldFail, ['*', 'http://locale.example.org']);
    }
}

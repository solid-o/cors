<?php declare(strict_types=1);

namespace Solido\Cors\Tests;

use Solido\Cors\Exception\InvalidOriginException;
use Solido\Cors\RequestHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestHandlerTest extends TestCase
{
    private RequestHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new RequestHandler(true, ['http://localhost', 'http://example.com']);
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
    }

    public function testShouldNotAddHeadersIfOriginIsNotValid(): void
    {
        $request = Request::create('/');
        $request->headers->set('Origin', 'http://example.org');

        $response = new Response();
        $this->handler->enhanceResponse($request, $response);
        self::assertFalse($response->headers->has('Access-Control-Allow-Origin'));
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
}

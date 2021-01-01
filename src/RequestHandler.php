<?php

declare(strict_types=1);

namespace Solido\Cors;

use Solido\Cors\Exception\InvalidOriginException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_filter;
use function array_map;
use function explode;
use function implode;
use function in_array;
use function mb_strtolower;
use function preg_quote;
use function Safe\array_flip;
use function Safe\preg_match;
use function strlen;

class RequestHandler implements RequestHandlerInterface
{
    private bool $allowCredentials;
    private string $allowedOrigins;
    private string $exposedHeaders;
    private int $maxAge;

    /** @var array<string, mixed> */
    private array $allowHeaders;

    /**
     * @param string[] $allowOrigins
     * @param string[] $allowHeaders
     * @param string[] $exposeHeaders
     */
    public function __construct(bool $allowCredentials = true, array $allowOrigins = ['*'], array $allowHeaders = [], array $exposeHeaders = [], int $maxAge = 0)
    {
        if (! empty($allowOrigins)) {
            $allowedOrigins = (static fn (string ...$origins) => $origins)(...$allowOrigins);
            $allowedOrigins = '#^(?:' . implode('|', array_map(fn (string $origin) => $this->toRegex($origin), $allowedOrigins)) . ')$#';
        }

        $this->allowedOrigins = $allowedOrigins ?? '#^(?:.*)$#';
        $this->allowCredentials = $allowCredentials;
        $this->allowHeaders = array_flip(array_map('mb_strtolower', $allowHeaders));
        $this->exposedHeaders = implode(', ', $exposeHeaders);
        $this->maxAge = $maxAge;
    }

    public function handleCorsRequest(Request $request, string $allowedMethods = 'GET, POST, HEAD, PUT, PATCH, DELETE'): Response
    {
        $origin = $request->headers->get('Origin');
        if ($origin === null || $origin === '*') {
            throw new InvalidOriginException('Given origin is not valid.');
        }

        if ($this->allowedOrigins === '#^(?:.*)$#') {
            $origin = '*';
        } elseif (! $this->isValidOrigin($origin)) {
            throw new InvalidOriginException('Given origin is not valid.');
        }

        $response = Response::create();
        $response->headers->set('Access-Control-Allow-Credentials', $this->allowCredentials ? 'true' : 'false');
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', $allowedMethods);
        $response->headers->set('Access-Control-Max-Age', (string) $this->maxAge);
        $response->headers->set('Allow', $allowedMethods);

        $headers = $request->headers->get('Access-Control-Request-Headers');
        if (! empty($headers)) {
            $response->headers->set('Access-Control-Allow-Headers', $this->filterHeaders(array_map('trim', explode(',', $headers))));
        }

        $response->headers->set('Access-Control-Expose-Headers', $this->exposedHeaders);

        $vary = $response->getVary();
        if ($origin !== '*' && ! in_array('Origin', $vary, true)) {
            $vary[] = 'Origin';
            $response->setVary($vary);
        }

        return $response;
    }

    public function enhanceResponse(Request $request, Response $response): void
    {
        $origin = $request->headers->get('Origin');
        if ($origin === null || $origin === '*') {
            return;
        }

        if ($this->allowedOrigins === '#^(?:.*)$#') {
            $origin = '*';
        } elseif (! $this->isValidOrigin($origin)) {
            return;
        }

        $response->headers->set('Access-Control-Allow-Credentials', $this->allowCredentials ? 'true' : 'false');
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Max-Age', (string) $this->maxAge);
        $response->headers->set('Access-Control-Expose-Headers', $this->exposedHeaders);

        $headers = $request->headers->get('Access-Control-Request-Headers');
        if (! empty($headers)) {
            $headers = $this->filterHeaders(array_map('trim', explode(',', $headers)));
            $response->headers->set('Access-Control-Allow-Headers', implode(',', $headers));
        }

        $response->headers->set('Access-Control-Expose-Headers', $this->exposedHeaders);

        $vary = $response->getVary();
        if ($origin === '*' || in_array('Origin', $vary, true)) {
            return;
        }

        $vary[] = 'Origin';
        $response->setVary($vary);
    }

    /**
     * Filter request headers with allowed headers.
     *
     * @param string[] $headers
     *
     * @return string[]
     */
    private function filterHeaders(array $headers): array
    {
        return array_filter($headers, fn (string $header) => isset($this->allowHeaders[mb_strtolower($header)]));
    }

    /**
     * Converts a domain glob to regex pattern.
     */
    private function toRegex(string $domain): string
    {
        $regex = '';
        $escaping = false;
        $size = strlen($domain);
        for ($i = 0; $i < $size; ++$i) {
            $char = $domain[$i];

            if ($escaping) {
                $escaping = false;
                $char = preg_quote($char, '#');
            } elseif ($char === '*') {
                $char = '.*';
            } elseif ($char === '?') {
                $char = '.';
            } elseif ($char === '\\') {
                $escaping = true;
            } else {
                $char = preg_quote($char, '#');
            }

            $regex .= $char;
        }

        return $regex;
    }

    /**
     * Checks whether $origin is allowed for CORS.
     */
    private function isValidOrigin(string $origin): bool
    {
        return preg_match($this->allowedOrigins, $origin) !== 0;
    }
}

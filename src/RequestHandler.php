<?php

declare(strict_types=1);

namespace Solido\Cors;

use Solido\Common\AdapterFactory;
use Solido\Common\AdapterFactoryInterface;
use Solido\Cors\Exception\InvalidOriginException;

use function array_filter;
use function array_map;
use function explode;
use function implode;
use function in_array;
use function preg_quote;
use function Safe\array_flip;
use function Safe\preg_match;
use function strlen;
use function strtolower;

class RequestHandler implements RequestHandlerInterface
{
    private AdapterFactoryInterface $adapterFactory;
    private string $allowedOrigins;
    private string $exposedHeaders;
    /** @var array<string, mixed> */
    private array $allowHeaders;

    /**
     * @param string[] $allowOrigins
     * @param string[] $allowHeaders
     * @param string[] $exposeHeaders
     */
    public function __construct(private bool $allowCredentials = true, array $allowOrigins = [], array $allowHeaders = [], array $exposeHeaders = [], private int $maxAge = 0)
    {
        $this->adapterFactory = new AdapterFactory();

        if (! empty($allowOrigins)) {
            $allowedOrigins = (static fn (string ...$origins) => $origins)(...$allowOrigins);
            $this->allowedOrigins = '#^(?:' . implode('|', array_map(fn (string $origin) => $this->toRegex($origin), $allowedOrigins)) . ')$#';
        } else {
            $this->allowedOrigins = '#^(?:.*)$#';
        }

        $this->allowHeaders = array_flip(array_map('mb_strtolower', $allowHeaders));
        $this->exposedHeaders = implode(', ', $exposeHeaders);
    }

    public function setAdapterFactory(AdapterFactoryInterface $adapterFactory): void
    {
        $this->adapterFactory = $adapterFactory;
    }

    public function handleCorsRequest(object $request, string $allowedMethods = 'GET, POST, HEAD, PUT, PATCH, DELETE'): object
    {
        $adapter = $this->adapterFactory->createRequestAdapter($request);
        $origin = $adapter->getHeader('Origin')[0] ?? null;
        if ($origin === null || $origin === '*') {
            throw new InvalidOriginException('Given origin is not valid.');
        }

        if ($this->allowedOrigins === '#^(?:.*)$#') {
            $origin = '*';
        } elseif (! $this->isValidOrigin($origin)) {
            throw new InvalidOriginException('Given origin is not valid.');
        }

        $response = $adapter->createResponse();
        $response->setHeaders([
            'Access-Control-Allow-Credentials' => $this->allowCredentials ? 'true' : 'false',
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => $allowedMethods,
            'Access-Control-Max-Age' => (string) $this->maxAge,
            'Allow' => $allowedMethods,
        ]);

        $headers = $adapter->getHeader('Access-Control-Request-Headers')[0] ?? null;
        if (! empty($headers)) {
            $response->setHeaders(['Access-Control-Allow-Headers' => $this->filterHeaders(array_map('trim', explode(',', $headers)))]);
        }

        $response->setHeaders(['Access-Control-Expose-Headers' => $this->exposedHeaders]);

        if ($origin !== '*') {
            $response->setHeaders(['Vary' => 'Origin']);
        }

        return $response->unwrap();
    }

    public function enhanceResponse(object $request, object $response): object
    {
        $request = $this->adapterFactory->createRequestAdapter($request);
        $origin = $request->getHeader('Origin')[0] ?? null;
        if ($origin === null || $origin === '*') {
            return $response;
        }

        if ($this->allowedOrigins === '#^(?:.*)$#') {
            $origin = '*';
        } elseif (! $this->isValidOrigin($origin)) {
            return $response;
        }

        $response = $this->adapterFactory->createResponseAdapter($response);
        $response->setHeaders([
            'Access-Control-Allow-Credentials' => $this->allowCredentials ? 'true' : 'false',
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Max-Age' => (string) $this->maxAge,
            'Access-Control-Expose-Headers' => $this->exposedHeaders,
        ]);

        $headers = $request->getHeader('Access-Control-Request-Headers')[0] ?? null;
        if (! empty($headers)) {
            $headers = $this->filterHeaders(array_map('trim', explode(',', $headers)));
            $response->setHeaders(['Access-Control-Allow-Headers' => implode(',', $headers)]);
        }

        $vary = $response->getHeader('Vary');
        if ($origin !== '*' && ! in_array('origin', array_map('strtolower', $vary), true)) {
            $vary[] = 'Origin';
            $response->setHeaders(['Vary' => $vary]);
        }

        return $response->unwrap();
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
        return array_filter($headers, fn (string $header) => isset($this->allowHeaders[strtolower($header)]));
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
                continue;
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

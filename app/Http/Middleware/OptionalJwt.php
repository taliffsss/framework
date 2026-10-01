<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Naluz\Http\Request;
use Naluz\Security\InvalidTokenException;
use Naluz\Security\Jwt;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Identifies the caller when a valid bearer token is sent, but lets anonymous requests through.
 * GraphQL uses it so one endpoint can serve public queries and authenticated mutations; the resolvers decide.
 */
final class OptionalJwt implements MiddlewareInterface
{
    public function __construct(private readonly Jwt $jwt)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = Request::bearerToken($request);
        if ($token !== null) {
            try {
                $claims = $this->jwt->decode($token);
                $request = $request->withAttribute('auth.claims', $claims)->withAttribute('auth.id', $claims['sub'] ?? null);
            } catch (InvalidTokenException) {
                // a bad token is treated as anonymous; protected resolvers will answer UNAUTHENTICATED
            }
        }
        return $handler->handle($request);
    }
}

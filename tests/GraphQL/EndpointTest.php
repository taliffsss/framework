<?php

declare(strict_types=1);

namespace Naluz\Tests\GraphQL;

use App\Models\Post;
use App\Models\User;
use Naluz\Config\Repository;
use Naluz\Security\Jwt;
use Naluz\Tests\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;

final class EndpointTest extends TestCase
{
    private int $n = 0;

    private function user(string $name = 'Ann'): User
    {
        return User::create(['name' => $name, 'email' => 'u' . ++$this->n . '@example.test', 'password' => 'secret-password']);
    }

    private function post(User $u, string $title, bool $published = true): Post
    {
        return Post::create(['user_id' => $u->getKey(), 'title' => $title, 'body' => 'Body of ' . $title, 'published' => $published]);
    }

    private function token(User $u): array
    {
        return ['Authorization' => 'Bearer ' . $this->app->make(Jwt::class)->encode(['sub' => $u->getKey()])];
    }

    /** @return array{0:int,1:array<string,mixed>,2:ResponseInterface} */
    private function gql(string $query, array $variables = [], array $headers = [], ?string $operation = null): array
    {
        $payload = ['query' => $query] + ($variables ? ['variables' => $variables] : []) + ($operation ? ['operationName' => $operation] : []);
        $response = $this->json('POST', '/api/graphql', $payload, $headers);
        return [$response->getStatusCode(), $this->decode($response), $response];
    }

    public function testQueryReturnsPublishedPostsWithAuthorsInAFixedNumberOfQueries(): void
    {
        $u1 = $this->user('Ann');
        $u2 = $this->user('Bob');
        foreach (range(1, 5) as $i) {
            $this->post($i % 2 ? $u1 : $u2, "Post {$i}");
        }
        $this->post($u1, 'Draft', false);

        $queries = [];
        $result = null;
        $queries = $this->queries(function () use (&$result) {
            $result = $this->gql('{ posts(first: 10) { id title published author { name } } }');
        });
        [$status, $body] = $result;

        $this->assertSame(200, $status);
        $this->assertArrayNotHasKey('errors', $body);
        $titles = array_column($body['data']['posts'], 'title');
        $this->assertCount(5, $titles);
        $this->assertNotContains('Draft', $titles);
        $this->assertSame('Ann', array_column($body['data']['posts'], 'author', 'title')['Post 1']['name']);
        $selects = array_filter($queries, fn ($q) => stripos($q, 'select') === 0 && !str_contains($q, 'sqlite_master'));
        $this->assertLessThanOrEqual(3, count($selects), 'authors are eager loaded (no N+1): ' . implode(' | ', $selects));
    }

    public function testNeverExposesFieldsThatAreNotInTheSchema(): void
    {
        $u = $this->user();
        $this->post($u, 'Hello');
        [$status, $body] = $this->gql('{ posts { author { email password } } }');
        $this->assertSame(400, $status);
        $this->assertStringContainsString('Cannot query field "email" on type "User"', $body['errors'][0]['message']);
        $this->assertStringNotContainsString('secret-password', json_encode($body));
    }

    public function testPaginationArgumentsAreClamped(): void
    {
        $u = $this->user();
        foreach (range(1, 60) as $i) {
            $this->post($u, "P{$i}");
        }
        [, $body] = $this->gql('{ big: posts(first: 1000) { id } zero: posts(first: 0) { id } neg: posts(first: -5) { id } page2: posts(first: 5, page: 2) { id } }');
        $this->assertCount(50, $body['data']['big'], 'a client cannot ask for everything');
        $this->assertCount(1, $body['data']['zero']);
        $this->assertCount(1, $body['data']['neg']);
        $this->assertCount(5, $body['data']['page2']);
    }

    public function testSinglePostAndMissingPost(): void
    {
        $post = $this->post($this->user(), 'Findable');
        $draft = $this->post($this->user(), 'Secret draft', false);
        [, $body] = $this->gql('query($id: ID!) { post(id: $id) { title author { name } } }', ['id' => (string) $post->getKey()]);
        $this->assertSame('Findable', $body['data']['post']['title']);
        [, $body] = $this->gql('query($id: ID!) { post(id: $id) { title } }', ['id' => (string) $draft->getKey()]);
        $this->assertNull($body['data']['post'], 'drafts are not readable through the API');
        [, $body] = $this->gql('{ post(id: "99999") { title } }');
        $this->assertNull($body['data']['post']);
    }

    public function testMutationsNeedAValidToken(): void
    {
        $q = 'mutation { createPost(input: {title: "T", body: "B"}) { id } }';
        [$status, $body] = $this->gql($q);
        $this->assertSame(200, $status);
        $this->assertNull($body['data']);
        $this->assertSame('UNAUTHENTICATED', $body['errors'][0]['extensions']['code']);

        [, $body] = $this->gql($q, headers: ['Authorization' => 'Bearer not.a.token']);
        $this->assertSame('UNAUTHENTICATED', $body['errors'][0]['extensions']['code'], 'a bad token is treated as anonymous');
        $this->assertSame(0, Post::query()->count());
    }

    public function testCreatePostUsesTheCallerNotAClientSuppliedAuthor(): void
    {
        $me = $this->user('Me');
        $other = $this->user('Other');
        [$status, $body] = $this->gql('mutation($in: PostInput!) { createPost(input: $in) { id title published author { name } } }', ['in' => ['title' => '  Hi  ', 'body' => 'Body']], $this->token($me));
        $this->assertSame(200, $status);
        $this->assertSame('Me', $body['data']['createPost']['author']['name']);
        $this->assertSame('Hi', $body['data']['createPost']['title'], 'the PostObserver trimmed the title');
        $this->assertFalse($body['data']['createPost']['published'], 'defaults to unpublished');

        [$status, $body] = $this->gql('mutation($in: PostInput!) { createPost(input: $in) { id } }', ['in' => ['title' => 'T', 'body' => 'B', 'user_id' => $other->getKey()]], $this->token($me));
        $this->assertSame(400, $status);
        $this->assertStringContainsString('"user_id" is not defined by type "PostInput"', $body['errors'][0]['message']);
        $this->assertSame(1, Post::query()->count());
    }

    public function testValidationFailuresAreReportedPerField(): void
    {
        $me = $this->user();
        [$status, $body] = $this->gql('mutation { createPost(input: {title: "", body: "B"}) { id } }', headers: $this->token($me));
        $this->assertSame(200, $status);
        $this->assertSame('VALIDATION_FAILED', $body['errors'][0]['extensions']['code']);
        $this->assertArrayHasKey('title', $body['errors'][0]['extensions']['validation']);
        $this->assertSame(0, Post::query()->count());

        [, $body] = $this->gql('mutation($t: String!) { createPost(input: {title: $t, body: "B"}) { id } }', ['t' => str_repeat('x', 201)], $this->token($me));
        $this->assertArrayHasKey('title', $body['errors'][0]['extensions']['validation']);
    }

    public function testOnlyTheOwnerCanDelete(): void
    {
        $owner = $this->user('Owner');
        $intruder = $this->user('Intruder');
        $post = $this->post($owner, 'Mine');
        $q = 'mutation($id: ID!) { deletePost(id: $id) }';

        [, $body] = $this->gql($q, ['id' => (string) $post->getKey()], $this->token($intruder));
        $this->assertSame('FORBIDDEN', $body['errors'][0]['extensions']['code']);
        $this->assertNotNull(Post::find($post->getKey()));

        [, $body] = $this->gql($q, ['id' => (string) $post->getKey()], $this->token($owner));
        $this->assertTrue($body['data']['deletePost']);
        $this->assertNull(Post::find($post->getKey()));

        [, $body] = $this->gql($q, ['id' => '424242'], $this->token($owner));
        $this->assertSame('NOT_FOUND', $body['errors'][0]['extensions']['code']);
        $this->assertStringNotContainsString('App\\Models', json_encode($body), 'class names do not leak');
    }

    public function testGetIsForQueriesOnly(): void
    {
        $this->post($this->user(), 'Via GET');
        $r = $this->get('/api/graphql?query=' . rawurlencode('query($n: Int) { posts(first: $n) { title } }') . '&variables=' . rawurlencode('{"n":1}'));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertSame('Via GET', $this->decode($r)['data']['posts'][0]['title']);

        $r = $this->get('/api/graphql?query=' . rawurlencode('mutation { deletePost(id: "1") }'));
        $this->assertSame(405, $r->getStatusCode());
        $this->assertSame('POST', $r->getHeaderLine('Allow'));

        $this->assertSame(400, $this->get('/api/graphql?variables=%7B%7D')->getStatusCode(), 'missing query');
        $this->assertSame(400, $this->get('/api/graphql?query=%7Ba%7D&variables=%7Bnope')->getStatusCode(), 'bad variables JSON');
    }

    public function testMalformedRequestsAreRejectedWithoutExecuting(): void
    {
        $factory = new Psr17Factory();
        $raw = fn (string $body, string $type = 'application/json') => $this->send((new ServerRequest('POST', '/api/graphql', ['Content-Type' => $type, 'Accept' => 'application/json']))->withBody($factory->createStream($body)));

        $this->assertSame(400, $raw('{nope')->getStatusCode(), 'invalid JSON');
        $this->assertSame(400, $raw('[{"query":"{ posts { id } }"},{"query":"{ posts { id } }"}]')->getStatusCode(), 'batching is refused');
        $this->assertSame(400, $raw('"just a string"')->getStatusCode());
        $this->assertSame(400, $raw('{"query":123}')->getStatusCode());
        $this->assertSame(400, $raw('{"query":"{ posts { id } }","variables":[1,2]}')->getStatusCode(), 'variables must be an object');
        $this->assertSame(400, $raw('{"query":"{ posts { id } }","operationName":5}')->getStatusCode());
        $this->assertSame(400, $raw('query=%7B+posts+%7D', 'application/x-www-form-urlencoded')->getStatusCode());
        $this->assertSame(400, $raw(str_repeat('a', 200000))->getStatusCode(), 'oversized body');

        $r = $raw('{ posts { id } }', 'application/graphql');
        $this->assertSame(200, $r->getStatusCode(), 'application/graphql carries the query as the body');
    }

    public function testErrorResponsesAreJsonAndUncacheable(): void
    {
        [$status, $body, $response] = $this->gql('{ nope }');
        $this->assertSame(400, $status);
        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame([['line' => 1, 'column' => 3]], $body['errors'][0]['locations']);
    }

    public function testIntrospectionIsOffInProductionAndOptInOtherwise(): void
    {
        [$status, $body] = $this->gql('{ __schema { types { name } } }');
        $this->assertSame(400, $status);
        $this->assertStringContainsString('introspection is disabled', $body['errors'][0]['message']);
    }

    public function testIntrospectionWorksWhenEnabled(): void
    {
        $this->app->make(Repository::class)->set('graphql.introspection', true);
        [$status, $body] = $this->gql('{ __schema { queryType { name } mutationType { name } types { name } } }');
        $this->assertSame(200, $status);
        $this->assertSame('Query', $body['data']['__schema']['queryType']['name']);
        $names = array_column($body['data']['__schema']['types'], 'name');
        foreach (['Post', 'User', 'PostInput', 'Mutation'] as $n) {
            $this->assertContains($n, $names);
        }
    }

    public function testDepthLimitIsConfigurable(): void
    {
        $this->app->make(Repository::class)->set('graphql.max_depth', 3);
        $q = '{ posts { author { posts { author { name } } } } }';
        [$status, $body] = $this->gql($q);
        $this->assertSame(400, $status);
        $this->assertStringContainsString('maximum depth is 3', $body['errors'][0]['message']);
    }

    public function testInternalFailuresAreMaskedAndLogged(): void
    {
        $this->app->make(Repository::class)->set('graphql.schema', function () {
            $q = new \Naluz\GraphQL\Type\ObjectType('Query', ['boom' => ['type' => \Naluz\GraphQL\Types::string(), 'resolve' => fn () => throw new \PDOException('SQLSTATE[HY000] password=hunter2 host=10.0.0.5')]]);
            return new \Naluz\GraphQL\Schema($q);
        });
        $logged = [];
        $this->app->make(\Naluz\Log\LogManager::class)->extend('capture', new class ($logged) extends \Psr\Log\AbstractLogger {
            public function __construct(private array &$sink)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->sink[] = (string) $message;
            }
        });
        $this->app->make(Repository::class)->set('logging.default', 'capture');
        [$status, $body] = $this->gql('{ boom }');
        $this->assertSame(200, $status);
        $this->assertSame('Internal server error.', $body['errors'][0]['message']);
        $this->assertStringNotContainsString('hunter2', json_encode($body));
        $this->assertNotEmpty($logged, 'the real error is in the log');
        $this->assertStringContainsString('PDOException', implode(' ', $logged));
    }
}

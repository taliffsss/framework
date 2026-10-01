<?php

declare(strict_types=1);

namespace Naluz\Tests\GraphQL;

use Naluz\GraphQL\GraphQL;
use Naluz\GraphQL\GraphQLError;
use Naluz\GraphQL\ResolveInfo;
use Naluz\GraphQL\Schema;
use Naluz\GraphQL\Type\EnumType;
use Naluz\GraphQL\Type\InputObjectType;
use Naluz\GraphQL\Type\ObjectType;
use Naluz\GraphQL\Types;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EngineTest extends TestCase
{
    /** @var list<string> */
    private array $log = [];
    /** @var list<\Throwable> */
    private array $reported = [];

    private function schema(): Schema
    {
        $role = new EnumType('Role', ['ADMIN' => 'admin', 'USER' => ['value' => 'user', 'description' => 'normal'], 'OLD' => ['value' => 'old', 'deprecated' => 'use USER']]);
        $author = null;
        $book = new ObjectType('Book', function () use (&$author): array {
            return [
                'id' => Types::nonNull(Types::id()),
                'title' => Types::nonNull(Types::string()),
                'pages' => Types::int(),
                'price' => Types::float(),
                'inStock' => Types::boolean(),
                'secret' => ['type' => Types::nonNull(Types::string()), 'resolve' => fn () => null],     // violates its own non-null
                'broken' => ['type' => Types::string(), 'resolve' => fn () => throw new \RuntimeException('db password is hunter2')],
                'friendly' => ['type' => Types::string(), 'resolve' => fn () => throw new GraphQLError('Nope, on purpose.', ['code' => 'NOPE'])],
                'author' => ['type' => $author, 'resolve' => fn (array $b) => ['name' => $b['authorName'] ?? 'Anon']],
                'tags' => Types::listOf(Types::nonNull(Types::string())),
                'maybeTags' => Types::listOf(Types::string()),
            ];
        });
        $author = new ObjectType('Author', fn () => [
            'name' => Types::nonNull(Types::string()),
            'books' => ['type' => Types::listOf($book), 'resolve' => fn () => [['id' => 9, 'title' => 'Nested']]],
        ]);
        $bookInput = new InputObjectType('BookInput', [
            'title' => Types::nonNull(Types::string()),
            'pages' => ['type' => Types::int(), 'default' => 100],
            'role' => Types::nonNull($role),
            'tags' => Types::listOf(Types::string()),
        ]);

        $books = [
            ['id' => 1, 'title' => 'Dune', 'pages' => 412, 'price' => 9.5, 'inStock' => 1, 'tags' => ['sf', 'classic'], 'maybeTags' => ['a', null], 'authorName' => 'Herbert'],
            ['id' => 2, 'title' => 'Emma', 'pages' => 474, 'price' => 7, 'inStock' => 0, 'tags' => ['romance'], 'maybeTags' => null],
        ];

        $query = new ObjectType('Query', [
            'books' => [
                'type' => Types::nonNull(Types::listOf(Types::nonNull($book))),
                'args' => ['limit' => ['type' => Types::int(), 'default' => 10]],
                'resolve' => fn ($r, array $a) => array_slice($books, 0, $a['limit']),
            ],
            'book' => [
                'type' => $book,
                'args' => ['id' => Types::nonNull(Types::id())],
                'resolve' => function ($r, array $a) use ($books) {
                    foreach ($books as $b) {
                        if ((string) $b['id'] === $a['id']) {
                            return $b;
                        }
                    }
                    return null;
                },
            ],
            'echoRole' => ['type' => $role, 'args' => ['role' => Types::nonNull($role)], 'resolve' => fn ($r, array $a) => $a['role']],
            'echoList' => ['type' => Types::listOf(Types::int()), 'args' => ['xs' => Types::listOf(Types::int())], 'resolve' => fn ($r, array $a) => $a['xs'] ?? null],
            'echoInput' => ['type' => Types::string(), 'args' => ['in' => Types::nonNull($bookInput)], 'resolve' => fn ($r, array $a) => json_encode($a['in'])],
            'hasArg' => ['type' => Types::string(), 'args' => ['x' => Types::int()], 'resolve' => fn ($r, array $a) => array_key_exists('x', $a) ? 'present' : 'absent'],
            'boom' => ['type' => Types::nonNull(Types::string()), 'resolve' => fn () => throw new \RuntimeException('secret internals')],
            'info' => ['type' => Types::string(), 'resolve' => fn ($r, $a, $ctx, ResolveInfo $i) => implode(',', $i->path) . '|' . $i->fieldName . '|' . $i->parentType],
            'ctx' => ['type' => Types::string(), 'resolve' => fn ($r, $a, $ctx) => (string) $ctx],
            'dangerous' => ['type' => Types::int(), 'resolve' => fn () => 'not-a-number'],
            'user' => ['type' => new ObjectType('Person', ['name' => Types::string()]), 'resolve' => fn () => new class {
                public string $name = 'Obj';
                public string $hidden = 'never exposed';
            }],
        ]);
        $mutation = new ObjectType('Mutation', [
            'log' => [
                'type' => Types::nonNull(Types::int()),
                'args' => ['msg' => Types::nonNull(Types::string())],
                'resolve' => function ($r, array $a) {
                    $this->log[] = $a['msg'];
                    return count($this->log);
                },
            ],
        ]);

        return new Schema($query, $mutation);
    }

    private function gql(string $query, array $vars = [], ?string $op = null, array $opts = []): array
    {
        $o = $opts + ['depth' => 10, 'nodes' => 500, 'len' => 20000, 'introspection' => true, 'debug' => false];
        $g = new GraphQL($this->schema(), $o['depth'], $o['nodes'], $o['len'], $o['introspection'], $o['debug'], function (\Throwable $e): void {
            $this->reported[] = $e;
        });
        return $g->execute($query, $vars, $op, $opts['context'] ?? 'ctx-value', $opts['mutations'] ?? true);
    }

    private function messages(array $result): array
    {
        return array_column($result['errors'] ?? [], 'message');
    }

    // ------------------------------------------------------------------ happy paths

    public function testQueriesAliasesFragmentsAndTypename(): void
    {
        $r = $this->gql('{ first: book(id: 1) { __typename ...F } other: book(id: "2") { t: title } } fragment F on Book { title pages price inStock tags }');
        $this->assertArrayNotHasKey('errors', $r);
        $this->assertSame(['__typename' => 'Book', 'title' => 'Dune', 'pages' => 412, 'price' => 9.5, 'inStock' => true, 'tags' => ['sf', 'classic']], $r['data']['first']);
        $this->assertSame(['t' => 'Emma'], $r['data']['other']);
    }

    public function testNullablesListsAndNestedObjects(): void
    {
        $r = $this->gql('{ book(id: 1) { maybeTags author { name books { title } } } book2: book(id: 99) { title } }');
        $this->assertSame(['a', null], $r['data']['book']['maybeTags']);
        $this->assertSame('Herbert', $r['data']['book']['author']['name']);
        $this->assertSame([['title' => 'Nested']], $r['data']['book']['author']['books']);
        $this->assertNull($r['data']['book2']);
        $this->assertArrayNotHasKey('errors', $r);
    }

    public function testDefaultResolverReadsPublicPropertiesOfObjectsOnlyForDeclaredFields(): void
    {
        $r = $this->gql('{ user { name } }');
        $this->assertSame(['name' => 'Obj'], $r['data']['user']);
        $r = $this->gql('{ user { hidden } }');
        $this->assertSame('Cannot query field "hidden" on type "Person".', $r['errors'][0]['message']);
    }

    public function testBooleanSerializesDatabaseZeroAndOne(): void
    {
        $r = $this->gql('{ books { inStock } }');
        $this->assertSame([['inStock' => true], ['inStock' => false]], $r['data']['books']);
    }

    public function testSkipAndIncludeDirectivesWithVariables(): void
    {
        $q = 'query($no: Boolean!, $yes: Boolean!) { book(id: 1) { title @skip(if: $no) pages @include(if: $yes) price @include(if: false) inStock @skip(if: false) } }';
        $r = $this->gql($q, ['no' => true, 'yes' => true]);
        $this->assertSame(['pages' => 412, 'inStock' => true], $r['data']['book']);
        $r = $this->gql($q, ['no' => false, 'yes' => false]);
        $this->assertSame(['title' => 'Dune', 'inStock' => true], $r['data']['book']);
    }

    public function testInfoContextAndSubfields(): void
    {
        $r = $this->gql('{ x: info ctx }', opts: ['context' => 'hello']);
        $this->assertSame(['x' => 'x|info|Query', 'ctx' => 'hello'], $r['data']);
    }

    // ------------------------------------------------------------------ variables and arguments

    public function testVariableCoercionDefaultsAndRequired(): void
    {
        $q = 'query($id: ID!, $limit: Int = 1) { book(id: $id) { title } books(limit: $limit) { id } }';
        $r = $this->gql($q, ['id' => 2]);
        $this->assertSame('Emma', $r['data']['book']['title'], 'an int is accepted for an ID and becomes a string');
        $this->assertCount(1, $r['data']['books'], 'the default applies when the variable is omitted');

        $r = $this->gql($q);
        $this->assertSame('Variable "$id" of required type "ID!" was not provided.', $r['errors'][0]['message']);
        $this->assertArrayNotHasKey('data', $r);
    }

    /** @return array<string,array{0:string,1:array,2:string}> */
    public static function badVariables(): array
    {
        return [
            'string for int' => ['query($n: Int) { books(limit: $n) { id } }', ['n' => '5'], 'Int cannot represent non-integer value'],
            'float for int' => ['query($n: Int) { books(limit: $n) { id } }', ['n' => 1.5], 'Int cannot represent non-integer value'],
            'int overflow' => ['query($n: Int) { books(limit: $n) { id } }', ['n' => 2147483648], 'non 32-bit'],
            'bool for int' => ['query($n: Int) { books(limit: $n) { id } }', ['n' => true], 'Int cannot represent'],
            'null for non-null' => ['query($n: ID!) { book(id: $n) { id } }', ['n' => null], 'not to be null'],
            'array for id' => ['query($n: ID!) { book(id: $n) { id } }', ['n' => [1]], 'ID cannot represent'],
            'bad enum' => ['query($r: Role!) { echoRole(role: $r) }', ['r' => 'ROOT'], 'does not exist in the Role enum'],
            'internal enum value' => ['query($r: Role!) { echoRole(role: $r) }', ['r' => 'admin'], 'does not exist in the Role enum'],
            'non-string enum' => ['query($r: Role!) { echoRole(role: $r) }', ['r' => 1], 'non-string'],
            'object for scalar list item' => ['query($x: [Int]) { echoList(xs: $x) }', ['x' => [1, 'two']], 'at "x[1]"'],
            'input: missing required' => ['query($i: BookInput!) { echoInput(in: $i) }', ['i' => ['pages' => 5]], 'was not provided'],
            'input: unknown field' => ['query($i: BookInput!) { echoInput(in: $i) }', ['i' => ['title' => 't', 'role' => 'ADMIN', 'isAdmin' => true]], 'is not defined by type "BookInput"'],
            'input: wrong nested type' => ['query($i: BookInput!) { echoInput(in: $i) }', ['i' => ['title' => 't', 'role' => 'ADMIN', 'pages' => 'x']], 'at "i.pages"'],
            'input: list for object' => ['query($i: BookInput!) { echoInput(in: $i) }', ['i' => [1, 2]], 'to be an object'],
        ];
    }

    #[DataProvider('badVariables')]
    public function testInvalidVariablesAreRejectedBeforeAnythingRuns(string $query, array $vars, string $expected): void
    {
        $r = $this->gql($query, $vars);
        $this->assertArrayNotHasKey('data', $r);
        $this->assertStringContainsString($expected, $r['errors'][0]['message']);
        $this->assertSame([], $this->reported);
    }

    public function testInputObjectsListsEnumsAndDefaults(): void
    {
        $r = $this->gql('query($i: BookInput!) { echoInput(in: $i) echoRole(role: ADMIN) echoList(xs: 3) a: echoList(xs: [1, null, 3]) }', ['i' => ['title' => 'T', 'role' => 'USER', 'tags' => 'solo']]);
        $this->assertSame('{"title":"T","pages":100,"role":"user","tags":["solo"]}', $r['data']['echoInput']);
        $this->assertSame('ADMIN', $r['data']['echoRole']);
        $this->assertSame([3], $r['data']['echoList'], 'a single value is accepted where a list is expected');
        $this->assertSame([1, null, 3], $r['data']['a']);
    }

    public function testLiteralArgumentErrorsAreFieldErrors(): void
    {
        $r = $this->gql('{ ok: echoRole(role: ADMIN) bad: echoList(xs: ["a"]) }');
        $this->assertSame('ADMIN', $r['data']['ok']);
        $this->assertNull($r['data']['bad']);
        $this->assertStringContainsString('Argument "xs"', $r['errors'][0]['message']);
    }

    public function testAnOmittedArgumentIsAbsentNotNull(): void
    {
        $this->assertSame('absent', $this->gql('{ hasArg }')['data']['hasArg']);
        $this->assertSame('present', $this->gql('{ hasArg(x: 1) }')['data']['hasArg']);
        $this->assertSame('present', $this->gql('{ hasArg(x: null) }')['data']['hasArg']);
        $this->assertSame('absent', $this->gql('query($x: Int) { hasArg(x: $x) }')['data']['hasArg']);
    }

    // ------------------------------------------------------------------ errors and null propagation

    public function testPartialDataWithPathsAndMaskedInternalErrors(): void
    {
        $r = $this->gql('{ book(id: 1) { title broken friendly pages } }');
        $this->assertSame(['title' => 'Dune', 'broken' => null, 'friendly' => null, 'pages' => 412], $r['data']['book']);
        $this->assertSame(['Internal server error.', 'Nope, on purpose.'], $this->messages($r));
        $this->assertSame(['book', 'broken'], $r['errors'][0]['path']);
        $this->assertSame('NOPE', $r['errors'][1]['extensions']['code']);
        $this->assertStringNotContainsString('hunter2', json_encode($r));
        $this->assertCount(1, $this->reported, 'the real exception reached the reporter (for logging)');
        $this->assertStringContainsString('hunter2', $this->reported[0]->getMessage());
    }

    public function testDebugModeRevealsTheRealMessage(): void
    {
        $r = $this->gql('{ book(id: 1) { broken } }', opts: ['debug' => true]);
        $this->assertSame('db password is hunter2', $r['errors'][0]['message']);
    }

    public function testNonNullViolationsBubbleToTheNearestNullableParent(): void
    {
        $r = $this->gql('{ book(id: 1) { title secret } ok: books { id } }');
        $this->assertNull($r['data']['book'], 'secret is non-null, so the whole nullable book becomes null');
        $this->assertCount(2, $r['data']['ok']);
        $this->assertSame('Cannot return null for non-nullable field Book.secret.', $r['errors'][0]['message']);
        $this->assertSame(['book', 'secret'], $r['errors'][0]['path']);
    }

    public function testNonNullListItemsAndRootBubbling(): void
    {
        $r = $this->gql('{ books { id secret } }');
        $this->assertNull($r['data'], 'books is non-null, so the failure reaches the root');
        $r = $this->gql('{ boom }');
        $this->assertNull($r['data']);
        $this->assertSame('Internal server error.', $r['errors'][0]['message']);
    }

    public function testSerializationFailureIsAFieldErrorNotACrash(): void
    {
        $r = $this->gql('{ dangerous ok: books { id } }');
        $this->assertNull($r['data']['dangerous']);
        $this->assertCount(2, $r['data']['ok']);
        $this->assertSame('Internal server error.', $r['errors'][0]['message']);
    }

    // ------------------------------------------------------------------ validation

    /** @return array<string,array{0:string,1:string}> */
    public static function invalidDocuments(): array
    {
        return [
            'unknown field' => ['{ nope }', 'Cannot query field "nope" on type "Query".'],
            'unknown nested field' => ['{ book(id: 1) { nope } }', 'Cannot query field "nope" on type "Book".'],
            'unknown argument' => ['{ book(id: 1, evil: 2) { id } }', 'Unknown argument "evil"'],
            'missing required argument' => ['{ book { id } }', 'argument "id" of type "ID!" is required'],
            'leaf with selection' => ['{ book(id: 1) { title { x } } }', 'must not have a selection'],
            'object without selection' => ['{ book(id: 1) }', 'must have a selection of subfields'],
            'undefined variable' => ['{ book(id: $x) { id } }', 'Variable "$x" is not defined'],
            'undefined variable in nested value' => ['{ echoList(xs: [1, $y]) }', 'Variable "$y" is not defined'],
            'unknown fragment' => ['{ book(id: 1) { ...Nope } }', 'Unknown fragment "Nope"'],
            'fragment cycle' => ['{ book(id: 1) { ...A } } fragment A on Book { ...B } fragment B on Book { ...A }', 'within itself'],
            'fragment wrong type' => ['{ book(id: 1) { ...F } } fragment F on Author { name }', 'can never be of type "Author"'],
            'inline fragment wrong type' => ['{ book(id: 1) { ... on Author { name } } }', 'can never be of type "Author"'],
            'unknown type condition' => ['{ book(id: 1) { ... on Ghost { x } } }', 'unknown type "Ghost"'],
            'unknown directive' => ['{ book(id: 1) @live { id } }', 'Unknown directive "@live"'],
            'directive without if' => ['{ book(id: 1) { id @skip } }', 'requires exactly one argument'],
            'conflicting aliases' => ['{ a: book(id: 1) { id } a: book(id: 2) { id } }', 'conflict'],
            'unknown variable type' => ['query($x: Ghost) { books { id } }', 'Unknown type "Ghost"'],
            'object as variable type' => ['query($x: Book) { books { id } }', 'non-input type'],
            'subscription' => ['subscription { books { id } }', 'Subscriptions are not supported'],
            'introspection outside root' => ['{ book(id: 1) { __schema { types { name } } } }', 'Cannot query field "__schema"'],
        ];
    }

    #[DataProvider('invalidDocuments')]
    public function testInvalidDocumentsNeverReachResolvers(string $query, string $expected): void
    {
        $r = $this->gql($query);
        $this->assertArrayNotHasKey('data', $r);
        $this->assertStringContainsString($expected, implode(' | ', $this->messages($r)));
        $this->assertSame([], $this->log);
    }

    public function testOperationSelection(): void
    {
        $q = 'query A { books { id } } query B { book(id: 1) { id } }';
        $this->assertStringContainsString('operation name', $this->gql($q)['errors'][0]['message']);
        $this->assertSame(['book'], array_keys($this->gql($q, op: 'B')['data']));
        $this->assertStringContainsString('Unknown operation named "C"', $this->gql($q, op: 'C')['errors'][0]['message']);
        $this->assertStringContainsString('only one operation named', $this->gql('query A { books { id } } query A { books { id } }', op: 'A')['errors'][0]['message']);
        $this->assertStringContainsString('anonymous operation', $this->gql('{ books { id } } query B { books { id } }')['errors'][0]['message']);
    }

    // ------------------------------------------------------------------ limits

    public function testDepthLimit(): void
    {
        $q = '{ book(id: 1) { author { books { author { books { id } } } } } }';
        $this->assertArrayHasKey('data', $this->gql($q, opts: ['depth' => 6]));
        $r = $this->gql($q, opts: ['depth' => 5]);
        $this->assertArrayNotHasKey('data', $r);
        $this->assertStringContainsString('maximum depth is 5', $r['errors'][0]['message']);
    }

    public function testNodeLimitStopsAliasAmplification(): void
    {
        $aliases = implode(' ', array_map(fn ($i) => "a{$i}: books { id title }", range(1, 300)));
        $r = $this->gql("{ {$aliases} }", opts: ['nodes' => 100]);
        $this->assertStringContainsString('too large', $r['errors'][0]['message']);
        $this->assertArrayNotHasKey('data', $r);
    }

    public function testExponentialFragmentExpansionIsStopped(): void
    {
        $fragments = 'fragment F0 on Book { id }';
        for ($i = 1; $i <= 25; $i++) {
            $p = $i - 1;
            $fragments .= " fragment F{$i} on Book { ...F{$p} ...F{$p} x{$i}: title }";
        }
        $start = microtime(true);
        $r = $this->gql("{ book(id: 1) { ...F25 } } {$fragments}", opts: ['nodes' => 500]);
        $this->assertLessThan(1.0, microtime(true) - $start, 'expansion is cut off at the node limit instead of running 2^25 times');
        $this->assertStringContainsString('too large', implode(' ', $this->messages($r)));
    }

    public function testQueryLengthLimit(): void
    {
        $r = $this->gql('{ books { id } }' . str_repeat(' ', 100), opts: ['len' => 50]);
        $this->assertSame('QUERY_TOO_LARGE', $r['errors'][0]['extensions']['code']);
    }

    // ------------------------------------------------------------------ mutations

    public function testMutationsRunInOrder(): void
    {
        $r = $this->gql('mutation { a: log(msg: "one") b: log(msg: "two") c: log(msg: "three") }');
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $r['data']);
        $this->assertSame(['one', 'two', 'three'], $this->log);
    }

    public function testMutationsCanBeForbidden(): void
    {
        $r = $this->gql('mutation { log(msg: "x") }', opts: ['mutations' => false]);
        $this->assertSame('METHOD_NOT_ALLOWED', $r['errors'][0]['extensions']['code']);
        $this->assertSame([], $this->log, 'nothing executed');
    }

    // ------------------------------------------------------------------ introspection

    public function testIntrospectionDescribesTheSchema(): void
    {
        $r = $this->gql('{ __schema { queryType { name } mutationType { name } subscriptionType { name } directives { name } types { name kind } } }');
        $this->assertArrayNotHasKey('errors', $r);
        $s = $r['data']['__schema'];
        $this->assertSame('Query', $s['queryType']['name']);
        $this->assertSame('Mutation', $s['mutationType']['name']);
        $this->assertNull($s['subscriptionType']);
        $this->assertSame(['skip', 'include'], array_column($s['directives'], 'name'));
        $names = array_column($s['types'], 'name');
        foreach (['Query', 'Book', 'Author', 'Role', 'BookInput', 'Int', 'String', 'ID', '__Schema', '__Type'] as $expected) {
            $this->assertContains($expected, $names);
        }
    }

    public function testTypeLookupFieldsArgsEnumsAndWrappers(): void
    {
        $r = $this->gql('{ b: __type(name: "Book") { kind name fields { name type { kind name ofType { kind name ofType { kind name ofType { name } } } } } }
            q: __type(name: "Query") { fields { name args { name defaultValue type { name kind ofType { name } } } } }
            e: __type(name: "Role") { kind enumValues { name } all: enumValues(includeDeprecated: true) { name isDeprecated deprecationReason } }
            i: __type(name: "BookInput") { kind inputFields { name defaultValue } }
            none: __type(name: "Nope") { name } }');
        $this->assertArrayNotHasKey('errors', $r);
        $fields = array_column($r['data']['b']['fields'], null, 'name');
        $this->assertSame('OBJECT', $r['data']['b']['kind']);
        $this->assertSame('NON_NULL', $fields['id']['type']['kind']);
        $this->assertSame('ID', $fields['id']['type']['ofType']['name']);
        $this->assertSame('LIST', $fields['tags']['type']['kind']);
        $this->assertSame(['LIST', 'NON_NULL', 'SCALAR'], [$fields['tags']['type']['kind'], $fields['tags']['type']['ofType']['kind'], $fields['tags']['type']['ofType']['ofType']['kind']]);
        $limit = array_column(array_column($r['data']['q']['fields'], null, 'name')['books']['args'], null, 'name')['limit'];
        $this->assertSame('10', $limit['defaultValue']);
        $this->assertSame(['ADMIN', 'USER'], array_column($r['data']['e']['enumValues'], 'name'), 'deprecated values are hidden by default');
        $this->assertSame('use USER', array_column($r['data']['e']['all'], null, 'name')['OLD']['deprecationReason']);
        $this->assertSame('ENUM', $r['data']['e']['kind']);
        $this->assertSame(['title', 'pages', 'role', 'tags'], array_column($r['data']['i']['inputFields'], 'name'));
        $this->assertSame('100', array_column($r['data']['i']['inputFields'], null, 'name')['pages']['defaultValue']);
        $this->assertNull($r['data']['none']);
    }

    public function testIntrospectionCanBeDisabled(): void
    {
        foreach (['{ __schema { types { name } } }', '{ __type(name: "Book") { name } }'] as $q) {
            $r = $this->gql($q, opts: ['introspection' => false]);
            $this->assertArrayNotHasKey('data', $r);
            $this->assertStringContainsString('introspection is disabled', $r['errors'][0]['message']);
        }
        $this->assertSame('Query', $this->gql('{ __typename }', opts: ['introspection' => false])['data']['__typename'], '__typename stays available');
    }

    // ------------------------------------------------------------------ schema construction

    public function testSchemaRejectsInvalidDefinitions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Types::listOf(new ObjectType('__Bad', ['a' => Types::int()]));
    }

    public function testDuplicateTypeNamesAreRejected(): void
    {
        $a = new ObjectType('Dup', ['x' => Types::int()]);
        $b = new ObjectType('Dup', ['y' => Types::int()]);
        $schema = new Schema(new ObjectType('Query', ['a' => $a, 'b' => $b]));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Two different GraphQL types');
        $schema->typeMap();
    }

    public function testNonNullCannotWrapNonNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Types::nonNull(Types::nonNull(Types::int()));
    }

    public function testInputFieldsCannotUseOutputTypes(): void
    {
        $o = new ObjectType('Out', ['a' => Types::int()]);
        $in = new InputObjectType('In', ['bad' => $o]);
        $this->expectException(\InvalidArgumentException::class);
        $in->fields();
    }

    public function testCustomScalar(): void
    {
        $date = Types::scalar(
            'Date',
            fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : throw new \InvalidArgumentException('bad date'),
            fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? new \DateTimeImmutable($v) : throw new \InvalidArgumentException('Date must look like 2026-01-31.')
        );
        $schema = new Schema(new ObjectType('Query', ['next' => ['type' => $date, 'args' => ['d' => Types::nonNull($date)], 'resolve' => fn ($r, $a) => $a['d']->modify('+1 day')]]));
        $g = new GraphQL($schema);
        $this->assertSame('2026-02-01', $g->execute('{ next(d: "2026-01-31") }')['data']['next']);
        $this->assertSame('2026-02-01', $g->execute('query($d: Date!) { next(d: $d) }', ['d' => '2026-01-31'])['data']['next']);
        $this->assertStringContainsString('Date must look like', $g->execute('query($d: Date!) { next(d: $d) }', ['d' => 'tomorrow'])['errors'][0]['message']);
    }
}

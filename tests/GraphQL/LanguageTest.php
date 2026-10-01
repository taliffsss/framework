<?php

declare(strict_types=1);

namespace Naluz\Tests\GraphQL;

use Naluz\GraphQL\GraphQLError;
use Naluz\GraphQL\Language\Lexer;
use Naluz\GraphQL\Language\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LanguageTest extends TestCase
{
    public function testParsesOperationsFragmentsVariablesAndDirectives(): void
    {
        $doc = Parser::parse('query Q($id: ID! = 5, $tags: [String!]) @x { a: user(id: $id, f: [1, 2.5, "s", true, null, ENUM, {k: 1}]) { ...F ... on User @skip(if: true) { id } } } fragment F on User { name }');
        $op = $doc['operations'][0];
        $this->assertSame('Q', $op['name']);
        $this->assertSame('nonnull', $op['variables'][0]['type']['kind']);
        $this->assertSame(['kind' => 'int', 'value' => 5], $op['variables'][0]['default']);
        $this->assertSame('list', $op['variables'][1]['type']['kind']);
        $field = $op['selections'][0];
        $this->assertSame('a', $field['alias']);
        $this->assertSame('user', $field['name']);
        $this->assertSame(['var', 'list'], [$field['args']['id']['kind'], $field['args']['f']['kind']]);
        $this->assertSame(['int', 'float', 'string', 'bool', 'null', 'enum', 'object'], array_column($field['args']['f']['value'], 'kind'));
        $this->assertSame('spread', $field['selections'][0]['kind']);
        $this->assertSame('inline', $field['selections'][1]['kind']);
        $this->assertSame('skip', $field['selections'][1]['directives'][0]['name']);
        $this->assertArrayHasKey('F', $doc['fragments']);
    }

    public function testShorthandQueryCommentsCommasAndBom(): void
    {
        $doc = Parser::parse("\xEF\xBB\xBF# a comment\n{ a, b # trailing\n c }");
        $this->assertSame('query', $doc['operations'][0]['type']);
        $this->assertSame(['a', 'b', 'c'], array_column($doc['operations'][0]['selections'], 'name'));
    }

    public function testStringEscapesAndBlockStrings(): void
    {
        $query = <<<'GQL'
{ f(a: "q\"\\\n\u00e9", b: """
    line one
      line two
    """) }
GQL;
        $doc = Parser::parse($query);
        $args = $doc['operations'][0]['selections'][0]['args'];
        $this->assertSame("q\"\\\n\u{e9}", $args['a']['value']);
        $this->assertSame("line one\n  line two", $args['b']['value']);
    }

    public function testBigIntegersBecomeFloatsSoIntCoercionCanRejectThem(): void
    {
        $doc = Parser::parse('{ f(a: 99999999999999999999) }');
        $this->assertSame('float', $doc['operations'][0]['selections'][0]['args']['a']['kind']);
    }

    /** @return array<string,array{0:string}> */
    public static function syntaxErrors(): array
    {
        return [
            'empty' => [''],
            'only comment' => ['# nothing'],
            'unclosed brace' => ['{ a '],
            'empty selection' => ['{ }'],
            'bad character' => ['{ a ? }'],
            'unterminated string' => ['{ f(a: "oops) }'],
            'newline in string' => ["{ f(a: \"a\nb\") }"],
            'bad escape' => ['{ f(a: "\q") }'],
            'bad unicode escape' => ['{ f(a: "\u12") }'],
            'leading zero' => ['{ f(a: 01) }'],
            'dangling minus' => ['{ f(a: -) }'],
            'number then letter' => ['{ f(a: 1x) }'],
            'single dot' => ['{ a.b }'],
            'unterminated block' => ['{ f(a: """x) }'],
            'duplicate argument' => ['{ f(a: 1, a: 2) }'],
            'duplicate variable' => ['query ($a: Int, $a: Int) { f }'],
            'variable in default' => ['query ($a: Int = $b) { f }'],
            'sdl' => ['type Query { a: Int }'],
            'fragment named on' => ['fragment on on User { a }'],
            'duplicate fragment' => ['fragment A on U { a } fragment A on U { b }'],
            'invalid utf8' => ["{ a(b: \"\xff\") }"],
        ];
    }

    #[DataProvider('syntaxErrors')]
    public function testSyntaxErrorsAreClientSafeAndLocated(string $source): void
    {
        try {
            Parser::parse($source);
            $this->fail('accepted invalid GraphQL');
        } catch (GraphQLError $e) {
            $this->assertStringStartsWith('Syntax Error', $e->getMessage());
        }
    }

    public function testErrorsCarryLineAndColumn(): void
    {
        try {
            Parser::parse("{\n  a ?\n}");
            $this->fail();
        } catch (GraphQLError $e) {
            $this->assertSame([['line' => 2, 'column' => 5]], $e->locations);
        }
    }

    public function testNestingLimitStopsHostileDocuments(): void
    {
        $this->expectException(GraphQLError::class);
        $this->expectExceptionMessage('nested too deeply');
        Parser::parse(str_repeat('{a', 5000) . str_repeat('}', 5000)); // no stack exhaustion
    }

    public function testTokenLimit(): void
    {
        $this->expectException(GraphQLError::class);
        $this->expectExceptionMessage('too large');
        Lexer::tokenize('{ ' . str_repeat('a ', 300) . '}', 100);
    }

    public function testDeeplyNestedValuesAreLimited(): void
    {
        $this->expectException(GraphQLError::class);
        Parser::parse('{ f(a: ' . str_repeat('[', 5000) . str_repeat(']', 5000) . ') }');
    }
}

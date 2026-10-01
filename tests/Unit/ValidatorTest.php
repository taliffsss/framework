<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use Naluz\Database\DatabaseManager;
use Naluz\Validation\ValidationException;
use Naluz\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private function fails(array $data, array $rules): array
    {
        return Validator::make($data, $rules)->errors();
    }

    #[DataProvider('cases')]
    public function testRules(string $rule, mixed $good, mixed $bad): void
    {
        $this->assertSame([], $this->fails(['f' => $good], ['f' => $rule]), "good value for {$rule}");
        $this->assertArrayHasKey('f', $this->fails(['f' => $bad], ['f' => $rule]), "bad value for {$rule}");
    }

    public static function cases(): array
    {
        return [
            'required' => ['required', 'x', ''],
            'required null' => ['required', 0, null],
            'string' => ['string', 'x', 5],
            'integer' => ['integer', '12', '1.5'],
            'numeric' => ['numeric', '1.5', 'abc'],
            'boolean' => ['boolean', '1', 'yes'],
            'array' => ['array', [1], 'x'],
            'email' => ['email', 'a@b.co', 'a@'],
            'url' => ['url', 'https://x.io/p', 'javascript:alert(1)'],
            'date' => ['date', '2026-01-31', 'not a date'],
            'alpha' => ['alpha', 'abc', 'ab1'],
            'alpha_num' => ['alpha_num', 'ab1', 'a-b'],
            'alpha_dash' => ['alpha_dash', 'a-b_1', 'a b'],
            'uuid' => ['uuid', '123e4567-e89b-12d3-a456-426614174000', 'nope'],
            'min string' => ['min:3', 'abc', 'ab'],
            'max string' => ['max:3', 'abc', 'abcd'],
            'between' => ['between:2,4', 'abc', 'a'],
            'in' => ['in:a,b', 'a', 'c'],
            'not_in' => ['not_in:a,b', 'c', 'a'],
            'regex' => ['regex:/^\d{3}$/', '123', '12'],
        ];
    }

    public function testNumericSizeRules(): void
    {
        $this->assertSame([], $this->fails(['n' => 5], ['n' => 'integer|min:1|max:10']));
        $this->assertArrayHasKey('n', $this->fails(['n' => '50'], ['n' => 'integer|max:10']), 'numeric strings compare by value when integer');
        $this->assertArrayHasKey('a', $this->fails(['a' => [1, 2, 3]], ['a' => 'array|max:2']), 'arrays compare by count');
    }

    public function testOptionalFieldsAreSkippedUnlessPresent(): void
    {
        $this->assertSame([], $this->fails([], ['n' => 'integer|min:5']));
        $this->assertSame([], $this->fails(['n' => null], ['n' => 'nullable|integer']));
        $this->assertArrayHasKey('n', $this->fails(['n' => 'x'], ['n' => 'integer']));
    }

    public function testConfirmedSameDifferent(): void
    {
        $this->assertSame([], $this->fails(['p' => 'a', 'p_confirmation' => 'a'], ['p' => 'confirmed']));
        $this->assertArrayHasKey('p', $this->fails(['p' => 'a', 'p_confirmation' => 'b'], ['p' => 'confirmed']));
        $this->assertSame([], $this->fails(['a' => 1, 'b' => 2], ['a' => 'different:b']));
    }

    public function testWildcardsAndNestedPaths(): void
    {
        $data = ['items' => [['qty' => 1], ['qty' => 'x'], ['qty' => 3]], 'user' => ['email' => 'bad']];
        $errors = $this->fails($data, ['items.*.qty' => 'required|integer', 'user.email' => 'email']);
        $this->assertArrayHasKey('items.1.qty', $errors);
        $this->assertArrayNotHasKey('items.0.qty', $errors);
        $this->assertArrayHasKey('user.email', $errors);
    }

    public function testClosureRulesAndCustomMessages(): void
    {
        $v = Validator::make(['n' => 3], ['n' => ['required', fn ($v) => $v % 2 ? 'must be even' : null]], ['required' => 'x']);
        $this->assertSame(['n' => ['must be even']], $v->errors());

        $v = Validator::make([], ['name' => 'required'], ['name.required' => 'Who are you?']);
        $this->assertSame(['name' => ['Who are you?']], $v->errors());
        $this->assertSame(['name' => ['The name field is required.']], Validator::make([], ['name' => 'required'])->errors());
    }

    public function testValidatedReturnsOnlyRuledFields(): void
    {
        $out = Validator::make(['a' => 1, 'evil' => 'x', 'n' => ['k' => 2, 'z' => 3]], ['a' => 'integer', 'n.k' => 'integer'])->validate();
        $this->assertSame(['a' => 1, 'n' => ['k' => 2]], $out);
    }

    public function testValidateThrowsWithErrorsAndInput(): void
    {
        try {
            Validator::make(['a' => 'x'], ['a' => 'integer'])->validate();
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status());
            $this->assertArrayHasKey('a', $e->errors);
            $this->assertSame(['a' => 'x'], $e->input);
        }
    }

    public function testUniqueAndExistsRules(): void
    {
        $db = (new DatabaseManager(['default' => 't', 'connections' => ['t' => ['driver' => 'sqlite', 'database' => ':memory:']]]))->connection();
        $db->schema()->create('users', function ($t) {
            $t->id();
            $t->string('email');
        });
        $db->table('users')->insert(['email' => 'a@x.io']);

        $this->assertArrayHasKey('email', Validator::make(['email' => 'a@x.io'], ['email' => 'unique:users,email'], [], $db)->errors());
        $this->assertSame([], Validator::make(['email' => 'new@x.io'], ['email' => 'unique:users,email'], [], $db)->errors());
        $this->assertSame([], Validator::make(['email' => 'a@x.io'], ['email' => 'unique:users,email,1'], [], $db)->errors(), 'ignoring own row');
        $this->assertSame([], Validator::make(['id' => 1], ['id' => 'exists:users,id'], [], $db)->errors());
        $this->assertArrayHasKey('id', Validator::make(['id' => 9], ['id' => 'exists:users,id'], [], $db)->errors());
        $this->expectException(\InvalidArgumentException::class);
        Validator::make(['x' => 'a'], ['x' => 'unique:users;DROP TABLE users,email'], [], $db)->errors();
    }

    public function testUnknownRuleFailsLoudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Validator::make(['a' => 'x'], ['a' => 'frobnicate'])->errors();
    }
}

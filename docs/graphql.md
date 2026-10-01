# GraphQL

NaluzPHP has a built-in, dependency-free GraphQL server (`Naluz\GraphQL`): parser, validator, executor, introspection
and an HTTP endpoint. Schemas are written in PHP (code-first).

The skeleton ships a working example: `app/GraphQL/AppSchema.php`, served at **`/api/graphql`**.

## Try it

```bash
php naluz run:server --port=8001

curl -s localhost:8001/api/graphql -H 'Content-Type: application/json' \
  -d '{"query":"{ posts(first: 5) { id title author { name } } }"}'

# variables, operation names
curl -s localhost:8001/api/graphql -H 'Content-Type: application/json' \
  -d '{"query":"query Post($id: ID!) { post(id: $id) { title } }","variables":{"id":"1"}}'

# mutations need a bearer token (JWT); the author is the token's user
curl -s localhost:8001/api/graphql -H 'Content-Type: application/json' -H "Authorization: Bearer $TOKEN" \
  -d '{"query":"mutation { createPost(input: {title: \"Hi\", body: \"…\"}) { id } }"}'
```

## Requests and responses

| | |
|---|---|
| `POST` | `application/json` `{"query", "variables", "operationName"}`, or `application/graphql` (the body is the query) |
| `GET` | `?query=…&variables={…}&operationName=…`, **queries only** (a mutation over GET gets `405`) |
| Batching | not supported (a JSON array is refused): it lets one request multiply the work |
| Success / field errors | `200` with `{"data": …, "errors": [...]}`; a failed field is `null` and listed in `errors` with its `path` |
| Request errors (syntax, validation, limits) | `400` with `{"errors": [...]}` and no `data` |

## Defining a schema

```php
use Naluz\GraphQL\{Schema, Types};
use Naluz\GraphQL\Type\{ObjectType, InputObjectType, EnumType};

$post = new ObjectType('Post', [
    'id'    => Types::nonNull(Types::id()),
    'title' => Types::nonNull(Types::string()),
    'tags'  => Types::listOf(Types::nonNull(Types::string())),
    'createdAt' => ['type' => Types::string(), 'resolve' => fn (Post $p) => $p->created_at],
]);

$query = new ObjectType('Query', [
    'post' => [
        'type' => $post,
        'args' => ['id' => Types::nonNull(Types::id())],
        'resolve' => fn ($root, array $args, $context, ResolveInfo $info) => Post::find($args['id']),
    ],
]);

return new Schema($query, $mutation /* optional */);
```

* **Scalars:** `Int` (32-bit), `Float`, `String`, `Boolean`, `ID`; custom ones with `Types::scalar('Date', $serialize, $parseValue)`.
* **Wrappers:** `Types::nonNull()`, `Types::listOf()`. **Enums:** `new EnumType('Role', ['ADMIN' => 'admin'])`.
  **Inputs:** `new InputObjectType('PostInput', ['title' => Types::nonNull(Types::string()), 'pages' => ['type' => Types::int(), 'default' => 1]])`.
* Types that refer to each other use closures for their fields (`new ObjectType('A', fn () => [...])`) and `use (&$b)`.
* A field without `resolve` reads the same-named array key / public property from its parent value. **Only fields you declare
  are reachable**, so `email`, `password`, … on a model stay private unless you add them.
* A resolver receives `($parentValue, array $args, $context, ResolveInfo $info)`. `$context` is the PSR-7 request, so
  `$context->getAttribute('auth.id')` is the authenticated user. `$info->subfields()` lists the fields the client selected,
  which is how you eager-load (`->with('author')`) and avoid N+1 queries.
* Register the schema in `config/graphql.php`: `'schema' => App\GraphQL\AppSchema::class` (a class with `public static function build(): Schema`).
  Route it: `$router->match(['GET', 'POST'], '/graphql', [GraphQLController::class, 'handle'])`.

## Errors

* Throw `Naluz\GraphQL\GraphQLError('message', ['code' => 'FORBIDDEN'])` from a resolver to send a message to the client.
* `ValidationException` (from `Validator::make(...)->validate()`) is reported as `VALIDATION_FAILED` with the per-field messages under `extensions.validation`.
* `ModelNotFoundException` → `NOT_FOUND`; other `HttpException`s below 500 keep their message and status.
* **Anything else is masked** as `Internal server error.` so database errors, paths and passwords never reach clients. The real
  exception is written to the application log. Set `APP_DEBUG=true` to see messages while developing.
* A `null` in a non-null (`!`) position makes the nearest nullable parent `null`, as the spec requires.

## Security limits (`config/graphql.php`)

| Setting | Default | Stops |
|---|---|---|
| `max_depth` | 10 | deeply nested selections |
| `max_nodes` | 500 | alias / fragment amplification (counted after fragments are expanded, so exponential fragment chains are cut off) |
| `max_query_length` | 20000 bytes | oversized documents (also caps the request body) |
| parser limits | 64 levels of nesting, 20000 tokens | stack / memory exhaustion |
| `introspection` | on only when `APP_DEBUG` is on | schema discovery in production (`GRAPHQL_INTROSPECTION=true` turns it on) |

Also: the document is validated against the schema before any resolver runs; variables are type-checked and unknown input
fields are rejected (so a client cannot add `user_id` to an input that does not declare it); mutations over GET are refused;
responses are `Cache-Control: no-store`. **Clamp list sizes in your own resolvers** (`first` ≤ 50 in the sample): the node
limit counts the fields in the query, not the rows a resolver returns. Put authorization in resolvers (see `deletePost`).

## Not supported (yet)

Subscriptions, interfaces and unions, `@deprecated`/custom directives in queries, SDL schemas, query batching, persisted queries
and automatic DataLoader batching (use `$info->subfields()` to eager-load).

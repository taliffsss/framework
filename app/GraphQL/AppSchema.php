<?php

declare(strict_types=1);

namespace App\GraphQL;

use App\Models\Post;
use App\Models\User;
use Naluz\GraphQL\GraphQLError;
use Naluz\GraphQL\ResolveInfo;
use Naluz\GraphQL\Schema;
use Naluz\GraphQL\Type\InputObjectType;
use Naluz\GraphQL\Type\ObjectType;
use Naluz\GraphQL\Types;
use Naluz\Validation\Validator;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Sample GraphQL schema over the Post / User models (served at POST /api/graphql, see config/graphql.php).
 *
 *     { posts(first: 5) { id title author { name } } }
 *     mutation { createPost(input: {title: "Hi", body: "…"}) { id } }     # needs a bearer token
 */
final class AppSchema
{
    public static function build(): Schema
    {
        // The two types refer to each other, so their fields are closures that run after both exist.
        $user = null;
        $post = null;
        $user = new ObjectType('User', function () use (&$post): array {
            return [
                'id' => Types::nonNull(Types::id()),
                'name' => Types::nonNull(Types::string()),
                // Only fields listed here are reachable: email, password, … stay private unless you add them.
                'posts' => [
                    'type' => Types::nonNull(Types::listOf(Types::nonNull($post))),
                    'resolve' => fn (User $u) => $u->posts()->where('published', true)->get(),
                ],
            ];
        }, 'A person who writes posts.');
        $post = new ObjectType('Post', function () use (&$user): array {
            return [
                'id' => Types::nonNull(Types::id()),
                'title' => Types::nonNull(Types::string()),
                'body' => Types::nonNull(Types::string()),
                'published' => Types::nonNull(Types::boolean()),
                'createdAt' => ['type' => Types::string(), 'resolve' => fn (Post $p) => $p->created_at],
                'author' => ['type' => $user, 'resolve' => fn (Post $p) => $p->author],
            ];
        }, 'A blog post.');

        $postInput = new InputObjectType('PostInput', [
            'title' => ['type' => Types::nonNull(Types::string()), 'description' => 'Up to 200 characters.'],
            'body' => Types::nonNull(Types::string()),
            'published' => ['type' => Types::boolean(), 'default' => false],
        ]);

        $query = new ObjectType('Query', [
            'posts' => [
                'type' => Types::nonNull(Types::listOf(Types::nonNull($post))),
                'description' => 'Published posts, newest first.',
                'args' => [
                    'first' => ['type' => Types::int(), 'default' => 10, 'description' => 'How many posts (1-50).'],
                    'page' => ['type' => Types::int(), 'default' => 1],
                ],
                'resolve' => function ($root, array $args, $context, ResolveInfo $info) {
                    $first = max(1, min(50, (int) $args['first']));      // never let a client ask for "everything"
                    $query = Post::published()->latest();
                    if (in_array('author', $info->subfields(), true)) {
                        $query->with('author');                          // one extra query, not one per post
                    }
                    return $query->paginate($first, max(1, (int) $args['page']))->items;
                },
            ],
            'post' => [
                'type' => $post,
                'args' => ['id' => Types::nonNull(Types::id())],
                'resolve' => fn ($root, array $args, $context, ResolveInfo $info) => Post::published()->with('author')->find($args['id']),
            ],
        ]);

        $mutation = new ObjectType('Mutation', [
            'createPost' => [
                'type' => Types::nonNull($post),
                'args' => ['input' => Types::nonNull($postInput)],
                'resolve' => function ($root, array $args, ServerRequestInterface $request) {
                    $userId = self::authenticatedUserId($request);
                    $data = Validator::make($args['input'], [
                        'title' => 'required|string|max:200',
                        'body' => 'required|string',
                        'published' => 'boolean',
                    ])->validate();              // a ValidationException is reported to the client with the field messages
                    return Post::create($data + ['user_id' => $userId]);   // the author is the caller, never a client-supplied id
                },
            ],
            'deletePost' => [
                'type' => Types::nonNull(Types::boolean()),
                'args' => ['id' => Types::nonNull(Types::id())],
                'resolve' => function ($root, array $args, ServerRequestInterface $request) {
                    $userId = self::authenticatedUserId($request);
                    $post = Post::findOrFail($args['id']);
                    if ((int) $post->user_id !== $userId) {
                        throw new GraphQLError('You can only delete your own posts.', ['code' => 'FORBIDDEN']);
                    }
                    return $post->delete();
                },
            ],
        ]);

        return new Schema($query, $mutation);
    }

    private static function authenticatedUserId(ServerRequestInterface $request): int
    {
        $id = $request->getAttribute('auth.id');
        if ($id === null) {
            throw new GraphQLError('Authentication required: send an "Authorization: Bearer <token>" header.', ['code' => 'UNAUTHENTICATED']);
        }
        return (int) $id;
    }
}

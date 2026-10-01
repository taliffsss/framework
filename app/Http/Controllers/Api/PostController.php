<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Post;
use Naluz\Database\DatabaseManager;
use Naluz\Database\Paginator;
use Naluz\Http\Request;
use Naluz\Http\Response;
use Naluz\Validation\Validator;
use Psr\Http\Message\ServerRequestInterface;

/** Example REST resource: GET/POST /api/posts, GET/PUT/PATCH/DELETE /api/posts/{post}. */
final class PostController
{
    public function __construct(private readonly DatabaseManager $db)
    {
    }

    public function index(ServerRequestInterface $request): Paginator
    {
        $q = $request->getQueryParams();
        return Post::published()->with('author')->latest()
            ->paginate((int) ($q['per_page'] ?? 15), (int) ($q['page'] ?? 1));
    }

    public function show(int $post): Post
    {
        return Post::with('author')->findOrFail($post);
    }

    public function store(ServerRequestInterface $request): Response
    {
        $data = Validator::make(Request::input($request), [
            'user_id' => 'required|integer|exists:users,id',
            'title' => 'required|string|max:200',
            'body' => 'required|string',
            'published' => 'boolean',
        ], db: $this->db->connection())->validate();

        return Response::json(Post::create($data), 201);
    }

    public function update(ServerRequestInterface $request, int $post): Post
    {
        $model = Post::findOrFail($post);
        $data = Validator::make(Request::input($request), [
            'title' => 'string|max:200',
            'body' => 'string',
            'published' => 'boolean',
        ])->validate();
        $model->update($data);
        return $model;
    }

    public function destroy(int $post): Response
    {
        Post::findOrFail($post)->delete();
        return Response::noContent();
    }
}

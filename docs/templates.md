# Templates

NaluzPHP has its own template engine. Files named `*.naluz.php` are **compiled to plain PHP once** and the compiled
file is cached in `storage/cache/views` (so rendering runs at native PHP speed). Every `{{ }}` echo is
**HTML-escaped automatically** — the XSS-by-forgetting mistake of plain PHP views can't happen unless you type `{!! !!}`.

Plain `*.php` views (using `$this->e()`) keep working; `*.naluz.php` wins when both exist.

## Syntax

```
{{ $user->name }}              escaped output   (htmlspecialchars, UTF-8, quotes encoded)
{!! $html !!}                  raw output — only for HTML you generated and trust
{{ $a ?? 'default' }}          any PHP expression
{{-- not rendered --}}         comment
@{{ literal }}   @@if          print "{{ literal }}" / "@if" as-is
```

### Control structures

```
@if ($n > 5) … @elseif ($n > 1) … @else … @endif
@unless ($ok) … @endunless          @isset($x) … @endisset          @empty($list) … @endempty
@foreach ($posts as $post) … @endforeach        @for (…) … @endfor        @while (…) … @endwhile
@forelse ($posts as $post) … @empty no posts @endforelse
@break  @continue  @switch/@case/@default/@endswitch
@auth … @endauth       @guest … @endguest
@php $x = compute(); @endphp
```

Directives may be followed immediately by `(` — nested parentheses are fine: `@if (in_array($x, [1, (2)]))`.
Things that merely look like directives (`@media` in CSS, `dev@example.com`) are left alone.

### Layouts

```
{{-- resources/views/layouts/app.naluz.php --}}
<title>@yield('title', 'My site')</title>
<body>@yield('content') @stack('scripts')</body>

{{-- resources/views/posts/show.naluz.php --}}
@extends('layouts/app')
@section('title', $post->title)            {{-- inline sections are escaped for you --}}
@section('content')
    <h1>{{ $post->title }}</h1>
    @include('partials/comments', ['comments' => $post->comments])
@endsection
@push('scripts') <script src="/js/post.js"></script> @endpush
```

`@include` passes the current variables plus any you give it.

### Forms and data

```
<form method="POST" action="/posts/1">
    @csrf                       {{-- hidden _token input --}}
    @method('PUT')              {{-- hidden _method (PUT, PATCH or DELETE only) --}}
</form>
<script>const data = @json($payload);</script>   {{-- safe in HTML, attributes and <script> --}}
```

## Caching & deployment

- **Development / `APP_ENV` ≠ production:** templates are recompiled when their *content* changes (content-hash keyed,
  so even a same-second edit is picked up) and superseded compiled files are deleted.
- **Production:** compiled files are trusted and never re-checked (no `stat`/hash per request). Run
  `php naluz view:clear` as part of each deploy.

Compiled files are written atomically (temp file + rename), so concurrent requests never read a half-written template.

## Security notes

- Template source is trusted code (like any PHP file) — never build templates from user input.
- `{!! !!}` and `@yield`/`@stack` output are unescaped: sections contain already-rendered template output.
- View names are validated (`[A-Za-z0-9_./-]`, no `..`), so `view($request->input('page'))` cannot traverse the filesystem.

## Extending

`Naluz\View\Compiler` is a small class (≈100 lines): add directives to its tables, or subclass `View\Factory`
and bind your own in a service provider.

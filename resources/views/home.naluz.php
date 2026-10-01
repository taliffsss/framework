@extends('layouts/app', ['title' => $name])

@section('content')
    <h1>Welcome to {{ $name }}</h1>
    <p>Your application is running. Edit <code>routes/web.php</code> to get started, or try
       <a href="/api/ping"><code>GET /api/ping</code></a>.</p>
@endsection

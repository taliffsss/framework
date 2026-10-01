<?php $this->extend('layouts/app', ['title' => $name]); ?>
<?php $this->section('content'); ?>
    <h1>Welcome to <?= $this->e($name) ?></h1>
    <p>Your application is running. Edit <code>routes/web.php</code> to get started, or try
       <a href="/api/ping"><code>GET /api/ping</code></a>.</p>
<?php $this->endSection(); ?>

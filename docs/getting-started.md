# Getting started

## Install

**As a starter for a new project** (from a clone of this repository):

```bash
php naluz new my-app            # copies the starter, writes .env with fresh APP_KEY/JWT_SECRET, runs composer install
cd my-app && php naluz migrate && php naluz serve
```

Options: `--name=vendor/package` (sets composer name), `--no-install`, `--dir=/parent/dir`. Or manually:

```bash
git clone https://github.com/taliffsss/framework.git my-app && cd my-app
composer install
cp .env.example .env
php naluz key:generate --jwt
touch storage/database.sqlite     # or configure MySQL / PostgreSQL, see below
php naluz migrate
php naluz serve
```

Open <http://127.0.0.1:8000> and `GET /api/ping`.

## Configuration

Settings live in `config/*.php` and read environment variables with `env()`. Access them anywhere with
`config('app.name')` or by injecting `Naluz\Config\Repository`.

| `.env` key | Purpose |
|---|---|
| `APP_ENV`, `APP_DEBUG` | `APP_DEBUG=true` shows exception details. **Never enable in production.** Defaults to `false`. |
| `APP_KEY` | 32-byte key for the `encrypted` cast and `Encrypter`. `php naluz key:generate` |
| `JWT_SECRET` | ≥32 chars, for token auth |
| `DB_CONNECTION` | `sqlite` (default), `mysql`, `pgsql` |
| `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD` | connection details |
| `SESSION_SECURE` | force the `Secure` cookie flag (otherwise automatic on HTTPS) |

### MySQL / PostgreSQL

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=myapp
DB_USERNAME=myapp
DB_PASSWORD=secret
```

Add more connections in `config/database.php` and use them with
`$manager->connection('reporting')` or `protected ?string $connection = 'reporting';` on a model.

## Web server

Point the document root at `public/`.

- **Apache**: `public/.htaccess` is included (needs `mod_rewrite`).
- **nginx**:
  ```nginx
  root /var/www/my-app/public;
  location / { try_files $uri /index.php$is_args$args; }
  location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root/index.php; fastcgi_pass unix:/run/php/php-fpm.sock; }
  ```
- **Dev**: `php naluz serve`.

Production checklist: `APP_DEBUG=false`, HTTPS (HSTS and `Secure` cookies then switch on automatically),
`composer install --no-dev -o`, `storage/` writable but not web-accessible, strong `APP_KEY` / `JWT_SECRET`.

## Your first feature

```bash
php naluz make:migration create_articles_table
php naluz make:model Article
php naluz make:controller ArticleController
```

```php
// database/migrations/…_create_articles_table.php
$schema->create('articles', function ($t) {
    $t->id();
    $t->string('title');
    $t->text('body');
    $t->timestamps();
});

// app/Models/Article.php
protected array $fillable = ['title', 'body'];

// routes/api.php
$router->apiResource('articles', ArticleController::class);

// app/Http/Controllers/ArticleController.php
final class ArticleController
{
    public function index(): array { return Article::latest()->get()->toArray(); }

    public function store(ServerRequestInterface $request): Response
    {
        $data = Validator::make(Request::input($request), ['title' => 'required|max:200', 'body' => 'required'])->validate();
        return Response::json(Article::create($data), 201);
    }

    public function show(int $article): Article { return Article::findOrFail($article); }
}
```

```bash
php naluz migrate
php naluz route:list
```

## Service providers

Register your own bindings by adding a class extending `Naluz\Foundation\ServiceProvider` to `config/app.php → providers`:

```php
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Mailer::class, fn ($c) => new SmtpMailer(config('mail.host')));
    }
}
```

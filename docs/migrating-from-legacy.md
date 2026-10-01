# Migrating from the legacy code

Earlier revisions of this repository (`Mark\` namespace, `system/`, `app/controllers`, `My_Controller`, a
CodeIgniter-style loader and an inventory/requisition application) were replaced by NaluzPHP. The old code is still in
git history (`git show 83d45af:path/to/file`).

Why it was replaced rather than patched: the old query layer concatenated SQL strings
(`"SELECT $fields FROM $table WHERE $where"`), had no CSRF protection, relied on global state, and had no tests or PSR
alignment. The old inventory app's controllers, views and assets were application code, not framework code, and are not
carried over.

| Legacy | NaluzPHP |
|---|---|
| `Mark\core\Database` | `Naluz\Database\Connection` + query builder |
| `My_Model` / `Model` | `Naluz\Database\Orm\Model` |
| `Routes` / `app/config/Router.php` | `routes/web.php`, `routes/api.php` |
| `Session`, `Cookie`, `Token` | `Session\Store`, `StartSession`, `Security\Csrf` |
| `Encryption`, `Hash` | `Security\Encrypter`, `Security\Hasher` |
| `Validation` library | `Validation\Validator` |
| `MyError` | `Foundation\ExceptionHandler` |

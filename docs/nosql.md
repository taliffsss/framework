# NoSQL (document stores)

NaluzPHP ships a small document-store layer next to the SQL query builder. Collections hold arrays; filters and updates
use MongoDB-style operators.

| Driver | Needs | Use for |
|---|---|---|
| `file` (default) | nothing | local dev, small apps; one JSON file per collection, `flock` + atomic rename |
| `memory` | nothing | tests |
| `mongodb` | `composer require mongodb/mongodb` (+ ext-mongodb) | production |

```env
NOSQL_CONNECTION=file          # file | memory | mongodb
NOSQL_MONGODB_URI=mongodb://127.0.0.1:27017
NOSQL_MONGODB_DATABASE=naluz
```

```php
$users = nosql()->collection('users');            // or nosql('mongodb')->collection(...)
$users->createIndex(['email' => 1], ['unique' => true]);
$users->insertOne(['name' => 'Ann', 'age' => 30, 'email' => 'ann@x.io']);

$adults = $users->query()
    ->where('age', '>=', 18)->orWhere('role', 'admin')
    ->orderBy('name')->limit(20)->get();          // collection of arrays

$users->query()->where('name', 'Ann')->increment('age');
$page = $users->query()->orderBy('age')->paginate(15, 1);
```

Raw filters work too: `$users->find(['age' => ['$gt' => 18]])`, `updateOne`, `updateMany`, `replaceOne`, `deleteOne`,
`deleteMany`, `distinct`, `count`. Supported update operators: `$set $unset $inc $mul $min $max $push $pull $addToSet
$rename $setOnInsert`.

## Safety

* The fluent `Query` builder wraps every value in `$eq`/`$in`, so user input such as `['$ne' => null]` stays a literal
  (no operator injection). Field names and operators are allow-listed.
* `like` patterns are escaped and anchored; regex length and options are bounded.
* The MongoDB adapter refuses `$where`, `$function` and `$accumulator` (server-side JavaScript) and rejects `$`-prefixed
  or dotted keys in stored documents.
* The file driver refuses corrupt files instead of overwriting them, and collection names cannot traverse paths.

# SQL Server, read/write splitting

See [database.md](database.md). Summary:

```env
DB_CONNECTION=sqlsrv           # sqlite | mysql | pgsql | sqlsrv
DB_WRITE_HOST=10.0.0.1
DB_READ_HOST=10.0.0.2,10.0.0.3 # comma list of replicas
DB_STICKY=true                 # reads after a write in the same request use the primary
DB_READ_FALLBACK=true          # use the primary if every replica is down
DB_READ_STRATEGY=random        # random | ordered
```

Reads and writes use separate PDO sessions, so they never share state. Replicas are opened read-only. Transactions,
`SELECT … FOR UPDATE`, `INSERT … RETURNING`, and reads issued after a write in the same request go to the primary.
Force it with `DB::table('x')->useWritePdo()`.

# File storage & uploads

```php
$disk = storage();            // default disk (config/filesystems.php)  ·  storage('public')
$disk->put('reports/q1.csv', $csv);        $disk->get(…);  exists  delete  copy  move  size  mimeType  lastModified
$disk->files('reports');                   $disk->directories();   makeDirectory  deleteDirectory
$disk->url('avatars/a.png');               // public disks only → "/storage/avatars/a.png"
```

Disks: `local` (`storage/app`, private) and `public` (`public/storage`, URL `/storage`). Implement
`Naluz\Storage\Filesystem` and register with `StorageManager::extend('s3', $disk)` for cloud storage (no S3 driver is bundled).

## Path safety

Every path is treated as hostile: `..`, absolute paths, backslashes, NUL bytes and drive letters throw `StorageException`,
and the nearest existing ancestor is re-resolved with `realpath()`, so a **symlink inside the root can't lead out of it**.
Writes are atomic (temp file + rename).

## Uploads

```php
use Naluz\Storage\Uploads;

public function avatar(ServerRequestInterface $request): array
{
    $file = $request->getUploadedFiles()['avatar'];
    $path = Uploads::store($file, storage('public'), 'avatars', Uploads::IMAGES, maxBytes: 2_000_000);
    return ['url' => storage('public')->url($path)];       // 422 automatically if rejected
}
```

`Uploads::store()` never trusts the client:

- the type is detected from the **file's bytes** (`finfo`), not the file name or the `Content-Type` header;
- you must pass an allow-list (`mime => extension`; `IMAGES` and `DOCUMENTS` presets) — there is no "allow everything";
  SVG/HTML are not in the presets because they can carry scripts;
- the stored extension comes from your list and the name is random (`avatars/9f2c…e1.png`), so uploads can't overwrite
  files, smuggle `.php`, or inject path segments;
- size and PHP upload errors are checked.

Still your job: serve uploaded files from a domain/path that never executes PHP, and scan documents if you accept them
from untrusted users.

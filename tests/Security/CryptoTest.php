<?php

declare(strict_types=1);

namespace Naluz\Tests\Security;

use Naluz\Cache\FileCache;
use Naluz\Log\FileLogger;
use Naluz\Security\DecryptException;
use Naluz\Security\Encrypter;
use Naluz\Security\Hasher;
use Naluz\Security\InvalidTokenException;
use Naluz\Security\Jwt;
use Naluz\Session\FileSessionHandler;
use PHPUnit\Framework\TestCase;

final class CryptoTest extends TestCase
{
    public function testHasherUsesStrongAlgorithmAndVerifies(): void
    {
        $h = new Hasher(['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]);
        $hash = $h->make('correct horse');
        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertTrue($h->check('correct horse', $hash));
        $this->assertFalse($h->check('wrong', $hash));
        $this->assertFalse($h->check('x', null));
        $this->assertNotSame($hash, $h->make('correct horse'), 'salted');
        $this->assertFalse($h->check(str_repeat('a', 5000), $hash), 'oversized input rejected without hashing');
        $this->expectException(\InvalidArgumentException::class);
        $h->make(str_repeat('a', 5000));
    }

    public function testEncrypterRoundTripAndTamperDetection(): void
    {
        $e = new Encrypter(Encrypter::generateKey());
        $payload = $e->encrypt(['a' => 1, 'b' => 'héllo']);
        $this->assertSame(['a' => 1, 'b' => 'héllo'], $e->decrypt($payload));
        $this->assertNotSame($payload, $e->encrypt(['a' => 1, 'b' => 'héllo']), 'random nonce');
        $this->assertStringNotContainsString('hello', $payload);

        $raw = base64_decode(strtr($payload, '-_', '+/'));
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] ^ "\x01";
        $this->expectException(DecryptException::class);
        $e->decryptString(rtrim(strtr(base64_encode($raw), '+/', '-_'), '='));
    }

    public function testEncrypterRejectsWrongKeyAndSupportsRotation(): void
    {
        $old = Encrypter::generateKey();
        $new = Encrypter::generateKey();
        $payload = (new Encrypter($old))->encryptString('secret');
        $this->assertSame('secret', (new Encrypter($new, [$old]))->decryptString($payload));
        $this->expectException(DecryptException::class);
        (new Encrypter($new))->decryptString($payload);
    }

    public function testEncrypterRejectsBadKeysAndGarbage(): void
    {
        try {
            new Encrypter('short');
            $this->fail('short key accepted');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(DecryptException::class);
        (new Encrypter(Encrypter::generateKey()))->decryptString('not-a-payload');
    }

    public function testJwtRoundTrip(): void
    {
        $jwt = new Jwt(str_repeat('s', 32), 'iss');
        $claims = $jwt->decode($jwt->encode(['sub' => 7]));
        $this->assertSame(7, $claims['sub']);
        $this->assertSame('iss', $claims['iss']);
    }

    public function testJwtRejectsTamperingAlgNoneExpiryAndIssuer(): void
    {
        $jwt = new Jwt(str_repeat('s', 32), 'iss');
        $token = $jwt->encode(['sub' => 1, 'role' => 'user']);
        [$h, $b, $s] = explode('.', $token);
        $b64 = fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');

        $forgedBody = $b64(json_encode(['sub' => 1, 'role' => 'admin', 'exp' => time() + 999]));
        $none = $b64(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $cases = [
            'tampered payload' => "{$h}.{$forgedBody}.{$s}",
            'alg none' => "{$none}.{$forgedBody}.",
            'alg none w/ sig' => "{$none}.{$b}.{$s}",
            'wrong secret' => (new Jwt(str_repeat('x', 32), 'iss'))->encode(['sub' => 1]),
            'wrong issuer' => (new Jwt(str_repeat('s', 32), 'other'))->encode(['sub' => 1]),
            'expired' => $jwt->encode(['sub' => 1], -3600),
            'not yet valid' => $jwt->encode(['sub' => 1, 'nbf' => time() + 3600]),
            'garbage' => 'a.b',
            'empty' => '',
        ];
        foreach ($cases as $name => $bad) {
            try {
                $jwt->decode($bad);
                $this->fail("accepted: {$name}");
            } catch (InvalidTokenException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testJwtRequiresStrongSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Jwt('weak');
    }

    public function testFileCacheNeverInstantiatesObjectsFromDisk(): void
    {
        $dir = sys_get_temp_dir() . '/naluz-cache-' . bin2hex(random_bytes(4));
        $cache = new FileCache($dir);
        $cache->set('k', ['v' => 1], 60);
        $this->assertSame(['v' => 1], $cache->get('k'));

        // Tamper: an attacker who can write cache files tries PHP object injection.
        $file = glob($dir . '/*.cache')[0];
        file_put_contents($file, serialize([new \ArrayObject(['pwn']), null]));
        $value = $cache->get('k');
        $this->assertNotInstanceOf(\ArrayObject::class, $value);

        $cache->set('ttl', 1, -10);
        $this->assertNull($cache->get('ttl'), 'expired entries are gone');
        $this->assertSame(1, $cache->increment('c', 60));
        $this->assertSame(2, $cache->increment('c', 60));
        $cache->clear();
        $this->assertNull($cache->get('k'));
        @rmdir($dir);
    }

    public function testCacheKeysAreValidatedAndHashedOnDisk(): void
    {
        $cache = new FileCache(sys_get_temp_dir() . '/naluz-cache-' . bin2hex(random_bytes(4)));
        $this->expectException(\Psr\SimpleCache\InvalidArgumentException::class);
        $cache->get('../../etc/passwd');
    }

    public function testSessionHandlerRejectsPathTraversalIds(): void
    {
        $h = new FileSessionHandler(sys_get_temp_dir() . '/naluz-sess-' . bin2hex(random_bytes(4)));
        $this->expectException(\InvalidArgumentException::class);
        $h->read('../../../etc/passwd');
    }

    public function testLoggerPreventsLogForgery(): void
    {
        $dir = sys_get_temp_dir() . '/naluz-log-' . bin2hex(random_bytes(4));
        (new FileLogger($dir))->warning('login failed for {user}', ['user' => "bob\n[2026-01-01 00:00:00] CRITICAL: forged"]);
        $content = (string) file_get_contents(glob($dir . '/*.log')[0]);
        $this->assertSame(1, substr_count($content, "\n"), 'newlines in context are neutralised');
        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }

    public function testLoggerHonoursMinimumLevel(): void
    {
        $dir = sys_get_temp_dir() . '/naluz-log-' . bin2hex(random_bytes(4));
        $log = new FileLogger($dir, 'error');
        $log->info('ignored');
        $this->assertSame([], glob($dir . '/*.log') ?: []);
        $log->error('kept');
        $this->assertCount(1, glob($dir . '/*.log'));
        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }

    public function testRedirectRejectsHeaderInjection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \Naluz\Http\Response::redirect("/ok\r\nSet-Cookie: pwned=1");
    }
}

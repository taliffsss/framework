# HTTP client (Guzzle, PSR-18)

Guzzle is the framework's PSR-18 client. It is bound to `Psr\Http\Client\ClientInterface`, so any library that wants a
PSR-18 client gets it, and you can swap it for another in a service provider.

```php
$res = http()->get('https://api.example.com/users', ['page' => 2], ['Authorization' => 'Bearer ' . $token]);
$res->getStatusCode();                       // 200
$users = Naluz\Http\Client\Http::json($res);  // decoded JSON (throws on invalid JSON)

http()->post('https://api.example.com/users', ['name' => 'Ann']);   // arrays are sent as JSON
http()->put(...) / patch(...) / delete(...)
http()->send('HEAD', $url);                  // anything else
```

Inject `Naluz\Http\Client\Http` (or the bare PSR-18 `ClientInterface`) into your classes instead of calling `http()`.

## Behaviour

- **HTTP error statuses are responses**, never exceptions (PSR-18): check `getStatusCode()`.
  Only transport failures throw (`Psr\Http\Client\ClientExceptionInterface`: DNS, connection, timeout, blocked request…).
- Defaults: 10 s timeout, 5 s connect timeout, TLS verification **on**, `User-Agent: <APP_NAME>`. Configure in `config/http.php`.
- **Redirects:** PSR-18 clients don't follow redirects, so `Http` does it — at most `http.max_redirects` (3) hops, relative
  `Location`s resolved, 301/302 on POST and 303 become `GET`, 307/308 replay the request, and **`Authorization`, `Cookie` and
  `Proxy-Authorization` are dropped when the redirect changes origin** (host, port or scheme, including https → http).
  Set `max_redirects` to `0` to handle redirects yourself.

## SSRF protection (on by default)

Server-side request forgery is what happens when "fetch this URL for me" lets an attacker reach your internal network or the
cloud metadata service (`169.254.169.254`). The client refuses, **on every hop including redirects**:

- non-HTTP(S) schemes (`file://`, `gopher://`…);
- loopback, private (RFC 1918, IPv6 ULA), link-local, carrier-grade NAT (100.64/10), documentation/benchmark and
  reserved ranges, multicast, and IPv4-mapped IPv6 forms of those;
- hostnames that *resolve* to any of the above.

```php
// trusted internal service calls only:
// config/http.php  →  'allow_private_networks' => true
```

Limitation (documented in `PrivateNetworkGuard`): DNS is resolved once for the check and again by the transport, so a
hostile DNS server could answer differently the second time (DNS rebinding). If you fetch **user-supplied URLs**, also
restrict egress at the network/firewall level.

## Testing

Pass a Guzzle `MockHandler`:

```php
$stack = GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler([new GuzzleHttp\Psr7\Response(200, [], '{"ok":true}')]));
$f = new Nyholm\Psr7\Factory\Psr17Factory();
$http = new Naluz\Http\Client\Http(Naluz\Http\Client\HttpClientFactory::make(['handler' => $stack]), $f, $f);
```

(`tests/Unit/HttpClientTest.php` covers redirects, credential stripping and every blocked address class this way.)

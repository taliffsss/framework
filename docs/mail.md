# Mail

```php
$mailer = app(Naluz\Mail\Mailer::class);

$message = $mailer->message()                       // From pre-filled from config/mail.php
    ->to($user->email, $user->name)
    ->subject('Welcome!')
    ->text('Hi ' . $user->name);                    // or ->html('<p>…</p>')

$mailer->view($message, 'emails/welcome', ['user' => $user]);   // render an (auto-escaping) template as the HTML body
$message->attach(storage_path('invoice.pdf'), 'invoice.pdf', 'application/pdf');

$mailer->send($message);                            // now
$mailer->queue($message, 'mail');                   // in the background via the queue (5 attempts, backoff)
```

If you only set `html`, a plain-text alternative is generated automatically (multipart/alternative).

## Transports (`MAIL_MAILER`)

| | |
|---|---|
| `log` (default) | writes the message to `storage/logs` instead of sending. Safe for development |
| `smtp` | `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION` = `tls` (STARTTLS, 587) / `ssl` (465) / `none`, `MAIL_USERNAME`, `MAIL_PASSWORD`. Server certificates are verified. AUTH PLAIN and LOGIN |
| `array` | keeps messages in `ArrayTransport::$sent` for assertions |

Implement `Naluz\Mail\Transport` (one method) to add an HTTP API provider and bind it in a service provider.

## Security

- **Header injection is impossible:** addresses, names, subjects, header values and attachment names containing CR/LF/NUL
  (or malformed addresses) throw `MailException` before anything is sent. Non-ASCII subjects/names are RFC 2047 encoded.
- `Bcc` recipients are used for the SMTP envelope but never written into headers.
- Bodies are dot-stuffed; templates auto-escape; line endings are normalised before quoted-printable encoding.
- Don't use user-controlled addresses for `From`; use `Reply-To` instead.

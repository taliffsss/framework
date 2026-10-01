<?php

declare(strict_types=1);

namespace Naluz\Tests\Mail;

use Naluz\Mail\Address;
use Naluz\Mail\ArrayTransport;
use Naluz\Mail\MailException;
use Naluz\Mail\Mailer;
use Naluz\Mail\Message;
use Naluz\Mail\SendMailJob;
use Naluz\Mail\SmtpTransport;
use Naluz\Queue\QueueManager;
use Naluz\Queue\Worker;
use Naluz\Tests\TestCase;

final class MailTest extends TestCase
{
    private function msg(): Message
    {
        return (new Message())->from('app@example.com', 'The App')->to('bob@example.com', 'Bob')->subject('Hi')->text('Hello Bob');
    }

    public function testMimeStructure(): void
    {
        $mime = $this->msg()->html('<p>Hello <b>Bob</b></p>')->attachData('PDFDATA', 'a.pdf', 'application/pdf')->toMime();
        $this->assertStringContainsString('From: "The App" <app@example.com>', $mime);
        $this->assertStringContainsString('To: "Bob" <bob@example.com>', $mime);
        $this->assertStringContainsString('Subject: Hi', $mime);
        $this->assertStringContainsString('Content-Type: multipart/mixed', $mime);
        $this->assertStringContainsString('Content-Type: multipart/alternative', $mime);
        $this->assertStringContainsString('Content-Type: text/html; charset=UTF-8', $mime);
        $this->assertStringContainsString('filename="a.pdf"', $mime);
        $this->assertStringContainsString(base64_encode('PDFDATA'), $mime);
        $this->assertStringContainsString("\r\n\r\n", $mime);
        $this->assertMatchesRegularExpression('/^Message-ID: <[0-9a-f]+@example\.com>/m', $mime);
    }

    public function testNonAsciiSubjectsAndNamesAreEncoded(): void
    {
        $mime = $this->msg()->subject('Héllo wörld ✓')->to('c@example.com', 'José Ñandú')->toMime();
        $this->assertStringContainsString('Subject: =?UTF-8?B?' . base64_encode('Héllo wörld ✓') . '?=', $mime);
        $this->assertStringContainsString('=?UTF-8?B?' . base64_encode('José Ñandú') . '?= <c@example.com>', $mime);
    }

    public function testPlainTextFallbackIsDerivedFromHtml(): void
    {
        $mime = (new Message())->from('a@example.com')->to('b@example.com')->subject('x')->html('<h1>Title</h1><p>Body &amp; more</p>')->toMime();
        $this->assertStringContainsString('Title', $mime);
        $this->assertStringContainsString('Body & more', quoted_printable_decode($mime));
    }

    public function testBccIsNeverWrittenToHeaders(): void
    {
        $m = $this->msg()->bcc('secret@example.com');
        $this->assertStringNotContainsString('secret@example.com', $m->toMime());
        $this->assertCount(2, $m->recipients());
    }

    // ---------------------------------------------------------------- header injection

    public function testHeaderInjectionIsImpossible(): void
    {
        $attacks = [
            fn () => $this->msg()->subject("Hi\r\nBcc: victim@evil.test"),
            fn () => $this->msg()->subject("Hi\nBcc: victim@evil.test"),
            fn () => $this->msg()->to("bob@example.com\r\nBcc: victim@evil.test"),
            fn () => new Address("a@example.com>\r\nRCPT TO:<x@evil.test"),
            fn () => new Address('a@example.com', "Bob\r\nBcc: x@evil.test"),
            fn () => $this->msg()->header('X-Test', "v\r\nBcc: x@evil.test"),
            fn () => $this->msg()->header("X-Bad\r\nName", 'v'),
            fn () => $this->msg()->attachData('x', "f\r\n.pdf"),
            fn () => new Address('"quoted"@example.com'),
            fn () => new Address('not-an-email'),
            fn () => new Address(''),
        ];
        foreach ($attacks as $i => $attack) {
            try {
                $attack();
                $this->fail("attack #{$i} was accepted");
            } catch (MailException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testMessagesNeedFromAndRecipient(): void
    {
        $this->expectException(MailException::class);
        (new Message())->to('a@example.com')->toMime();
    }

    // ---------------------------------------------------------------- mailer, views, queue

    public function testMailerWithArrayTransportAndViews(): void
    {
        $this->app->instance(\Naluz\Mail\Transport::class, $t = new ArrayTransport());
        $this->app->singleton(Mailer::class, fn ($c) => new Mailer($t, $c->make(\Naluz\Config\Repository::class), $c->make(\Naluz\View\Factory::class), $c->make(QueueManager::class)));
        $mailer = $this->app->make(Mailer::class);

        $m = $mailer->message()->to('bob@example.com')->subject('Welcome');
        $mailer->view($m, 'home', ['name' => '<script>x</script>']);
        $mailer->send($m);

        $this->assertCount(1, $t->sent);
        $this->assertSame('hello@example.com', $t->sent[0]->getFrom()->email, 'default From from config/mail.php');
        $this->assertStringNotContainsString('<script>x</script>', $t->sent[0]->getHtml(), 'templates auto-escape in e-mail too');
    }

    public function testQueuedMailIsSentByTheWorker(): void
    {
        $this->app->instance(\Naluz\Mail\Transport::class, $t = new ArrayTransport());
        $this->app->singleton(Mailer::class, fn ($c) => new Mailer($t, $c->make(\Naluz\Config\Repository::class), $c->make(\Naluz\View\Factory::class), $c->make(QueueManager::class)));
        $this->app->make(\Naluz\Config\Repository::class)->set('queue.default', 'database');

        $this->app->make(Mailer::class)->queue($this->msg()->attachData("bin\0data", 'x.bin'), 'mail');
        $this->assertCount(0, $t->sent);
        $this->assertSame('processed', $this->app->make(Worker::class)->runNextJob('mail'));
        $this->assertCount(1, $t->sent);
        $this->assertSame('Hi', $t->sent[0]->getSubject());
        $this->assertSame("bin\0data", $t->sent[0]->getAttachments()[0]['data']);
    }

    public function testMessageArrayRoundTrip(): void
    {
        $m = $this->msg()->cc('c@example.com')->bcc('d@example.com')->replyTo('r@example.com')->html('<p>x</p>')->header('X-Tag', 'a');
        $copy = Message::fromArray($m->toArray());
        $this->assertSame($m->toArray(), $copy->toArray());
        $this->assertInstanceOf(SendMailJob::class, new SendMailJob($m->toArray()));
    }

    // ---------------------------------------------------------------- SMTP against a scripted server

    private function smtpSend(Message $m, ?string $user = null, ?string $pass = null, string $mode = ''): string
    {
        $file = tempnam(sys_get_temp_dir(), 'smtp');
        $port = random_int(20000, 60000);
        $proc = proc_open([PHP_BINARY, dirname(__DIR__) . '/stubs/smtp_server.php', (string) $port, $file, $mode], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        $transport = new SmtpTransport('127.0.0.1', $port, 'none', $user, $pass, 3.0, function (string $h, int $p, float $t) {
            for ($i = 0; $i < 100; $i++) {
                if ($s = @stream_socket_client("tcp://{$h}:{$p}", $e, $m, $t)) {
                    return $s;
                }
                usleep(30_000);
            }
            throw new MailException('fake server did not start');
        }, 'client.test');
        try {
            $transport->send($m);
        } finally {
            usleep(100_000);
            proc_terminate($proc);
            proc_close($proc);
            $transcript = (string) file_get_contents($file);
            @unlink($file);
            $this->lastTranscript = $transcript;
        }
        return $this->lastTranscript;
    }

    private string $lastTranscript = '';

    public function testSmtpConversation(): void
    {
        $t = $this->smtpSend($this->msg()->cc('cc@example.com')->bcc('bcc@example.com')->text("line one\n.leading dot line\nend"));
        $this->assertStringContainsString('C: EHLO client.test', $t);
        $this->assertStringContainsString('C: MAIL FROM:<app@example.com>', $t);
        foreach (['bob@example.com', 'cc@example.com', 'bcc@example.com'] as $rcpt) {
            $this->assertStringContainsString("C: RCPT TO:<{$rcpt}>", $t);
        }
        $this->assertStringContainsString('C: Subject: Hi', $t);
        $this->assertStringContainsString('C: ..leading dot line', $t, 'dot-stuffed');
        $this->assertStringNotContainsString('Bcc:', $t, 'Bcc header never transmitted');
        $this->assertStringContainsString('C: QUIT', $t);
    }

    public function testSmtpAuthPlain(): void
    {
        $t = $this->smtpSend($this->msg(), 'user', 'secret');
        $this->assertStringContainsString('C: AUTH PLAIN ' . base64_encode("\0user\0secret"), $t);
        $this->assertStringContainsString('S: 235', $t);
    }

    public function testSmtpAuthFailureThrows(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('535');
        $this->smtpSend($this->msg(), 'user', 'wrong');
    }

    public function testSmtpRejectedRecipientThrows(): void
    {
        $this->expectException(MailException::class);
        $this->expectExceptionMessage('550');
        $this->smtpSend($this->msg()->to('bad@example.com'), null, null, 'reject-rcpt');
    }

    public function testUnreachableServerThrows(): void
    {
        $this->expectException(MailException::class);
        (new SmtpTransport('127.0.0.1', 1, 'none', null, null, 0.3))->send($this->msg());
    }

    public function testHeloNameCannotInjectSmtpCommands(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SmtpTransport('h', 25, 'none', null, null, 1.0, null, "evil\r\nMAIL FROM:<x@evil.test>");
    }

    public function testInvalidEncryptionModeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SmtpTransport('h', 25, 'maybe');
    }
}

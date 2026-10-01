<?php

// Tiny single-connection fake SMTP server for tests: php smtp_server.php <port> <transcript-file> [reject-rcpt]
[, $port, $file, $mode] = $argv + [3 => ''];
$server = stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $err);
$conn = stream_socket_accept($server, 10);
$log = fopen($file, 'w');
$send = function (string $line) use ($conn, $log) {
    fwrite($conn, $line . "\r\n");
    fwrite($log, "S: {$line}\n");
};
$send('220 fake.test ESMTP');
$inData = false;
while (($line = fgets($conn)) !== false) {
    $line = rtrim($line, "\r\n");
    fwrite($log, "C: {$line}\n");
    if ($inData) {
        if ($line === '.') {
            $inData = false;
            $send('250 2.0.0 queued');
        }
        continue;
    }
    $cmd = strtoupper(strtok($line, ' '));
    switch ($cmd) {
        case 'EHLO':
            fwrite($conn, "250-fake.test\r\n250-AUTH PLAIN LOGIN\r\n250 8BITMIME\r\n");
            fwrite($log, "S: 250 EHLO\n");
            break;
        case 'AUTH':
            if (str_starts_with($line, 'AUTH PLAIN ')) {
                $ok = base64_decode(substr($line, 11)) === "\0user\0secret";
                $send($ok ? '235 2.7.0 ok' : '535 5.7.8 bad credentials');
            } else {
                $send('334 VXNlcm5hbWU6');
            }
            break;
        case 'RCPT':
            $send($mode === 'reject-rcpt' && str_contains($line, 'bad@') ? '550 5.1.1 no such user' : '250 ok');
            break;
        case 'DATA':
            $inData = true;
            $send('354 go ahead');
            break;
        case 'QUIT':
            $send('221 bye');
            break 2;
        default:
            $send('250 ok');
    }
}

<?php

namespace App\Services\Backup;

use RuntimeException;

class BackupCipher
{
    private const CHUNK_BYTES = 1048576;

    private const MAGIC = 'ARVBKP01';

    public function encrypt(string $source, string $destination): void
    {
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key());
        $input = $this->open($source, 'rb');
        $output = $this->open($destination, 'wb');

        try {
            $this->write($output, self::MAGIC.$header);
            $chunk = $this->read($input, self::CHUNK_BYTES);

            do {
                $next = $this->read($input, self::CHUNK_BYTES);
                $isFinal = $next === '';
                $encrypted = sodium_crypto_secretstream_xchacha20poly1305_push(
                    $state,
                    $chunk,
                    '',
                    $isFinal
                        ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                        : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE,
                );
                $this->write($output, $encrypted);
                $chunk = $next;
            } while (! $isFinal);
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    public function decrypt(string $source, string $destination): void
    {
        $input = $this->open($source, 'rb');
        $output = $this->open($destination, 'wb');

        try {
            $prefix = $this->readExactly($input, strlen(self::MAGIC) + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if (! str_starts_with($prefix, self::MAGIC)) {
                throw new RuntimeException('The backup encryption header is invalid.');
            }

            $header = substr($prefix, strlen(self::MAGIC));
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->key());
            $cipherChunkBytes = self::CHUNK_BYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
            $chunk = $this->read($input, $cipherChunkBytes);
            if ($chunk === '') {
                throw new RuntimeException('The encrypted backup has no payload.');
            }

            do {
                $next = $this->read($input, $cipherChunkBytes);
                $isFinal = $next === '';
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
                if ($result === false) {
                    throw new RuntimeException('Backup authentication failed.');
                }
                [$plain, $tag] = $result;
                $expectedTag = $isFinal
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                if ($tag !== $expectedTag) {
                    throw new RuntimeException('The encrypted backup is truncated or contains trailing data.');
                }
                $this->write($output, $plain);
                $chunk = $next;
            } while (! $isFinal);
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    public function sign(string $contents): string
    {
        return hash_hmac('sha256', $contents, $this->key());
    }

    private function key(): string
    {
        $configured = (string) config('backup.postgres.encryption_key');
        $encoded = str_starts_with($configured, 'base64:') ? substr($configured, 7) : $configured;
        $key = base64_decode($encoded, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('POSTGRES_BACKUP_ENCRYPTION_KEY must contain exactly 32 base64-encoded bytes.');
        }

        return $key;
    }

    /** @return resource */
    private function open(string $path, string $mode)
    {
        $stream = fopen($path, $mode);
        if ($stream === false) {
            throw new RuntimeException('Unable to open a temporary backup file.');
        }

        return $stream;
    }

    /** @param resource $stream */
    private function read($stream, int $length): string
    {
        $contents = fread($stream, $length);
        if ($contents === false) {
            throw new RuntimeException('Unable to read a temporary backup file.');
        }

        return $contents;
    }

    /** @param resource $stream */
    private function readExactly($stream, int $length): string
    {
        $contents = '';
        while (strlen($contents) < $length && ! feof($stream)) {
            $contents .= $this->read($stream, $length - strlen($contents));
        }
        if (strlen($contents) !== $length) {
            throw new RuntimeException('The encrypted backup header is truncated.');
        }

        return $contents;
    }

    /** @param resource $stream */
    private function write($stream, string $contents): void
    {
        $written = 0;
        $length = strlen($contents);
        while ($written < $length) {
            $bytes = fwrite($stream, substr($contents, $written));
            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('Unable to write a temporary backup file.');
            }
            $written += $bytes;
        }
    }
}

<?php

namespace Tests\Unit;

use App\Services\Backup\BackupCipher;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class BackupCipherTest extends TestCase
{
    public function test_large_backup_round_trips_and_detects_tampering(): void
    {
        config()->set('backup.postgres.encryption_key', 'base64:'.base64_encode(random_bytes(32)));
        $source = tempnam(sys_get_temp_dir(), 'cipher-source-');
        $encrypted = tempnam(sys_get_temp_dir(), 'cipher-encrypted-');
        $restored = tempnam(sys_get_temp_dir(), 'cipher-restored-');
        File::put($source, random_bytes(2_200_000));
        $cipher = app(BackupCipher::class);

        try {
            $cipher->encrypt($source, $encrypted);
            $cipher->decrypt($encrypted, $restored);
            $this->assertSame(hash_file('sha256', $source), hash_file('sha256', $restored));

            $contents = File::get($encrypted);
            $contents[strlen($contents) - 1] = chr(ord($contents[strlen($contents) - 1]) ^ 1);
            File::put($encrypted, $contents);

            $this->expectException(RuntimeException::class);
            $cipher->decrypt($encrypted, $restored);
        } finally {
            File::delete([$source, $encrypted, $restored]);
        }
    }
}

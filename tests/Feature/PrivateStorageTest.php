<?php

namespace Tests\Feature;

use App\Support\PrivateStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateStorageTest extends TestCase
{
    public function test_private_files_can_be_copied_and_verified_between_disks(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        Storage::disk('local')->put('work-orders/1/photo.jpg', 'photo-content');
        Storage::disk('local')->put('tax-returns/1/receipt.pdf', 'receipt-content');

        $this->artisan('storage:migrate-private', ['from' => 'local', 'to' => 's3'])
            ->expectsOutput('2 files copied; 0 unchanged files skipped.')
            ->assertSuccessful();

        Storage::disk('s3')->assertExists([
            'work-orders/1/photo.jpg',
            'tax-returns/1/receipt.pdf',
        ]);
        $this->artisan('storage:migrate-private', ['from' => 'local', 'to' => 's3'])
            ->expectsOutput('0 files copied; 2 unchanged files skipped.')
            ->assertSuccessful();
    }

    public function test_legacy_knowledge_paths_are_read_from_the_configured_private_disk(): void
    {
        Storage::fake('s3');
        config(['filesystems.private' => 's3']);
        Storage::disk('s3')->put('knowledge/manual.md', 'Cloud knowledge');

        $legacy = '/var/www/html/storage/app/private/knowledge/manual.md';

        $this->assertTrue(PrivateStorage::exists($legacy));
        $this->assertSame('Cloud knowledge', PrivateStorage::get($legacy));
        $this->assertSame('knowledge/manual.md', PrivateStorage::normalizePath($legacy));
    }
}

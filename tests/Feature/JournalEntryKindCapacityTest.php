<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JournalEntryKindCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_reclassification_kind_is_preserved_and_cannot_be_truncated(): void
    {
        $entry = JournalEntry::create(['entry_date' => '2026-09-27', 'number' => 'CAPACITY-TEST', 'description' => 'Regression fixture', 'entry_kind' => 'bank_reclassification', 'event' => 'bank_reclassification', 'status' => 'draft']);
        $this->assertSame('bank_reclassification', $entry->fresh()->entry_kind);
        if (config('database.default') === 'pgsql') {
            $column = collect(Schema::getColumns('journal_entries'))->firstWhere('name', 'entry_kind');
            $this->assertSame('character varying(64)', $column['type']);
        }
        $migration = require database_path('migrations/2026_09_27_020000_expand_journal_entry_kinds.php');
        try {
            $migration->down();
            $this->fail('Rollback must not truncate journal entry kinds.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cannot shorten journal entry kinds while longer kinds exist.', $exception->getMessage());
        }
        $this->assertSame('bank_reclassification', $entry->fresh()->entry_kind);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serial_number_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serial_number_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('installation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 30);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['serial_number_id', 'occurred_at']);
        });

        $now = now();
        DB::table('serial_numbers')
            ->join('stock_movement_lines', 'stock_movement_lines.id', '=', 'serial_numbers.received_line_id')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->select('serial_numbers.id as serial_number_id', 'stock_movements.id as stock_movement_id', 'stock_movement_lines.warehouse_id', 'stock_movements.posted_at')
            ->orderBy('serial_numbers.id')
            ->chunk(500, function ($rows) use ($now) {
                DB::table('serial_number_events')->insert($rows->map(fn ($row) => [
                    'serial_number_id' => $row->serial_number_id,
                    'stock_movement_id' => $row->stock_movement_id,
                    'from_warehouse_id' => null,
                    'to_warehouse_id' => $row->warehouse_id,
                    'installation_id' => null,
                    'event_type' => 'received',
                    'occurred_at' => $row->posted_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

        DB::table('serial_numbers')
            ->join('stock_movement_lines', 'stock_movement_lines.id', '=', 'serial_numbers.issued_line_id')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->select('serial_numbers.id as serial_number_id', 'serial_numbers.status', 'serial_numbers.installation_id', 'stock_movements.id as stock_movement_id', 'stock_movement_lines.warehouse_id', 'stock_movements.posted_at')
            ->orderBy('serial_numbers.id')
            ->chunk(500, function ($rows) use ($now) {
                DB::table('serial_number_events')->insert($rows->map(fn ($row) => [
                    'serial_number_id' => $row->serial_number_id,
                    'stock_movement_id' => $row->stock_movement_id,
                    'from_warehouse_id' => $row->warehouse_id,
                    'to_warehouse_id' => null,
                    'installation_id' => $row->installation_id,
                    'event_type' => in_array($row->status, ['installed', 'returned', 'missing'], true) ? $row->status : 'issued',
                    'occurred_at' => $row->posted_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_number_events');
    }
};

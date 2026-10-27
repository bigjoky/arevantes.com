<?php

namespace App\Services\Documents;

use App\Models\SalesDocument;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Request identity for existing document creation paths, not a second billing workflow. */
class SalesDocumentCreation
{
    public function run(Request $request, string $operation, array $payload, Closure $create): array
    {
        $key = $request->validate(['creation_key' => ['nullable', 'uuid']])['creation_key'] ?? null;
        $normalize = function (array $data) use (&$normalize): array {
            if (! array_is_list($data)) {
                ksort($data);
            }

            return array_map(fn ($value) => is_array($value) ? $normalize($value) : $value, $data);
        };
        $fingerprint = hash('sha256', json_encode([$operation, $request->user()->id, $normalize($payload)], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($key, $fingerprint, $create): array {
            if ($key && DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['sales-creation:'.$key]);
            }
            $existing = $key ? SalesDocument::where('creation_key', $key)->lockForUpdate()->first() : null;
            if ($existing) {
                if (! hash_equals($existing->creation_fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['creation_key' => 'Esta operación ya se utilizó con otros datos. Recarga antes de iniciar otra creación.']);
                }

                return [$existing, false];
            }
            [$document, $created] = $create();
            if ($key && $created) {
                $document->forceFill(['creation_key' => $key, 'creation_fingerprint' => $fingerprint])->save();
            }

            return [$document, $created];
        });
    }
}

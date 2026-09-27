<?php

namespace App\Services\Banking;

use App\Models\BankAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StatementImporter
{
    public function import(BankAccount $account, UploadedFile $file): array
    {
        return $this->process($account, $file, true);
    }

    public function preview(BankAccount $account, UploadedFile $file): array
    {
        return $this->process($account, $file, false);
    }

    private function process(BankAccount $account, UploadedFile $file, bool $persist): array
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false || trim($contents) === '') {
            throw ValidationException::withMessages(['statement' => __('The statement could not be read.')]);
        }

        return $this->isNorma43($contents)
            ? $this->importNorma43($account, $contents, $persist)
            : $this->importCsv($account, $file, $persist);
    }

    private function importCsv(BankAccount $account, UploadedFile $file, bool $persist): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            throw ValidationException::withMessages(['statement' => __('The statement could not be read.')]);
        }

        $first = fgets($handle);
        rewind($handle);
        $delimiter = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
        $headers = fgetcsv($handle, 0, $delimiter);

        if (! $headers) {
            throw ValidationException::withMessages(['statement' => __('The statement has no header row.')]);
        }

        $headers = array_map(fn ($value) => Str::of(Str::ascii(trim((string) $value)))->lower()->replace([' ', '-'], '_')->toString(), $headers);
        $columns = $this->columns($headers);
        $created = $duplicates = 0;
        $createdIds = [];
        $occurrences = [];
        $sample = [];

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = array_combine($headers, array_slice(array_pad($values, count($headers), ''), 0, count($headers)));

            if (! $row) {
                continue;
            }

            try {
                $date = $this->date($row[$columns['date']]);
                $amount = $this->amount($row[$columns['amount']]);
            } catch (\Throwable) {
                throw ValidationException::withMessages(['statement' => __('A statement row contains an invalid date or amount.')]);
            }

            $description = trim((string) $row[$columns['description']]);
            $reference = $columns['reference'] ? trim((string) $row[$columns['reference']]) : null;
            $valueDate = $columns['value_date'] && filled($row[$columns['value_date']]) ? $this->date($row[$columns['value_date']]) : null;
            $occurrence = $this->nextOccurrence($occurrences, $date, $valueDate, $amount, $reference, $description);
            $stored = $this->store($account, $date, $valueDate, $amount, $reference, $description, $row, $occurrence, $persist);
            if ($stored['created']) {
                $created++;
                if ($stored['id']) {
                    $createdIds[] = $stored['id'];
                }
            } else {
                $duplicates++;
            }
            $this->addSample($sample, $date, $valueDate, $amount, $reference, $description, $stored['created']);
        }

        fclose($handle);

        return compact('created', 'duplicates', 'sample') + ['created_ids' => $createdIds, 'format' => 'csv', 'metadata' => []];
    }

    private function importNorma43(BankAccount $account, string $contents, bool $persist): array
    {
        // Split physical records before decoding: byte 0x85 is text in Windows-1252.
        $lines = preg_split('/\r\n|\n|\r/', $contents) ?: [];
        $blocks = [];
        $blockIndex = null;
        $movementIndex = null;

        foreach ($lines as $lineNumber => $line) {
            $line = rtrim($line, "\r\n");

            if (trim($line) === '') {
                continue;
            }

            $record = substr($line, 0, 2);

            if ($record === '11') {
                if (strlen($line) < 51) {
                    throw ValidationException::withMessages(['statement' => __('Norma 43 header record :line is truncated.', ['line' => $lineNumber + 1])]);
                }
                $blockIndex = count($blocks);
                $movementIndex = null;
                $blocks[] = ['header' => [
                    'entity' => trim(substr($line, 2, 4)),
                    'office' => trim(substr($line, 6, 4)),
                    'account' => trim(substr($line, 10, 10)),
                    'period_from' => $this->aebDate(substr($line, 20, 6)),
                    'period_to' => $this->aebDate(substr($line, 26, 6)),
                    'opening_balance' => $this->aebSignedAmount(substr($line, 32, 1), substr($line, 33, 14)),
                    'currency_code' => trim(substr($line, 47, 3)),
                ], 'movements' => [], 'control' => null];

                continue;
            }

            if ($record === '22') {
                if ($blockIndex === null) {
                    throw ValidationException::withMessages(['statement' => __('Norma 43 movement record :line appears before an account header.', ['line' => $lineNumber + 1])]);
                }
                if (strlen($line) < 80) {
                    throw ValidationException::withMessages(['statement' => __('Norma 43 record :line is truncated.', ['line' => $lineNumber + 1])]);
                }
                $header = $blocks[$blockIndex]['header'];
                if (trim(substr($line, 2, 4)) !== $header['entity'] || trim(substr($line, 6, 4)) !== $header['office']) {
                    throw ValidationException::withMessages(['statement' => __('Norma 43 movement record :line belongs to a different bank entity or office.', ['line' => $lineNumber + 1])]);
                }

                $sign = substr($line, 27, 1);

                if (! in_array($sign, ['1', '2'], true)) {
                    throw ValidationException::withMessages(['statement' => __('Norma 43 record :line has an invalid debit/credit code.', ['line' => $lineNumber + 1])]);
                }

                $movementIndex = count($blocks[$blockIndex]['movements']);
                $blocks[$blockIndex]['movements'][] = [
                    'booked_on' => $this->aebDate(substr($line, 10, 6)),
                    'value_on' => $this->aebDate(substr($line, 16, 6)),
                    'amount' => ($sign === '1' ? -1 : 1) * ((int) substr($line, 28, 14) / 100),
                    'reference' => $this->cleanAebText(substr($line, 42, 10).' '.substr($line, 52, 12).' '.substr($line, 64, 16)),
                    'description' => trim('Concept '.$this->cleanAebText(substr($line, 22, 2)).'/'.$this->cleanAebText(substr($line, 24, 3))),
                    'raw_data' => ['format' => 'norma43', 'header' => $header, 'record_22' => $this->aebUtf8($line), 'records_23' => []],
                ];

                continue;
            }

            if ($record === '23' && $blockIndex !== null && $movementIndex !== null) {
                $text = $this->cleanAebText(substr($line, 4));
                $blocks[$blockIndex]['movements'][$movementIndex]['raw_data']['records_23'][] = $this->aebUtf8($line);

                if ($text !== '') {
                    $blocks[$blockIndex]['movements'][$movementIndex]['description'] = trim($blocks[$blockIndex]['movements'][$movementIndex]['description'].' '.$text);
                }
            }

            if ($record === '33' && strlen($line) >= 76) {
                if ($blockIndex === null) {
                    throw ValidationException::withMessages(['statement' => __('Norma 43 control record :line appears before an account header.', ['line' => $lineNumber + 1])]);
                }
                $header = $blocks[$blockIndex]['header'];
                if (trim(substr($line, 2, 4)) !== $header['entity'] || trim(substr($line, 6, 4)) !== $header['office'] || trim(substr($line, 10, 10)) !== $header['account']) {
                    throw ValidationException::withMessages(['statement' => __('Norma 43 control record :line does not match its account header.', ['line' => $lineNumber + 1])]);
                }
                $blocks[$blockIndex]['control'] = [
                    'debit_count' => (int) substr($line, 20, 5), 'debit_total' => (int) substr($line, 25, 14) / 100,
                    'credit_count' => (int) substr($line, 39, 5), 'credit_total' => (int) substr($line, 44, 14) / 100,
                    'closing_balance' => $this->aebSignedAmount(substr($line, 58, 1), substr($line, 59, 14)),
                ];
                $movementIndex = null;
            }
        }

        if ($blocks === [] || collect($blocks)->sum(fn ($block) => count($block['movements'])) === 0) {
            throw ValidationException::withMessages(['statement' => __('The file is not a valid Norma 43 statement or contains no movements.')]);
        }
        if (count($blocks) > 1) {
            $identifiers = collect($blocks)->map(fn ($block) => $this->aebAccountIdentifier($block['header']))->unique()->values();
            throw ValidationException::withMessages([
                'statement' => __('This Norma 43 file contains :count account blocks (:accounts). Split it by account before importing so movements cannot be mixed.', ['count' => count($blocks), 'accounts' => $identifiers->implode(', ')]),
            ]);
        }

        $block = $blocks[0];
        $accountHeader = $block['header'];
        $movements = $block['movements'];
        $control = $block['control'];
        $this->assertNorma43Account($account, $accountHeader);

        $created = $duplicates = 0;
        $createdIds = [];
        $occurrences = [];
        $sample = [];

        foreach ($movements as $movement) {
            $occurrence = $this->nextOccurrence($occurrences, $movement['booked_on'], $movement['value_on'], $movement['amount'], $movement['reference'], $movement['description']);
            $stored = $this->store($account, $movement['booked_on'], $movement['value_on'], $movement['amount'], $movement['reference'], $movement['description'], $movement['raw_data'], $occurrence, $persist);
            if ($stored['created']) {
                $created++;
                if ($stored['id']) {
                    $createdIds[] = $stored['id'];
                }
            } else {
                $duplicates++;
            }
            $this->addSample($sample, $movement['booked_on'], $movement['value_on'], $movement['amount'], $movement['reference'], $movement['description'], $stored['created']);
        }

        $computed = ['debit_count' => collect($movements)->where('amount', '<', 0)->count(), 'debit_total' => round(abs((float) collect($movements)->where('amount', '<', 0)->sum('amount')), 2), 'credit_count' => collect($movements)->where('amount', '>', 0)->count(), 'credit_total' => round((float) collect($movements)->where('amount', '>', 0)->sum('amount'), 2)];
        $computed['closing_balance'] = round((float) $accountHeader['opening_balance'] + (float) collect($movements)->sum('amount'), 2);
        $controlValid = $control && $computed['debit_count'] === $control['debit_count'] && $computed['credit_count'] === $control['credit_count'] && abs($computed['debit_total'] - $control['debit_total']) < 0.01 && abs($computed['credit_total'] - $control['credit_total']) < 0.01 && abs($computed['closing_balance'] - $control['closing_balance']) < 0.01;

        return compact('created', 'duplicates', 'sample') + ['created_ids' => $createdIds, 'format' => 'norma43', 'metadata' => ['period_from' => $accountHeader['period_from'], 'period_to' => $accountHeader['period_to'], 'opening_balance' => $accountHeader['opening_balance'], 'closing_balance' => $control['closing_balance'] ?? $computed['closing_balance'], 'control_valid' => (bool) $controlValid, 'control_data' => ['account' => $accountHeader, 'block_count' => 1, 'declared' => $control, 'computed' => $computed]]];
    }

    private function assertNorma43Account(BankAccount $account, array $header): void
    {
        $currency = ['978' => 'EUR', '840' => 'USD', '826' => 'GBP'][$header['currency_code']] ?? null;
        if ($currency && strtoupper($account->currency) !== $currency) {
            throw ValidationException::withMessages(['statement' => __('The statement currency (:statement) does not match the selected account currency (:account).', ['statement' => $currency, 'account' => strtoupper($account->currency)])]);
        }

        $iban = strtoupper((string) preg_replace('/\s+/', '', (string) $account->iban));
        if ($iban === '') {
            return;
        }
        if (! preg_match('/^ES[0-9]{22}$/', $iban)) {
            throw ValidationException::withMessages(['statement' => __('Configure a valid Spanish IBAN on the selected account before importing Norma 43.')]);
        }
        $selected = ['entity' => substr($iban, 4, 4), 'office' => substr($iban, 8, 4), 'account' => substr($iban, 14, 10)];
        if ($selected['entity'] !== $header['entity'] || $selected['office'] !== $header['office'] || $selected['account'] !== $header['account']) {
            throw ValidationException::withMessages(['statement' => __('The Norma 43 account :statement does not match the selected IBAN ending in :iban.', ['statement' => $this->aebAccountIdentifier($header), 'iban' => substr($iban, -4)])]);
        }
    }

    private function aebAccountIdentifier(array $header): string
    {
        return $header['entity'].'-'.$header['office'].'-'.substr($header['account'], -4);
    }

    /** @return array{created: bool, id: int|null} */
    private function store(BankAccount $account, string $date, ?string $valueDate, float $amount, ?string $reference, string $description, array $rawData, int $occurrence, bool $persist): array
    {
        $baseHash = $this->movementHash($date, $valueDate, $amount, $reference, $description);
        $hash = $occurrence === 1 ? $baseHash : hash('sha256', $baseHash.'|occurrence:'.$occurrence);

        if (! $persist) {
            return ['created' => ! $account->transactions()->where('import_hash', $hash)->exists(), 'id' => null];
        }

        $transaction = $account->transactions()->firstOrCreate(['import_hash' => $hash], [
            'booked_on' => $date,
            'value_on' => $valueDate,
            'amount' => round($amount, 2),
            'currency' => $account->currency,
            'reference' => $reference ?: null,
            'description' => $description ?: __('Bank movement'),
            'status' => 'pending',
            'raw_data' => $rawData,
        ]);

        return ['created' => $transaction->wasRecentlyCreated, 'id' => $transaction->wasRecentlyCreated ? $transaction->id : null];
    }

    private function addSample(array &$sample, string $date, ?string $valueDate, float $amount, ?string $reference, string $description, bool $created): void
    {
        if (count($sample) >= 10) {
            return;
        }

        $sample[] = [
            'booked_on' => $date,
            'value_on' => $valueDate,
            'amount' => round($amount, 2),
            'reference' => $reference ?: null,
            'description' => $description ?: __('Bank movement'),
            'status' => $created ? 'new' : 'duplicate',
        ];
    }

    private function nextOccurrence(array &$occurrences, string $date, ?string $valueDate, float $amount, ?string $reference, string $description): int
    {
        $fingerprint = $this->movementHash($date, $valueDate, $amount, $reference, $description);

        return $occurrences[$fingerprint] = ($occurrences[$fingerprint] ?? 0) + 1;
    }

    private function movementHash(string $date, ?string $valueDate, float $amount, ?string $reference, string $description): string
    {
        return hash('sha256', implode('|', [$date, $valueDate, number_format($amount, 2, '.', ''), $reference, $description]));
    }

    private function isNorma43(string $contents): bool
    {
        $first = collect(preg_split('/\R/', $contents))->first(fn ($line) => trim((string) $line) !== '');

        return is_string($first) && str_starts_with($first, '11') && strlen($first) >= 50;
    }

    private function aebDate(string $value): string
    {
        $date = Carbon::createFromFormat('ymd', $value);

        if (! $date || $date->format('ymd') !== $value) {
            throw new \InvalidArgumentException('Invalid AEB date.');
        }

        return $date->toDateString();
    }

    private function cleanAebText(string $value): string
    {
        $value = $this->aebUtf8($value);

        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }

    private function aebUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    private function aebSignedAmount(string $sign, string $amount): float
    {
        return ($sign === '1' ? -1 : 1) * ((int) $amount / 100);
    }

    private function columns(array $headers): array
    {
        $find = fn (array $names) => collect($names)->first(fn ($name) => in_array($name, $headers, true));
        $columns = ['date' => $find(['date', 'fecha', 'booked_on', 'fecha_operacion']), 'value_date' => $find(['value_date', 'fecha_valor', 'valor']), 'amount' => $find(['amount', 'importe', 'cantidad']), 'description' => $find(['description', 'descripcion', 'concepto', 'detalle']), 'reference' => $find(['reference', 'referencia', 'ref'])];

        if (! $columns['date'] || ! $columns['amount'] || ! $columns['description']) {
            throw ValidationException::withMessages(['statement' => __('Required columns: date/fecha, amount/importe and description/concepto.')]);
        }

        return $columns;
    }

    private function date(string $value): string
    {
        $value = trim($value);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
            }
        }

        return Carbon::parse($value)->toDateString();
    }

    private function amount(string $value): float
    {
        $value = preg_replace('/[^0-9,\.\-]/', '', trim($value));

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = strrpos($value, ',') > strrpos($value, '.') ? str_replace(['.', ','], ['', '.'], $value) : str_replace(',', '', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException;
        }

        return round((float) $value, 2);
    }
}

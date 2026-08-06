<?php

namespace App\Services\Finance;

use App\Models\FinancialBudget;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetCsvImporter
{
    private const CATEGORIES = ['revenue', 'costs', 'cash_in', 'cash_out'];

    /** @return array{imported: int, skipped: int} */
    public function import(string $path, int $year, bool $overwrite, ?int $userId): array
    {
        $rows = $this->read($path, $year);

        return DB::transaction(function () use ($rows, $overwrite, $userId) {
            $imported = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                $budget = FinancialBudget::firstOrNew([
                    'year' => $row['year'],
                    'month' => $row['month'],
                    'category' => $row['category'],
                ]);
                if ($budget->exists && ! $overwrite) {
                    $skipped++;

                    continue;
                }
                $budget->fill([
                    'amount' => $row['amount'],
                    'notes' => $row['notes'],
                    'created_by' => $userId,
                ])->save();
                $imported++;
            }

            return compact('imported', 'skipped');
        });
    }

    /** @return array<int, array{year: int, month: int, category: string, amount: float, notes: ?string}> */
    private function read(string $path, int $expectedYear): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['budget_file' => __('The budget file could not be read.')]);
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw ValidationException::withMessages(['budget_file' => __('The budget file is empty.')]);
            }
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);
            $header = fgetcsv($handle, 0, $delimiter, '"', '');
            if ($header === false) {
                throw ValidationException::withMessages(['budget_file' => __('The budget file has no header.')]);
            }
            $header = array_map(fn ($value) => strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $value))), $header);
            $indexes = array_flip($header);
            foreach (['year', 'month', 'category', 'amount'] as $required) {
                if (! array_key_exists($required, $indexes)) {
                    throw ValidationException::withMessages(['budget_file' => __('Missing required column: :column.', ['column' => $required])]);
                }
            }

            $rows = [];
            $seen = [];
            $line = 1;
            while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $line++;
                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                if (count($rows) >= 1000) {
                    throw $this->lineError($line, __('The file cannot contain more than 1000 budget rows.'));
                }

                $rowYear = filter_var($this->value($values, $indexes, 'year'), FILTER_VALIDATE_INT);
                $month = filter_var($this->value($values, $indexes, 'month'), FILTER_VALIDATE_INT);
                $category = strtolower(trim($this->value($values, $indexes, 'category')));
                $amount = $this->amount($this->value($values, $indexes, 'amount'));
                $notes = trim($this->value($values, $indexes, 'notes'));

                if ($rowYear === false || $rowYear !== $expectedYear) {
                    throw $this->lineError($line, __('The year must be :year.', ['year' => $expectedYear]));
                }
                if ($month === false || $month < 1 || $month > 12) {
                    throw $this->lineError($line, __('Month must be between 1 and 12.'));
                }
                if (! in_array($category, self::CATEGORIES, true)) {
                    throw $this->lineError($line, __('Unknown category: :category.', ['category' => $category]));
                }
                if ($amount === null || $amount < 0 || $amount > 999999999999.99) {
                    throw $this->lineError($line, __('Amount must be a non-negative numeric value.'));
                }
                if (mb_strlen($notes) > 1000) {
                    throw $this->lineError($line, __('Notes cannot exceed 1000 characters.'));
                }

                $key = $rowYear.'-'.$month.'-'.$category;
                if (isset($seen[$key])) {
                    throw $this->lineError($line, __('The month and category are duplicated in the file.'));
                }
                $seen[$key] = true;
                $rows[] = [
                    'year' => $rowYear,
                    'month' => $month,
                    'category' => $category,
                    'amount' => round($amount, 2),
                    'notes' => $notes !== '' ? $notes : null,
                ];
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['budget_file' => __('The budget file contains no data rows.')]);
        }

        return $rows;
    }

    /** @param array<int, string|null> $values
     * @param  array<string, int>  $indexes
     */
    private function value(array $values, array $indexes, string $column): string
    {
        if (! isset($indexes[$column])) {
            return '';
        }

        return trim((string) ($values[$indexes[$column]] ?? ''));
    }

    private function amount(string $value): ?float
    {
        $value = str_replace(["\u{00A0}", ' '], '', trim($value));
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = strrpos($value, ',') > strrpos($value, '.')
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function lineError(int $line, string $message): ValidationException
    {
        return ValidationException::withMessages([
            'budget_file' => __('Line :line: :message', ['line' => $line, 'message' => $message]),
        ]);
    }
}

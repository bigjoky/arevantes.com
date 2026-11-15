<?php

namespace App\Services\HistoricalInvoices;

use RuntimeException;

/** Reads a bounded, untrusted CSV into documentary rows; never writes economic data. */
final class CsvReader
{
    public const COLUMNS = ['supplier_tax_id', 'supplier_number', 'issue_date', 'operation_date', 'received_date', 'due_date', 'taxable_base', 'vat_rate', 'vat_amount', 'withholding_rate', 'withholding_amount', 'total'];

    public const MAX_BYTES = 1048576;

    public const MAX_ROWS = 1000;

    /**
     * @return list<array{row: int, values: array<string, string>, errors: list<string>}>
     */
    public function read(string $path): array
    {
        $size = filesize($path);
        if ($size === false || $size > self::MAX_BYTES) {
            throw new RuntimeException('El archivo supera el límite de 1 MB o no se puede leer.');
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se puede abrir el archivo.');
        }

        try {
            $header = fgetcsv($handle, escape: '');
            if ($header !== false && isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            if ($header !== self::COLUMNS) {
                throw new RuntimeException('La cabecera no coincide con la plantilla CSV.');
            }
            $rows = [];
            while (($fields = fgetcsv($handle, escape: '')) !== false) {
                if (count($rows) >= self::MAX_ROWS) {
                    throw new RuntimeException('El archivo supera el límite de 1000 filas.');
                }
                $number = count($rows) + 2;
                $errors = [];
                if (count($fields) !== count(self::COLUMNS)) {
                    $errors[] = 'Número de columnas incorrecto.';
                }
                $values = [];
                foreach (self::COLUMNS as $index => $column) {
                    $value = (string) ($fields[$index] ?? '');
                    if (! mb_check_encoding($value, 'UTF-8') || strlen($value) > 255 || str_contains($value, "\0")) {
                        $errors[] = "Contenido inválido o demasiado largo: {$column}.";
                        $value = '';
                    }
                    // Whitespace and control characters cannot hide spreadsheet formulas.
                    if (preg_match('/^[\s\x00-\x1f]*[=+@\-]/u', $value)) {
                        $errors[] = "Contenido de fórmula no permitido: {$column}.";
                        $value = '';
                    }
                    $values[$column] = trim($value);
                }
                $rows[] = ['row' => $number, 'values' => $values, 'errors' => $errors];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}

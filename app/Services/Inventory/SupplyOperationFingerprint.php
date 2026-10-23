<?php

namespace App\Services\Inventory;

class SupplyOperationFingerprint
{
    public static function for(array $data): string
    {
        unset($data['operation_key']);

        return hash('sha256', json_encode(self::ordered($data), JSON_THROW_ON_ERROR));
    }

    private static function ordered(array $data): array
    {
        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = self::ordered($value);
            } elseif (is_numeric($value)) {
                $value = (string) (0 + $value);
            }
        }

        return $data;
    }
}

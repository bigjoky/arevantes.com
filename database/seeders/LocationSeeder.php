<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Locality;
use App\Models\Municipality;
use App\Models\PostalCode;
use App\Models\Province;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Seed a first Spain/Cordoba dataset.
     */
    public function run(): void
    {
        $spain = Country::updateOrCreate(
            ['code' => 'ES'],
            ['name' => 'Spain']
        );

        $cordoba = Province::updateOrCreate(
            ['country_id' => $spain->id, 'name' => 'Córdoba'],
            ['code' => '14']
        );

        $rows = [
            ['municipality' => 'Córdoba', 'locality' => 'Córdoba', 'postal_codes' => ['14001', '14002', '14003', '14004', '14005', '14006', '14007', '14008', '14009', '14010', '14011', '14012', '14013', '14014']],
            ['municipality' => 'Córdoba', 'locality' => 'Alcolea', 'postal_codes' => ['14610']],
            ['municipality' => 'Córdoba', 'locality' => 'Santa Cruz', 'postal_codes' => ['14820']],
            ['municipality' => 'Lucena', 'locality' => 'Lucena', 'postal_codes' => ['14900']],
            ['municipality' => 'Lucena', 'locality' => 'Jauja', 'postal_codes' => ['14911']],
            ['municipality' => 'Lucena', 'locality' => 'Las Navas del Selpillar', 'postal_codes' => ['14914']],
            ['municipality' => 'Cabra', 'locality' => 'Cabra', 'postal_codes' => ['14940']],
            ['municipality' => 'Priego de Córdoba', 'locality' => 'Priego de Córdoba', 'postal_codes' => ['14800']],
            ['municipality' => 'Puente Genil', 'locality' => 'Puente Genil', 'postal_codes' => ['14500']],
            ['municipality' => 'Montilla', 'locality' => 'Montilla', 'postal_codes' => ['14550']],
            ['municipality' => 'Baena', 'locality' => 'Baena', 'postal_codes' => ['14850']],
            ['municipality' => 'Palma del Río', 'locality' => 'Palma del Río', 'postal_codes' => ['14700']],
            ['municipality' => 'Aguilar de la Frontera', 'locality' => 'Aguilar de la Frontera', 'postal_codes' => ['14920']],
        ];

        foreach ($rows as $row) {
            $municipality = Municipality::updateOrCreate(
                ['province_id' => $cordoba->id, 'name' => $row['municipality']],
                ['code' => null]
            );

            $locality = Locality::updateOrCreate(
                ['municipality_id' => $municipality->id, 'name' => $row['locality']]
            );

            foreach ($row['postal_codes'] as $code) {
                $postalCode = PostalCode::updateOrCreate(
                    ['country_id' => $spain->id, 'code' => $code]
                );

                $locality->postalCodes()->syncWithoutDetaching($postalCode->id);
            }
        }
    }
}

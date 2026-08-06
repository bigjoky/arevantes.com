<?php

namespace App\Console\Commands;

use App\Models\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class SyncSpainLocations extends Command
{
    protected $signature = 'locations:sync-spain';

    protected $description = 'Synchronize Spanish postal locations from GeoNames';

    public function handle(): int
    {
        $url = config('services.geonames.spain_postal_url');
        $this->info('Downloading the Spanish postal directory...');

        $response = Http::retry(3, 1000)->timeout(60)->get($url);

        if (! $response->successful()) {
            $this->error("GeoNames returned HTTP {$response->status()}.");

            return self::FAILURE;
        }

        try {
            $rows = $this->parseArchive($response->body());
            $counts = $this->persist($rows);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Synchronized %d provinces, %d municipalities, %d localities and %d postal links.',
            $counts['provinces'],
            $counts['municipalities'],
            $counts['localities'],
            $counts['links'],
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<array{postal_code: string, locality: string, province: string, province_code: string, municipality: string, municipality_code: string}>
     */
    private function parseArchive(string $archive): array
    {
        $path = tempnam(sys_get_temp_dir(), 'geonames-es-');

        if ($path === false || file_put_contents($path, $archive) === false) {
            throw new RuntimeException('The downloaded postal directory could not be stored.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            @unlink($path);
            throw new RuntimeException('GeoNames returned an invalid ZIP archive.');
        }

        $contents = $zip->getFromName('ES.txt');
        $zip->close();
        @unlink($path);

        if ($contents === false) {
            throw new RuntimeException('The GeoNames archive does not contain ES.txt.');
        }

        $rows = [];

        foreach (preg_split('/\R/', $contents) as $line) {
            if ($line === '') {
                continue;
            }

            $columns = str_getcsv($line, "\t");

            if (count($columns) < 12 || $columns[0] !== 'ES') {
                continue;
            }

            $province = trim($columns[5]);
            $municipality = trim($columns[7]) ?: trim($columns[2]);
            $locality = trim($columns[2]);

            if ($province === '' || $municipality === '' || $locality === '') {
                continue;
            }

            $rows[] = [
                'postal_code' => trim($columns[1]),
                'locality' => $locality,
                'province' => $province,
                'province_code' => trim($columns[6]),
                'municipality' => $municipality,
                'municipality_code' => trim($columns[8]),
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('The GeoNames archive contains no usable Spanish postal locations.');
        }

        return $rows;
    }

    /**
     * @param  list<array{postal_code: string, locality: string, province: string, province_code: string, municipality: string, municipality_code: string}>  $rows
     * @return array{provinces: int, municipalities: int, localities: int, links: int}
     */
    private function persist(array $rows): array
    {
        return DB::transaction(function () use ($rows): array {
            $now = now();
            $country = Country::updateOrCreate(['code' => 'ES'], ['name' => 'Spain']);

            // Location IDs are only used while filling forms; replacing Spain's
            // directory prevents stale or differently accented entries lingering.
            DB::table('provinces')->where('country_id', $country->id)->delete();
            DB::table('postal_codes')->where('country_id', $country->id)->delete();

            $provinceRows = collect($rows)->unique(fn (array $row) => $this->locationKey($row['province']))->map(fn (array $row) => [
                'country_id' => $country->id,
                'name' => $row['province'],
                'code' => $row['province_code'] ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all();
            DB::table('provinces')->upsert($provinceRows, ['country_id', 'name'], ['code', 'updated_at']);
            $provinces = DB::table('provinces')->where('country_id', $country->id)->get(['id', 'name'])
                ->mapWithKeys(fn ($province) => [$this->locationKey($province->name) => $province->id]);

            $municipalityRows = collect($rows)->map(fn (array $row) => [
                'province_id' => $provinces[$this->locationKey($row['province'])],
                'name' => $row['municipality'],
                'code' => $row['municipality_code'] ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->unique(fn (array $row) => $row['province_id'].'|'.$this->locationKey($row['name']))->values()->all();
            foreach (array_chunk($municipalityRows, 1000) as $chunk) {
                DB::table('municipalities')->upsert($chunk, ['province_id', 'name'], ['code', 'updated_at']);
            }
            $municipalities = DB::table('municipalities')->get(['id', 'province_id', 'name'])
                ->keyBy(fn ($row) => $row->province_id.'|'.$this->locationKey($row->name));

            $postalRows = collect($rows)->unique('postal_code')->map(fn (array $row) => [
                'country_id' => $country->id,
                'code' => $row['postal_code'],
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all();
            foreach (array_chunk($postalRows, 1000) as $chunk) {
                DB::table('postal_codes')->upsert($chunk, ['country_id', 'code'], ['updated_at']);
            }
            $postalCodes = DB::table('postal_codes')->where('country_id', $country->id)->pluck('id', 'code');

            $localityRows = collect($rows)->map(function (array $row) use ($provinces, $municipalities, $now): array {
                $provinceId = $provinces[$this->locationKey($row['province'])];
                $municipality = $municipalities[$provinceId.'|'.$this->locationKey($row['municipality'])];

                return [
                    'municipality_id' => $municipality->id,
                    'name' => $row['locality'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->unique(fn (array $row) => $row['municipality_id'].'|'.$this->locationKey($row['name']))->values()->all();
            foreach (array_chunk($localityRows, 1000) as $chunk) {
                DB::table('localities')->upsert($chunk, ['municipality_id', 'name'], ['updated_at']);
            }
            $localities = DB::table('localities')->get(['id', 'municipality_id', 'name'])
                ->keyBy(fn ($row) => $row->municipality_id.'|'.$this->locationKey($row->name));

            $links = collect($rows)->map(function (array $row) use ($provinces, $municipalities, $localities, $postalCodes): array {
                $provinceId = $provinces[$this->locationKey($row['province'])];
                $municipality = $municipalities[$provinceId.'|'.$this->locationKey($row['municipality'])];
                $locality = $localities[$municipality->id.'|'.$this->locationKey($row['locality'])];

                return [
                    'locality_id' => $locality->id,
                    'postal_code_id' => $postalCodes[$row['postal_code']],
                ];
            })->unique(fn (array $row) => $row['locality_id'].'|'.$row['postal_code_id'])->values()->all();
            foreach (array_chunk($links, 1000) as $chunk) {
                DB::table('locality_postal_code')->insertOrIgnore($chunk);
            }

            return [
                'provinces' => count($provinceRows),
                'municipalities' => count($municipalityRows),
                'localities' => count($localityRows),
                'links' => count($links),
            ];
        });
    }

    private function locationKey(string $value): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/', ' ', trim($value)) ?? trim($value)));
    }
}

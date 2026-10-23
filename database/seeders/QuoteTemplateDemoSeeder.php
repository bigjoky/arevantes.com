<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuoteTemplateDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo templates are restricted to local and testing environments.');
        }
        $user = User::firstOrCreate(['email' => 'plantillas@example.test'], ['name' => 'Prueba de plantillas', 'role' => 'admin', 'password' => 'Demo-Plantillas-2026!']);
        $user->forceFill(['email_verified_at' => now()])->save();
        Person::firstOrCreate(['company_name' => 'Cliente de prueba · plantillas'], ['category' => 'customer', 'first_name' => 'Demo', 'payment_terms_days' => 30]);
        foreach ([
            ['Solar 6 kW · ejemplo', 'solar', [['Panel fotovoltaico 500 W', 12, 85.125], ['Inversor 6 kW', 1, 850], ['Estructura y fijaciones', 1, 480], ['Protecciones y cableado', 1, 320], ['Montaje, puesta en marcha y legalización', 1, 1100]]],
            ['Punto de recarga 7,4 kW · ejemplo', 'ev_charger', [['Cargador monofásico 7,4 kW', 1, 650], ['Cableado y canalización (m)', 15, 12.450], ['Protecciones eléctricas', 1, 180], ['Instalación y puesta en marcha', 1, 350]]],
            ['Climatización split · ejemplo', 'hvac', [['Equipo split 3,5 kW', 1, 540], ['Tubería frigorífica (m)', 3, 24.750], ['Soportes y desagüe', 1, 65], ['Instalación y puesta en marcha', 1, 280]]],
        ] as [$name, $type, $items]) {
            $lines = collect($items)->map(fn ($item, $index) => ['client_key' => 'demo-line-'.$index, 'depth' => 1, 'product_id' => null, 'unit_id' => null, 'tax_rate_id' => null, 'description' => $item[0], 'quantity' => (string) $item[1], 'unit_price' => number_format($item[2], 3, '.', ''), 'total_with_tax' => '', 'discount_percent' => '0', 'tax_percent' => '21', 'selection_mode' => 'included', 'option_group' => '', 'is_selected' => true, 'components' => []])->all();
            QuoteTemplate::firstOrCreate(['name' => $name], ['service_type' => $type, 'created_by' => $user->id, 'lines' => $lines]);
        }
    }
}

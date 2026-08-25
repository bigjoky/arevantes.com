<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('installation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('service_slug', 40);
            $table->string('location')->nullable();
            $table->text('summary');
            $table->text('description')->nullable();
            $table->date('completed_on')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['published_at', 'sort_order']);
            $table->index(['service_slug', 'published_at']);
        });

        Schema::create('portfolio_project_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('portfolio_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('alt_text');
            $table->string('caption')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['portfolio_project_id', 'position']);
        });

        $now = now();
        $projects = [
            [
                'title' => 'Autoconsumo en vivienda rural',
                'slug' => 'autoconsumo-vivienda-rural-axarquia',
                'service_slug' => 'energia',
                'location' => 'Axarquía, Málaga',
                'summary' => 'Integración fotovoltaica sobre cubierta inclinada en una vivienda situada en un entorno rural.',
                'description' => 'Una solución de autoconsumo adaptada a la geometría de la cubierta y al paisaje del entorno. La disposición aprovecha las superficies disponibles manteniendo una integración visual limpia en la vivienda.',
                'completed_on' => '2023-09-08',
                'is_featured' => true,
                'sort_order' => 10,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Instalación fotovoltaica en vivienda',
                'slug' => 'instalacion-fotovoltaica-vivienda-baena',
                'service_slug' => 'energia',
                'location' => 'Baena, Córdoba',
                'summary' => 'Sistema fotovoltaico distribuido sobre una cubierta residencial inclinada.',
                'description' => 'El campo fotovoltaico se distribuye entre los paños útiles de la cubierta para adaptarse a sus condicionantes y mantener una ejecución ordenada. El resultado combina aprovechamiento de superficie, accesibilidad y una implantación coherente con la vivienda.',
                'completed_on' => '2024-09-15',
                'is_featured' => true,
                'sort_order' => 20,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Autoconsumo residencial en cubierta plana',
                'slug' => 'autoconsumo-cubierta-plana-costa-del-sol',
                'service_slug' => 'energia',
                'location' => 'Costa del Sol, Málaga',
                'summary' => 'Instalación de autoconsumo sobre cubierta plana en un entorno residencial.',
                'description' => 'Una implantación fotovoltaica diseñada para ordenar las filas sobre la cubierta plana y conservar zonas de paso. La orientación del conjunto y la disposición de los módulos responden a las condiciones reales del edificio.',
                'completed_on' => '2025-05-01',
                'is_featured' => true,
                'sort_order' => 30,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('portfolio_projects')->insert($projects);
        $ids = DB::table('portfolio_projects')->pluck('id', 'slug');

        DB::table('portfolio_project_images')->insert([
            $this->image($ids['autoconsumo-vivienda-rural-axarquia'], 'images/projects/autoconsumo-vivienda-rural.webp', 119030, 'Paneles fotovoltaicos integrados sobre la cubierta de una vivienda rural', 10, $now),
            $this->image($ids['instalacion-fotovoltaica-vivienda-baena'], 'images/projects/autoconsumo-residencial-baena.webp', 142454, 'Instalación fotovoltaica distribuida en la cubierta inclinada de una vivienda', 10, $now),
            $this->image($ids['instalacion-fotovoltaica-vivienda-baena'], 'images/projects/paneles-cubierta-baena.webp', 108608, 'Detalle del campo fotovoltaico instalado sobre teja', 20, $now),
            $this->image($ids['autoconsumo-cubierta-plana-costa-del-sol'], 'images/projects/autoconsumo-costa-del-sol.webp', 268102, 'Filas de paneles fotovoltaicos sobre una cubierta plana residencial', 10, $now),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_project_images');
        Schema::dropIfExists('portfolio_projects');
    }

    /** @return array<string, mixed> */
    private function image(int $projectId, string $path, int $size, string $altText, int $position, mixed $now): array
    {
        return [
            'portfolio_project_id' => $projectId,
            'uploaded_by' => null,
            'disk' => 'public_asset',
            'path' => $path,
            'mime_type' => 'image/webp',
            'size' => $size,
            'width' => 1600,
            'height' => 900,
            'alt_text' => $altText,
            'caption' => null,
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
};

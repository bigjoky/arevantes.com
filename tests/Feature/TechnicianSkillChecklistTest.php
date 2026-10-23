<?php

namespace Tests\Feature;

use App\Models\FieldServiceProfile;
use App\Models\User;
use App\Services\Work\TechnicianSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TechnicianSkillChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_checklist_preserves_existing_custom_skills_and_exposes_readable_labels(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'daily_capacity_minutes' => 480, 'max_travel_km' => 150, 'skills' => ['solar', 'trabajos_en_altura'], 'is_available' => true]);
        $this->actingAs($admin)->get(route('field-service.index'))->assertInertia(fn (Assert $page) => $page->where('skillOptions', fn ($options) => collect($options)->contains(fn ($item) => $item['value'] === 'solar' && $item['label'] === 'Instalaciones solares') && collect($options)->contains(fn ($item) => $item['value'] === 'electrical' && $item['label'] === 'Electricidad') && collect($options)->contains(fn ($item) => $item['value'] === 'trabajos_en_altura'))->etc());
        $this->put(route('field-service.profiles.update', $technician), ['daily_capacity_minutes' => 480, 'max_travel_km' => 150, 'skills' => ['solar', 'electrical', 'trabajos_en_altura'], 'is_available' => true])->assertSessionHasNoErrors();
        $this->assertSame(['solar', 'electrical', 'trabajos_en_altura'], $technician->fieldServiceProfile()->first()->skills);
        app(TechnicianSchedule::class)->assertAvailable($technician->id, null, null, null, ['solar', 'electrical']);
    }

    public function test_unchecking_every_skill_clears_the_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        FieldServiceProfile::create(['user_id' => $technician->id, 'skills' => ['solar', 'electrical'], 'daily_capacity_minutes' => 480, 'is_available' => true]);
        $this->actingAs($admin)->put(route('field-service.profiles.update', $technician), ['daily_capacity_minutes' => 480, 'max_travel_km' => 150, 'is_available' => true])->assertSessionHasNoErrors();
        $this->assertSame([], $technician->fieldServiceProfile()->first()->skills);
        try {
            app(TechnicianSchedule::class)->assertAvailable($technician->id, null, null, null, ['solar', 'electrical']);
            $this->fail('Required skills must still be enforced.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['assigned_user_id'][0];
            $this->assertStringContainsString('Instalaciones solares', $message);
            $this->assertStringContainsString('Electricidad', $message);
        }
    }
}

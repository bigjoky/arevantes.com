<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_spanish_is_native_for_signed_in_users_even_with_legacy_english_app_config(): void
    {
        config(['app.locale' => 'en']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'))->assertOk()->assertSee('<html lang="es"', false)
            ->assertInertia(fn (Assert $page) => $page->where('localization.current', 'es'));
    }

    public function test_explicit_english_choice_is_preserved_and_invalid_session_falls_back_to_spanish(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->withSession(['locale' => 'en'])
            ->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('localization.current', 'en'));
        $this->withSession(['locale' => 'fr'])->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('localization.current', 'es'));
    }

    public function test_authenticated_user_can_change_language_for_the_current_session(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->patch(route('locale.update'), ['locale' => 'es'])
            ->assertRedirect()
            ->assertSessionHas('locale', 'es')
            ->assertSessionHas('toast.message', 'Idioma cambiado para esta sesión.');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('localization.current', 'es')
                ->where('localization.supported.0.code', 'es')
                ->where('localization.supported.0.label', 'Español')
                ->where('localization.supported.1.code', 'en'));

        $this->patch(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect()
            ->assertSessionHas('locale', 'en')
            ->assertSessionHas('toast.message', 'Language changed for this session.');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertInertia(fn (Assert $page) => $page->where('localization.current', 'en'));
    }

    public function test_guest_can_select_language_for_public_pages(): void
    {
        $this->patch(route('locale.update'), ['locale' => 'es'])
            ->assertRedirect()
            ->assertSessionHas('locale', 'es');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertInertia(fn (Assert $page) => $page->where('localization.current', 'es'));
    }

    public function test_language_selection_rejects_unsupported_locales(): void
    {
        $this->withSession(['locale' => 'es'])
            ->patch(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale')
            ->assertSessionHas('locale', 'es');

        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->withSession(['locale' => 'es'])
            ->patch(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale')
            ->assertSessionHas('locale', 'es');
    }
}

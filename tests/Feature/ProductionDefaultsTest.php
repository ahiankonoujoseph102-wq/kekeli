<?php

namespace Tests\Feature;

use App\Billing\ProductionDefaults;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Base de production neuve : Administration → Paiements et Administration → Réglages sont remplis tout seuls au
 * déploiement (accord du porteur du projet du 14 novembre 2026), sans jamais changer une valeur déjà saisie.
 */
class ProductionDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_on_an_empty_base_the_four_countries_the_ten_methods_the_whatsapp_and_the_settings_are_filled(): void
    {
        $this->assertSame(0, Setting::query()->count());

        app(ProductionDefaults::class)->apply();
        $settings = app(Settings::class);

        $countries = $settings->get('payment_countries');
        $this->assertSame(['TG', 'BF', 'BJ', 'CI'], array_keys($countries));
        $this->assertSame(10, collect($countries)->sum(fn ($place) => count($place['methods'])));
        $this->assertSame('Ahiankonou Kokou Joseph', $countries['TG']['holder']);
        $this->assertSame([['name' => 'Mixx by Yas', 'number' => '+228 72 24 02 31'], ['name' => 'Flooz', 'number' => '+228 79 16 23 90']], $countries['TG']['methods']);
        $this->assertSame('Anderson Passoule Oyo', $countries['BF']['holder']);
        $this->assertSame('KOUAGOU Noutcha Adam Fernando', $countries['BJ']['holder']);
        $this->assertSame(['MTN', 'Moov', 'Celtiis'], array_column($countries['BJ']['methods'], 'name'));
        $this->assertSame('OULOUSSEGUEA CHARLES DANHAN', $countries['CI']['holder']);
        $this->assertSame(['Orange Money', 'MTN Mobile Money', 'Wave'], array_column($countries['CI']['methods'], 'name'));
        $this->assertStringNotContainsString('T-Money', json_encode($countries));

        $this->assertSame('+228 72 24 02 31', $settings->get('payment_whatsapp'));
        $this->assertSame(100, $settings->int('email_daily_cap'));
        $this->assertEqualsWithDelta(10.0, $settings->float('openai_daily_cap_usd'), 0.0001);
        $this->assertSame(['+228 72 24 02 31', '+228 79 16 23 90', '+228 79 74 21 27'], $settings->get('contact_phones'));
        // Seulement le nom, l'e-mail et le téléphone de l'émetteur : aucune adresse ni aucun numéro légal inventés.
        $this->assertSame(['name' => 'Kekeli', 'address' => '', 'email' => 'kekelilome3@gmail.com', 'phone' => '+228 72 24 02 31', 'legal' => ''], $settings->get('receipt_issuer'));
    }

    public function test_the_client_of_each_country_sees_the_right_numbers_and_holders(): void
    {
        app(ProductionDefaults::class)->apply();
        $user = User::factory()->create(['country' => 'TG']);

        $expected = [
            'TG' => ['Ahiankonou Kokou Joseph', 'Mixx by Yas', '+228 72 24 02 31', 'Flooz', '+228 79 16 23 90'],
            'BF' => ['Anderson Passoule Oyo', 'Orange Money', '+226 07 27 63 81', 'Wave', '+226 03 53 21 76'],
            'BJ' => ['KOUAGOU Noutcha Adam Fernando', 'MTN', '+229 01 51 53 39 35', 'Moov', '+229 01 94 55 59 14', 'Celtiis', '+229 01 40 28 57 61'],
            'CI' => ['OULOUSSEGUEA CHARLES DANHAN', 'Orange Money', '+225 07 09 51 82 85', 'MTN Mobile Money', '+225 05 45 53 11 70', 'Wave'],
        ];
        foreach ($expected as $country => $texts) {
            $page = Volt::actingAs($user)->test('payments.create', ['plan' => 'pro'])->set('country', $country);
            foreach ($texts as $text) {
                $page->assertSee($text);
            }
        }

        // Autre pays : le bouton WhatsApp vers le numéro réglé.
        Volt::actingAs($user)->test('payments.create', ['plan' => 'pro'])->set('country', 'autre')
            ->assertSee('Écrire sur WhatsApp')
            ->assertSee('wa.me/22872240231', false);
    }

    public function test_a_value_already_typed_in_the_administration_is_never_changed(): void
    {
        $settings = app(Settings::class);
        // L'administrateur a déjà saisi un autre numéro Flooz et un autre titulaire au Togo, et gardé 150 e-mails.
        $settings->set('payment_countries', ['TG' => ['holder' => 'Titulaire saisi à la main', 'methods' => [['name' => 'Flooz', 'number' => '+228 90 00 00 01']]]]);
        $settings->set('payment_whatsapp', '+228 90 00 00 09');
        $settings->set('email_daily_cap', 150);
        $settings->set('receipt_issuer', ['name' => 'Lomé IA School', 'address' => 'Adresse saisie', 'email' => '', 'phone' => '+228 90 00 00 02', 'legal' => '']);

        app(ProductionDefaults::class)->apply();

        $togo = $settings->get('payment_countries')['TG'];
        $this->assertSame('Titulaire saisi à la main', $togo['holder']);
        $this->assertSame(['name' => 'Flooz', 'number' => '+228 90 00 00 01'], $togo['methods'][0]);
        // Le moyen qui manquait est ajouté, sans toucher à l'autre.
        $this->assertSame(['name' => 'Mixx by Yas', 'number' => '+228 72 24 02 31'], $togo['methods'][1]);
        $this->assertCount(2, $togo['methods']);
        $this->assertSame('+228 90 00 00 09', $settings->get('payment_whatsapp'));
        $this->assertSame(150, $settings->int('email_daily_cap'));
        // Émetteur : seul l'e-mail vide est rempli.
        $this->assertSame(['name' => 'Lomé IA School', 'address' => 'Adresse saisie', 'email' => 'kekelilome3@gmail.com', 'phone' => '+228 90 00 00 02', 'legal' => ''], $settings->get('receipt_issuer'));
    }

    public function test_running_it_again_adds_nothing_twice(): void
    {
        $defaults = app(ProductionDefaults::class);
        $this->assertNotSame([], $defaults->apply());

        $before = Setting::query()->orderBy('key')->get(['key', 'value'])->toArray();
        $this->assertSame([], $defaults->apply());
        $this->assertSame([], $defaults->apply());

        $this->assertSame($before, Setting::query()->orderBy('key')->get(['key', 'value'])->toArray());
        $this->assertSame(10, collect(app(Settings::class)->get('payment_countries'))->sum(fn ($place) => count($place['methods'])));
    }

    public function test_the_migration_calls_the_filling_and_is_skipped_while_the_tests_recreate_the_base(): void
    {
        $migration = require database_path('migrations/2026_11_23_000001_fill_production_settings.php');
        $migration->up();
        $this->assertSame(0, Setting::query()->count(), 'pendant les tests, la migration ne remplit rien');

        $this->assertStringContainsString('app(ProductionDefaults::class)->apply()', file_get_contents(database_path('migrations/2026_11_23_000001_fill_production_settings.php')));
    }

    public function test_the_administration_shows_the_filled_numbers(): void
    {
        app(ProductionDefaults::class)->apply();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.payments'))->assertOk()
            ->assertSee('OULOUSSEGUEA CHARLES DANHAN')
            ->assertSee('+229 01 40 28 57 61')
            ->assertSee('+228 72 24 02 31');
    }
}

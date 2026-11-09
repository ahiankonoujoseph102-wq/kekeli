<?php

namespace Tests\Feature;

use App\Agent\Energy;
use App\Billing\Subscriptions;
use App\Models\AdminAlert;
use App\Models\Payment;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_the_formulas_page_shows_the_published_prices_and_a_button_to_pay(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('plans'))
            ->assertSee('2 500 FCFA par mois')
            ->assertSee('7 500 FCFA par mois')
            ->assertSee('25 000 FCFA par mois')
            ->assertSee('Choisir cette formule')
            ->assertDontSee('data-plans-coming', false);
    }

    public function test_the_client_sees_the_numbers_of_the_country_and_sends_only_a_screenshot(): void
    {
        $user = User::factory()->create(['country' => 'TG']);

        $this->actingAs($user)->get(route('payments.create', 'pro'))
            ->assertSee('Payer la formule Pro')
            ->assertSee('Mixx by Yas')
            ->assertSee('+228 72 24 02 31')
            ->assertSee('Flooz')
            ->assertSee('Ahiankonou Kokou Joseph')
            ->assertDontSee('T-Money')
            ->assertSee('Aucune référence à taper');

        $component = Volt::actingAs($user)->test('payments.create', ['plan' => 'pro'])
            ->assertSet('country', 'TG')
            ->set('country', 'BJ')
            ->assertSee('KOUAGOU Noutcha Adam Fernando')
            ->assertSee('Celtiis')
            ->set('country', 'CI')
            ->assertSee('OULOUSSEGUEA CHARLES DANHAN')
            ->assertSee('Wave');

        // Un fichier qui n'est pas une image est refusé.
        $component->set('screenshot', UploadedFile::fake()->create('releve.pdf', 50, 'application/pdf'))
            ->call('send')
            ->assertHasErrors('screenshot');
        $this->assertSame(0, Payment::count());

        $component->set('screenshot', UploadedFile::fake()->image('capture.png', 720, 1600))
            ->call('send')
            ->assertHasNoErrors()
            ->assertSee('Votre paiement est en cours de vérification');

        $payment = Payment::sole();
        $this->assertSame('pro', $payment->plan);
        $this->assertSame(7500, $payment->amount_fcfa);
        $this->assertSame('CI', $payment->country);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        Storage::disk('local')->assertExists($payment->screenshot_path);
        $this->assertSame('payment_pending', AdminAlert::latest('id')->value('kind'));

        // Un seul paiement en attente à la fois.
        $component->set('screenshot', UploadedFile::fake()->image('encore.png'))->call('send');
        $this->assertSame(1, Payment::count());
    }

    public function test_other_countries_write_on_whatsapp(): void
    {
        $user = User::factory()->create(['country' => 'SN']);

        Volt::actingAs($user)->test('payments.create', ['plan' => 'decouverte'])
            ->assertSet('country', 'autre')
            ->assertSee('Écrire sur WhatsApp')
            ->assertSee('https://wa.me/22872240231?text=', false)
            ->assertDontSee('data-send-payment', false);
    }

    public function test_the_free_formula_and_unknown_formulas_cannot_be_paid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('payments.create', 'gratuite'))->assertNotFound();
        $this->actingAs($user)->get(route('payments.create', 'inconnue'))->assertNotFound();
    }

    public function test_the_administrator_validates_a_payment_and_the_formula_is_active_for_30_days(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(10, 0));
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $payment = app(Subscriptions::class)->submit($user, 'pro', 'TG', 'paiements/1/a.png');
        Storage::disk('local')->put('paiements/1/a.png', 'octets');

        $this->actingAs($admin)->get(route('admin.payments'))
            ->assertSee('En attente de votre vérification (1)')
            ->assertSee($user->email)
            ->assertSee('7 500 FCFA');
        $this->actingAs($admin)->get(route('admin.payments.screenshot', $payment))->assertOk();

        Volt::actingAs($admin)->test('admin.payments')->call('validatePayment', $payment->id);

        $user->refresh();
        $this->assertSame('pro', $user->plan);
        $this->assertTrue($user->plan_expires_at->equalTo(now()->addDays(30)));
        $this->assertSame(Payment::STATUS_VALIDATED, $payment->fresh()->status);
        $this->assertSame('Pro', app(Energy::class)->plan($user)['name']);

        // Renouvellement avant la fin : les jours restants ne sont pas perdus.
        $this->travel(25)->days();
        $again = app(Subscriptions::class)->submit($user, 'pro', 'TG', 'paiements/1/b.png');
        app(Subscriptions::class)->validate($again, $admin);
        $this->assertTrue($user->fresh()->plan_expires_at->equalTo(now()->subDays(25)->addDays(60)));
    }

    public function test_a_refused_payment_shows_the_reason_to_the_client(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $payment = app(Subscriptions::class)->submit($user, 'decouverte', 'TG', 'paiements/1/a.png');

        Volt::actingAs($admin)->test('admin.payments')
            ->set('reasons.'.$payment->id, 'Paiement introuvable dans l\'historique')
            ->call('refusePayment', $payment->id);

        $this->assertSame(Payment::STATUS_REFUSED, $payment->fresh()->status);
        $this->assertNotSame('decouverte', $user->fresh()->plan);
        $this->actingAs($user)->get(route('plans'))
            ->assertSee("Votre dernier paiement n'a pas pu être validé.")
            ->assertSee("Paiement introuvable dans l'historique");
    }

    public function test_only_administrators_see_the_payments_and_the_screenshots(): void
    {
        $user = User::factory()->create();
        $payment = app(Subscriptions::class)->submit($user, 'pro', 'TG', 'paiements/1/a.png');
        Storage::disk('local')->put('paiements/1/a.png', 'octets');

        $this->actingAs($user)->get(route('admin.payments'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.payments.screenshot', $payment))->assertForbidden();
    }

    public function test_the_reminder_appears_before_the_end_then_the_formula_ends(): void
    {
        $user = User::factory()->create(['plan' => 'pro', 'plan_expires_at' => now()->addDays(10)]);
        $this->actingAs($user)->get(route('projects.index'))->assertDontSee('data-plan-reminder', false);

        $user->forceFill(['plan_expires_at' => now()->addDays(2)])->save();
        $this->actingAs($user)->get(route('projects.index'))
            ->assertSee('Votre formule Pro se termine le')
            ->assertSee('Renouveler');

        // Fin de la période : la formule gratuite revient, même avant la tâche planifiée.
        $this->travel(3)->days();
        $this->assertSame('gratuite', app(Energy::class)->plan($user->fresh())['key']);

        $this->artisan('kekeli:expirer-formules')->assertSuccessful();
        $user->refresh();
        $this->assertSame('gratuite', $user->plan);
        $this->assertNull($user->plan_expires_at);
    }

    public function test_the_administrator_changes_the_payment_numbers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $component = Volt::actingAs($admin)->test('admin.payments')
            ->set('countries.0.methods.1.number', '+228 99 00 00 00')
            ->set('newCountry', 'SN')
            ->call('addCountry')
            ->set('countries.4.holder', 'Titulaire Sénégal')
            ->set('countries.4.methods.0.name', 'Wave')
            ->set('countries.4.methods.0.number', '+221 77 000 00 00')
            ->call('saveNumbers')
            ->assertHasNoErrors();

        $countries = app(Settings::class)->get('payment_countries');
        $this->assertSame('+228 99 00 00 00', $countries['TG']['methods'][1]['number']);
        $this->assertSame('Titulaire Sénégal', $countries['SN']['holder']);

        // Un numéro invalide est refusé.
        $component->set('countries.0.methods.0.number', 'pas un numéro')->call('saveNumbers')->assertHasErrors('countries.0.methods.0.number');

        $client = User::factory()->create(['country' => 'SN']);
        $this->actingAs($client)->get(route('payments.create', 'pro'))->assertSee('Titulaire Sénégal');
    }
}

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

        // Numéros et titulaires inventés : les vrais se règlent seulement dans Administration → Paiements.
        app(Settings::class)->set('payment_countries', [
            'TG' => ['holder' => 'Titulaire Togo', 'methods' => [['name' => 'Mixx by Yas', 'number' => '+228 90 00 00 01'], ['name' => 'Flooz', 'number' => '+228 90 00 00 02']]],
            'BJ' => ['holder' => 'Titulaire Bénin', 'methods' => [['name' => 'MTN', 'number' => '+229 90 00 00 01'], ['name' => 'Moov', 'number' => '+229 90 00 00 02'], ['name' => 'Celtiis', 'number' => '+229 90 00 00 03']]],
            'BF' => ['holder' => 'Titulaire Burkina Faso', 'methods' => [['name' => 'Orange Money', 'number' => '+226 70 00 00 01'], ['name' => 'Wave', 'number' => '+226 70 00 00 02']]],
            'CI' => ['holder' => 'Titulaire Côte d\'Ivoire', 'methods' => [['name' => 'Orange Money', 'number' => '+225 07 00 00 00 01'], ['name' => 'Wave', 'number' => '+225 07 00 00 00 02']]],
        ]);
        app(Settings::class)->set('payment_whatsapp', '+228 90 00 00 09');
    }

    public function test_the_formulas_page_shows_the_published_prices_and_a_button_to_pay(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('plans'))
            ->assertSee('7 500 FCFA')
            ->assertSee('20 000 FCFA')
            ->assertSee('50 000 FCFA')
            ->assertSee('Choisir Pro')
            ->assertSee(route('payments.create', 'expert'))
            ->assertDontSee('Découverte')
            ->assertDontSee('data-plans-coming', false);
    }

    public function test_the_client_sees_the_numbers_of_the_country_and_sends_only_a_screenshot(): void
    {
        $user = User::factory()->create(['country' => 'TG']);

        $this->actingAs($user)->get(route('payments.create', 'pro'))
            ->assertSee('Payer la formule Pro')
            ->assertSee('Mixx by Yas')
            ->assertSee('+228 90 00 00 01')
            ->assertSee('Flooz')
            ->assertSee('Titulaire Togo')
            ->assertDontSee('T-Money')
            ->assertSee('3. Envoyez la capture d&#039;écran ici.', false);

        $component = Volt::actingAs($user)->test('payments.create', ['plan' => 'pro'])
            ->assertSet('country', 'TG')
            ->set('country', 'BJ')
            ->assertSee('Titulaire Bénin')
            ->assertSee('Celtiis')
            ->set('country', 'CI')
            ->assertSee('Titulaire Côte d\'Ivoire')
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

        Volt::actingAs($user)->test('payments.create', ['plan' => 'pro'])
            ->assertSet('country', 'autre')
            ->assertSee('Écrire sur WhatsApp')
            ->assertSee('https://wa.me/22890000009?text=', false)
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
        $payment = app(Subscriptions::class)->submit($user, 'pro', 'TG', 'paiements/1/a.png');

        Volt::actingAs($admin)->test('admin.payments')
            ->set('reasons.'.$payment->id, 'Paiement introuvable dans l\'historique')
            ->call('refusePayment', $payment->id);

        $this->assertSame(Payment::STATUS_REFUSED, $payment->fresh()->status);
        $this->assertNotSame('pro', $user->fresh()->plan);
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

    public function test_no_payment_number_is_written_in_the_code(): void
    {
        // Les numéros, les titulaires et le numéro WhatsApp se règlent seulement dans l'administration.
        $this->assertSame([], config('kekeli.settings.payment_countries'));
        $this->assertSame('', config('kekeli.settings.payment_whatsapp'));

        app(Settings::class)->set('payment_countries', []);
        app(Settings::class)->set('payment_whatsapp', '');
        $user = User::factory()->create(['country' => 'TG']);

        Volt::actingAs($user)->test('payments.create', ['plan' => 'pro'])
            ->assertSee('Le paiement n\'est pas encore ouvert dans votre pays')
            ->assertDontSee('wa.me', false);
        $this->get(route('legal.terms'))->assertOk()->assertSee('depuis votre compte Kekeli')->assertDontSee('wa.me', false);
        $this->get(route('legal.privacy'))->assertOk()->assertDontSee('wa.me', false);
    }

    public function test_the_legal_pages_show_the_whatsapp_number_set_by_the_administrator(): void
    {
        $this->get(route('legal.terms'))->assertSee('+228 90 00 00 09')->assertSee('https://wa.me/22890000009', false);
        $this->get(route('legal.privacy'))->assertSee('+228 90 00 00 09');
    }
}

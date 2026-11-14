<?php

namespace Tests\Feature;

use App\Billing\Receipts;
use App\Billing\Subscriptions;
use App\Jobs\SendEmail;
use App\Mail\Emails;
use App\Mail\KekeliMail;
use App\Models\EmailLog;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use LogicException;
use Tests\TestCase;

/**
 * Reçus d'abonnement et e-mails avec Brevo : un reçu PDF par paiement validé (numéro unique, jamais modifié, envoyé
 * en pièce jointe, retrouvé dans « Mes reçus », renvoyé ou annulé par l'administrateur), les e-mails de paiement et
 * de formule, la file avec le plafond du jour et la priorité, le journal masqué. Runner et envoi simulés.
 */
class ReceiptsAndEmailsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        config(['kekeli.runner.secret' => self::SECRET, 'kekeli.runner.url' => 'http://runner.test', 'mail.from.address' => 'envoi@exemple.tg']);
        Http::fake(['runner.test/recus' => Http::response(['ok' => true, 'pdf' => base64_encode('%PDF-1.4 reçu inventé'), 'apercu' => base64_encode('image'), 'pages' => 1])]);
        app(Settings::class)->set('payment_countries', ['TG' => ['holder' => 'Titulaire inventé', 'methods' => [['name' => 'Mixx by Yas', 'number' => '+228 90 00 00 01'], ['name' => 'Flooz', 'number' => '+228 90 00 00 02']]]]);
    }

    /** A payment of the Pro formula validated by the administrator, with the method and the reference. */
    private function validatedPayment(?User $user = null, ?string $reference = 'MP251114.1032.A12345'): Payment
    {
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'admin'.uniqid().'@exemple.tg']);
        $user ??= User::factory()->create(['name' => 'Afi Mensah', 'email' => 'afi@exemple.tg', 'country' => 'TG']);
        $payment = app(Subscriptions::class)->submit($user, 'pro', 'TG', 'paiements/a.png');
        Volt::actingAs($admin)->test('admin.payments')->set('methods.'.$payment->id, 'Flooz')->set('references.'.$payment->id, (string) $reference)->call('validatePayment', $payment->id)->assertHasNoErrors();

        return $payment->fresh();
    }

    public function test_a_validated_payment_gives_a_numbered_receipt_sent_as_a_pdf_attachment(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(10, 0));
        $payment = $this->validatedPayment();
        $receipt = Receipt::sole();

        $this->assertSame('KEK-2026-000001', $receipt->number);
        $this->assertSame(['Flooz', 'MP251114.1032.A12345'], [$payment->method, $payment->reference]);
        Storage::disk('local')->assertExists('recus/2026/KEK-2026-000001.pdf');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/recus') && $r['structure']['titre'] === 'Reçu KEK-2026-000001' && ! str_contains($r->body(), self::SECRET));

        Mail::assertSent(KekeliMail::class, fn (KekeliMail $m) => $m->hasTo('afi@exemple.tg')
            && $m->subjectLine === 'Votre reçu Kekeli KEK-2026-000001'
            && $m->file['name'] === 'KEK-2026-000001.pdf'
            && count($m->attachments()) === 1);
        $this->assertNotNull($receipt->fresh()->sent_at);
        $this->assertTrue(EmailLog::where('type', 'receipt')->sole()->priority);
    }

    public function test_the_receipt_has_every_required_detail_and_no_invented_legal_number(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 14)->setTime(10, 0));
        $this->validatedPayment();
        $structure = Receipts::structure(Receipt::sole());
        $text = json_encode($structure, JSON_UNESCAPED_UNICODE);

        foreach (['KEK-2026-000001', 'Date du paiement', '14 novembre 2026', 'Afi Mensah', 'afi@exemple.tg', 'Formule', 'Pro', 'du 14 novembre 2026 au 14 décembre 2026', '7 500 FCFA', 'Flooz', 'MP251114.1032.A12345', 'Payé', 'Kekeli, un produit de Lomé IA School', 'Adidogomé, Lomé, Togo', 'E-mail : kekelilome3@gmail.com'] as $expected) {
            $this->assertStringContainsString($expected, $text, $expected);
        }
        // Chaque numéro reste sur une seule ligne (espaces insécables), le téléphone principal n'apparaît qu'une fois.
        $this->assertStringContainsString("Téléphones : +228\u{00A0}72\u{00A0}24\u{00A0}02\u{00A0}31 · +228\u{00A0}79\u{00A0}16\u{00A0}23\u{00A0}90 · +228\u{00A0}79\u{00A0}74\u{00A0}21\u{00A0}27", $this->contactLine($structure));
        $this->assertSame(1, substr_count($text, "72\u{00A0}24"), 'le téléphone principal n’apparaît qu’une fois');
        $this->assertStringNotContainsString('Téléphone :', $text);
        $this->assertStringNotContainsString('kekeli.dev', $text);
        $this->assertStringNotContainsString('RCCM', $text);
        $this->assertSame('document', $structure['type']);
    }

    /** @param  array<string, mixed>  $structure */
    private function contactLine(array $structure): string
    {
        return (string) collect($structure['blocs'])->where('type', 'paragraphe')->pluck('texte')->first(fn ($t) => str_contains($t, 'Téléphones'));
    }

    public function test_without_a_contact_email_the_line_disappears_from_receipts_and_emails(): void
    {
        $settings = app(Settings::class);
        $settings->set('receipt_issuer', ['name' => 'Lomé IA School', 'address' => 'Adidogomé, Lomé, Togo', 'email' => '', 'phone' => '', 'legal' => '']);
        $this->validatedPayment();
        $text = json_encode(Receipts::structure(Receipt::sole()), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('E-mail :', $text);
        $this->assertStringNotContainsString('envoi@exemple.tg', $text, 'jamais l’adresse d’envoi à la place du contact');

        $mail = new KekeliMail('Sujet', 'Titre', ['Texte.']);
        $this->assertStringNotContainsString('E-mail :', $mail->render());
        $this->assertSame([], $mail->envelope()->replyTo);
    }

    public function test_emails_answer_to_the_contact_address_and_keep_each_phone_on_one_line(): void
    {
        $mail = new KekeliMail('Sujet', 'Titre', ['Texte.']);
        $this->assertSame('kekelilome3@gmail.com', $mail->envelope()->replyTo[0]->address);
        $html = $mail->render();
        $this->assertStringContainsString('>kekelilome3@gmail.com</a>', $html);
        $this->assertStringContainsString("<span style=\"white-space:nowrap;\">+228\u{00A0}72\u{00A0}24\u{00A0}02\u{00A0}31</span>", $html);
        $this->assertSame(1, substr_count($html, "72\u{00A0}24\u{00A0}02"), 'le téléphone principal n’apparaît qu’une fois');
    }

    public function test_the_refused_payment_email_says_how_to_reach_us(): void
    {
        $user = User::factory()->create(['email' => 'kofi@exemple.tg', 'country' => 'TG']);
        $payment = app(Subscriptions::class)->submit($user, 'pro', 'TG', 'paiements/a.png');
        $payment->forceFill(['refusal_reason' => 'la capture ne montre pas le montant'])->save();
        app(Emails::class)->paymentRefused($payment);
        Mail::assertSent(KekeliMail::class, fn (KekeliMail $m) => str_contains(implode(' ', $m->paragraphs), 'kekelilome3@gmail.com')
            && str_contains(implode(' ', $m->paragraphs), "+228\u{00A0}72\u{00A0}24\u{00A0}02\u{00A0}31"));
    }

    public function test_outside_the_fcfa_zone_the_amount_is_in_dollars(): void
    {
        $user = User::factory()->create(['name' => 'Haruto', 'email' => 'haruto@exemple.jp', 'country' => 'JP']);
        $this->validatedPayment($user);
        $this->assertSame('13 $ (7 500 FCFA payés par Mobile Money)', Receipt::sole()->amountText());
    }

    public function test_numbers_follow_without_any_duplicate(): void
    {
        foreach (range(1, 3) as $i) {
            $this->validatedPayment(User::factory()->create(['email' => "client$i@exemple.tg"]));
        }
        $year = now(config('kekeli.display_timezone'))->format('Y');
        $this->assertSame(["KEK-$year-000001", "KEK-$year-000002", "KEK-$year-000003"], Receipt::orderBy('id')->pluck('number')->all());
        $this->assertSame(3, Receipt::distinct()->count('number'));
        // Le même paiement ne donne jamais deux reçus.
        app(Receipts::class)->issueFor(Payment::first());
        $this->assertSame(3, Receipt::count());
    }

    public function test_a_receipt_is_never_changed_an_error_gives_a_cancellation_receipt(): void
    {
        $this->validatedPayment();
        $receipt = Receipt::sole();

        try {
            $receipt->update(['amount_fcfa' => 1]);
            $this->fail('Un reçu ne doit jamais être modifié.');
        } catch (LogicException) {
        }
        try {
            $receipt->delete();
            $this->fail('Un reçu ne doit jamais être effacé.');
        } catch (LogicException) {
        }

        $admin = User::factory()->create(['is_admin' => true]);
        Volt::actingAs($admin)->test('admin.payments')->call('cancelReceipt', $receipt->id)->assertHasErrors('cancelReasons.'.$receipt->id);
        Volt::actingAs($admin)->test('admin.payments')->set('cancelReasons.'.$receipt->id, 'Mauvaise formule choisie')->call('cancelReceipt', $receipt->id)->assertHasNoErrors();

        $cancel = Receipt::where('kind', Receipt::CANCELLATION)->sole();
        $this->assertSame($receipt->id, $cancel->cancels_receipt_id);
        $this->assertSame(-7500, $cancel->amount_fcfa);
        $this->assertSame(7500, $receipt->fresh()->amount_fcfa, 'le reçu d’origine ne change pas');
        $this->assertStringContainsString('Annulé', json_encode(Receipts::structure($cancel), JSON_UNESCAPED_UNICODE));
        Mail::assertSent(KekeliMail::class, fn (KekeliMail $m) => $m->subjectLine === 'Reçu d’annulation '.$cancel->number);
    }

    public function test_the_client_finds_his_receipts_and_only_his(): void
    {
        $payment = $this->validatedPayment();
        $receipt = Receipt::sole();
        $client = $payment->user;

        $this->actingAs($client)->get(route('settings.plan'))->assertOk()
            ->assertSee('Mes reçus')->assertSee($receipt->number)->assertSee('Formule Pro');
        $this->actingAs($client)->get(route('receipts.show', $receipt))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs(User::factory()->create())->get(route('receipts.show', $receipt))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('admin.receipts.show', $receipt))->assertForbidden();
    }

    public function test_the_administrator_sees_and_sends_a_receipt_again(): void
    {
        $this->validatedPayment();
        $receipt = Receipt::sole();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.payments'))->assertSee($receipt->number)->assertSee('Envoyer à nouveau');
        $this->actingAs($admin)->get(route('admin.receipts.show', $receipt))->assertOk();
        Volt::actingAs($admin)->test('admin.payments')->call('resendReceipt', $receipt->id);
        Mail::assertSent(KekeliMail::class, fn (KekeliMail $m) => $m->title === 'Votre reçu '.$receipt->number && $m->file !== null);
        $this->assertSame(2, EmailLog::where('type', 'receipt')->count());
    }

    public function test_payment_emails_to_the_administrator_and_to_the_client(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'admin@exemple.tg']);
        $user = User::factory()->create(['email' => 'afi@exemple.tg']);
        $payment = app(Subscriptions::class)->submit($user, 'pro', 'TG', 'paiements/a.png');
        Mail::assertSent(KekeliMail::class, fn (KekeliMail $m) => $m->hasTo('admin@exemple.tg') && $m->subjectLine === 'Un paiement attend votre vérification');

        Volt::actingAs($admin)->test('admin.payments')->set('reasons.'.$payment->id, 'La capture ne montre pas le montant.')->call('refusePayment', $payment->id);
        Mail::assertSent(KekeliMail::class, fn (KekeliMail $m) => $m->hasTo('afi@exemple.tg')
            && $m->subjectLine === 'Votre paiement n’a pas pu être validé'
            && in_array('Raison : La capture ne montre pas le montant.', $m->paragraphs, true));
        $this->assertSame(0, Receipt::count());

        // ADMIN_ALERT_EMAIL, s'il est réglé, passe avant les comptes administrateurs.
        config(['kekeli.admin_alert_email' => 'alertes@exemple.tg']);
        $this->assertSame(['alertes@exemple.tg'], app(Emails::class)->adminAddresses());
    }

    public function test_the_formula_ending_tomorrow_then_ended(): void
    {
        $user = User::factory()->create(['email' => 'afi@exemple.tg', 'plan' => 'pro', 'plan_expires_at' => now()->addHours(20)]);
        $this->artisan('kekeli:expirer-formules')->assertSuccessful();
        Mail::assertSent(KekeliMail::class, fn (KekeliMail $m) => $m->hasTo('afi@exemple.tg') && $m->subjectLine === 'Votre formule Pro se termine demain');
        $this->artisan('kekeli:expirer-formules');
        $this->assertSame(1, EmailLog::where('type', 'plan_ending')->count(), 'un seul rappel');

        $this->travel(21)->hours();
        $this->artisan('kekeli:expirer-formules');
        Mail::assertSent(KekeliMail::class, fn (KekeliMail $m) => $m->subjectLine === 'Votre formule Pro est terminée');
        $this->assertNull($user->fresh()->plan_expires_at);
    }

    public function test_the_ceiling_of_the_day_lets_codes_and_receipts_pass_and_the_others_wait_for_tomorrow(): void
    {
        app(Settings::class)->set('email_daily_cap', 2);
        $user = User::factory()->create(['name' => 'Afi', 'email' => 'afi@exemple.tg']);
        $emails = app(Emails::class);

        $emails->welcome($user);
        $emails->welcome($user);
        $late = $emails->welcome($user);
        $code = $emails->loginCode('afi@exemple.tg', '123456', 'https://kekeli.test/lien');

        $this->assertSame(EmailLog::DEFERRED, $late->fresh()->status);
        $this->assertNotNull($late->fresh()->deferred_until);
        $this->assertSame(EmailLog::SENT, $code->fresh()->status);
        $this->assertSame(3, SendEmail::sentToday());
        Mail::assertSent(KekeliMail::class, 3);
    }

    public function test_a_failed_sending_is_tried_again_and_written_in_the_journal(): void
    {
        $user = User::factory()->create(['email' => 'afi.mensah@exemple.tg']);
        $log = EmailLog::create(['type' => 'welcome', 'user_id' => $user->id, 'recipient_masked' => EmailLog::mask($user->email), 'status' => EmailLog::QUEUED]);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connexion refusée par afi.mensah@exemple.tg'));
        $job = new SendEmail($log->id, $user->email, new KekeliMail('Sujet', 'Titre'));
        $this->assertSame(4, $job->tries);

        try {
            $job->handle();
        } catch (\RuntimeException) {
        }
        $job->failed(null);
        $log->refresh();
        $this->assertSame([EmailLog::FAILED, 1], [$log->status, $log->attempts]);
        $this->assertStringNotContainsString('afi.mensah@exemple.tg', (string) $log->error, 'jamais l’adresse dans le journal');
        $this->assertSame('a•••@e•••.tg', $log->recipient_masked);
    }

    public function test_the_journal_shows_masked_recipients_to_the_administrator_only(): void
    {
        $user = User::factory()->create(['name' => 'Afi', 'email' => 'afi.mensah@exemple.tg']);
        app(Emails::class)->welcome($user);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.emails'))->assertOk()
            ->assertSee('Bienvenue')->assertSee('a•••@e•••.tg')->assertSee('Envoyé')->assertSee('1 <span', false)
            ->assertDontSee('afi.mensah@exemple.tg');
        $this->actingAs($user)->get(route('admin.emails'))->assertForbidden();
    }

    public function test_every_email_is_in_french_with_a_text_version_and_the_contact_phones(): void
    {
        $mail = new KekeliMail('Votre code Kekeli : 482913', 'Votre code de connexion', ['Voici votre code.'], code: '482913', details: ['Formule' => 'Pro'], button: ['label' => 'Se connecter', 'url' => 'https://kekeli.test/lien'], note: 'Valable 10 minutes.');
        $html = $mail->render();
        foreach (['Votre code de connexion', '482913', 'Se connecter', "+228\u{00A0}72\u{00A0}24\u{00A0}02\u{00A0}31", 'Kekeli, un produit de Lomé IA School', 'prefers-color-scheme: dark', 'lang="fr"'] as $expected) {
            $this->assertStringContainsString($expected, $html, $expected);
        }
        $text = view('emails.message-text', ['title' => $mail->title, 'paragraphs' => $mail->paragraphs, 'code' => $mail->code, 'details' => $mail->details, 'button' => $mail->button, 'note' => $mail->note, 'footer' => KekeliMail::footer()])->render();
        $this->assertStringContainsString('Se connecter : https://kekeli.test/lien', $text);
        $this->assertStringContainsString("Téléphones : +228\u{00A0}72\u{00A0}24\u{00A0}02\u{00A0}31 · +228\u{00A0}79\u{00A0}16\u{00A0}23\u{00A0}90 · +228\u{00A0}79\u{00A0}74\u{00A0}21\u{00A0}27", $text);
        $this->assertStringContainsString('E-mail : kekelilome3@gmail.com', $text);
        $this->assertStringNotContainsString('<', $text);
    }

    public function test_the_administrator_sets_the_ceiling_the_issuer_and_the_contact_phones(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk()->assertSee('E-mails et reçus')->assertSee('Téléphones de contact')->assertSee('Émetteur des reçus');
        Volt::actingAs($admin)->test('admin.settings')->assertSet('emailCap', 150)->assertSet('issuer.name', 'Lomé IA School')->assertSet('issuer.phone', '+228 72 24 02 31')->assertSet('issuer.email', 'kekelilome3@gmail.com')
            ->assertSet('contactPhones', "+228 72 24 02 31\n+228 79 16 23 90\n+228 79 74 21 27");

        Volt::actingAs($admin)->test('admin.settings')
            ->set('emailCap', 120)
            ->set('contactPhones', "+228 90 00 00 01\n\n+228 90 00 00 02")
            ->set('issuer.phone', '+228 90 00 00 03')
            ->set('issuer.legal', 'Numéro inventé pour l’essai')
            ->call('saveEmails')->assertHasNoErrors();

        $settings = app(Settings::class);
        $this->assertSame(120, $settings->int('email_daily_cap'));
        $this->assertSame(['+228 90 00 00 01', '+228 90 00 00 02'], $settings->get('contact_phones'));
        $this->assertSame('+228 90 00 00 03', $settings->get('receipt_issuer')['phone']);
        Volt::actingAs($admin)->test('admin.settings')->set('emailCap', 400)->call('saveEmails')->assertHasErrors('emailCap');
    }
}

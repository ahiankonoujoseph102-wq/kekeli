// Fabrique d'une application web Laravel à partir d'une description (runner/src/applications/modeles.json).
// Le code produit est du Laravel ordinaire, en français pour le client : modèles, contrôleurs, vues Blade,
// politiques d'accès et tests. Les fichiers fixes du squelette Laravel sont dans socles/laravel/.
//
// Sécurité, toujours : mots de passe chiffrés (cast « hashed »), protection des formulaires (@csrf),
// limite des essais de connexion, données de chaque utilisateur séparées (politique d'accès et tests),
// aucune clé ni aucun mot de passe dans le code (seulement des variables d'environnement).
import { FEUILLE_DE_STYLE, ICONES, accord, aucun, constanteOptions, exemple, le, majuscule, mes, tous, un, verifierModele } from './commun.js'

const php = String.raw

/** Barre oblique inverse des espaces de noms PHP (« App\Models\… »), placée juste avant une valeur insérée. */
const B = '\\'

const icone = (nom) => `<svg class="icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONES[nom]}</svg>`

/** Échappe un texte pour une chaîne PHP entre apostrophes. */
const q = (texte) => `'${String(texte).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`

/** Règles de validation Laravel d'un champ. */
function regles(entite, champ) {
  const r = [champ.requis ? q('required') : q('nullable')]
  switch (champ.type) {
    case 'string':
      r.push(q('string'), q('max:255'))
      break
    case 'text':
      r.push(q('string'), q('max:5000'))
      break
    case 'tel':
      r.push(q('string'), q('max:30'), q('regex:/^[0-9+ ().-]{6,30}$/'))
      break
    case 'email':
      r.push(q('email'), q('max:255'))
      break
    case 'date':
      r.push(q('date'))
      if (champ.contrainte === 'futur') r.push(q('after_or_equal:today'))
      if (champ.contrainte === 'passe') r.push(q('before:today'))
      if (champ.contrainte?.startsWith('apres:')) r.push(q(`after:${champ.contrainte.slice(6)}`))
      break
    case 'time':
      r.push(q('date_format:H:i'))
      break
    case 'integer':
      r.push(q('integer'))
      if (champ.min !== undefined) r.push(q(`min:${champ.min}`))
      if (champ.max !== undefined) r.push(q(`max:${champ.max}`))
      break
    case 'select':
      r.push(`Rule::in(${entite.classe}::${constanteOptions(champ)})`)
      break
  }
  return `[${r.join(', ')}]`
}

/** Colonne de la migration d'un champ. */
function colonne(champ) {
  const type = { string: 'string', text: 'text', tel: "string('" + champ.nom + "', 30)", email: 'string', date: 'date', time: 'string', integer: 'unsignedInteger', select: 'string' }[champ.type]
  const appel = type.includes('(') ? `$table->${type}` : `$table->${type}('${champ.nom}'${champ.type === 'time' ? ', 5' : ''})`
  return `            ${appel}${champ.requis ? '' : '->nullable()'};`
}

/** Valeur affichée d'un champ dans une vue Blade. */
function affichage(variable, champ) {
  if (champ.type === 'date') return `{{ $${variable}->${champ.nom}?->format('d/m/Y') }}`
  if (champ.type === 'text') return `{!! nl2br(e($${variable}->${champ.nom})) !!}`
  return `{{ $${variable}->${champ.nom} }}`
}

/** Champ de formulaire Blade. */
function champFormulaire(entite, champ) {
  const nom = champ.nom
  const requis = champ.requis ? ' required' : ''
  const libelle = `${champ.libelle}${champ.requis ? '' : ' (facultatif)'}`
  let saisie
  if (champ.type === 'text') saisie = `<textarea id="${nom}" name="${nom}"${requis}>{{ old('${nom}') }}</textarea>`
  else if (champ.type === 'select') {
    saisie = php`<select id="${nom}" name="${nom}"${requis}>
                <option value="">Choisir…</option>
                @foreach (\App\Models${B}${entite.classe}::${constanteOptions(champ)} as $option)
                    <option value="{{ $option }}" @selected(old('${nom}') === $option)>{{ $option }}</option>
                @endforeach
            </select>`
  } else {
    const type = { string: 'text', tel: 'tel', email: 'email', date: 'date', time: 'time', integer: 'number' }[champ.type]
    const bornes = champ.type === 'integer' ? `${champ.min !== undefined ? ` min="${champ.min}"` : ''}${champ.max !== undefined ? ` max="${champ.max}"` : ''}` : ''
    const autres = champ.type === 'tel' ? ' autocomplete="tel" inputmode="tel"' : champ.type === 'email' ? ' autocomplete="email"' : ''
    saisie = `<input id="${nom}" name="${nom}" type="${type}" value="{{ old('${nom}') }}"${bornes}${autres}${requis}>`
  }
  return `        <div class="champ">
            <label for="${nom}">${libelle}</label>
            ${saisie}
            @error('${nom}') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>`
}

/** Valeur d'exemple pour les tests et les fabriques (en PHP). */
function valeurPhp(champ) {
  if (champ.type === 'integer') return String(champ.exemple ?? champ.min ?? 1)
  if (champ.type === 'date') {
    if (champ.contrainte === 'futur') return "now()->addDays(7)->toDateString()"
    if (champ.contrainte === 'passe') return "now()->subYears(8)->toDateString()"
    if (champ.contrainte?.startsWith('apres:')) return "now()->addDays(10)->toDateString()"
    return q(exemple(champ))
  }
  return q(exemple(champ))
}

export function fichiersLaravel(modele) {
  verifierModele(modele)
  const app = modele.application
  const e = modele.entite
  const C = e.classe
  const v = C.charAt(0).toLowerCase() + C.slice(1)
  const vs = `${v}s`
  const route = e.chemin
  const champs = e.champs
  const statuts = e.statuts
  const options = champs.filter((c) => c.type === 'select')
  const casts = champs.filter((c) => ['date', 'integer'].includes(c.type))
  const premierStatut = statuts[0].code
  const f = {}

  // ----------------------------------------------------------------------------------------------
  // Configuration et fichiers de départ
  f['composer.json'] = JSON.stringify(
    {
      name: 'kekeli/application',
      type: 'project',
      description: `${app.nom} : application web faite avec Kekeli.`,
      license: 'proprietary',
      require: { php: '^8.2', 'laravel/framework': '^12.0', 'laravel/tinker': '^2.10.1' },
      'require-dev': { 'fakerphp/faker': '^1.23', 'laravel/pint': '^1.24', 'mockery/mockery': '^1.6', 'nunomaduro/collision': '^8.6', 'phpunit/phpunit': '^11.5.50' },
      autoload: { 'psr-4': { 'App\\': 'app/', 'Database\\Factories\\': 'database/factories/', 'Database\\Seeders\\': 'database/seeders/' } },
      'autoload-dev': { 'psr-4': { 'Tests\\': 'tests/' } },
      scripts: {
        test: ['@php artisan config:clear --ansi', '@php artisan test'],
        'post-autoload-dump': ['Illuminate\\Foundation\\ComposerScripts::postAutoloadDump', '@php artisan package:discover --ansi'],
        'post-root-package-install': ["@php -r \"file_exists('.env') || copy('.env.example', '.env');\""],
      },
      extra: { laravel: { 'dont-discover': [] } },
      config: { 'optimize-autoloader': true, 'preferred-install': 'dist', 'sort-packages': true, 'allow-plugins': { 'php-http/discovery': true } },
      'minimum-stability': 'stable',
      'prefer-stable': true,
    },
    null,
    4,
  ) + '\n'

  f['.env.example'] = `# Réglages de l'application. Copier ce fichier en « .env » et le remplir.
# Ne jamais envoyer le fichier .env sur GitHub : il contient des secrets.
APP_NAME="${app.nom}"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=fr_FR
APP_TIMEZONE=Africa/Lome

# Adresse électronique de l'administrateur : le compte créé avec cette adresse devient administrateur.
ADMIN_EMAIL=

# Hébergeurs qui passent par un relais (Laravel Cloud, Render) : mettre * ; sinon laisser vide.
TRUSTED_PROXIES=

# Sur un hébergement mutualisé sans terminal : mettre « oui » le temps d'ouvrir /installation, puis « non ».
INSTALLATION_AUTORISEE=non

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

# Base de données : mysql (MySQL ou MariaDB), pgsql (PostgreSQL) ou sqlite.
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

# Envoi des courriels (mot de passe oublié) : réglages donnés par l'hébergeur.
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="\${APP_NAME}"
`

  f['.gitignore'] = `*.log
.DS_Store
.env
.env.backup
.env.production
.phpunit.result.cache
/.phpunit.cache
/.idea
/.vscode
/auth.json
/node_modules
/public/build
/public/hot
/public/storage
/storage/*.key
/vendor
`

  f['config/application.php'] = php`<?php

return [

    // Nom affiché de l'application et phrase d'accueil.
    'nom' => env('APP_NAME', ${q(app.nom)}),
    'accroche' => ${q(app.accroche)},

    // Le compte créé avec cette adresse devient administrateur (variable d'environnement ADMIN_EMAIL).
    'admin_email' => env('ADMIN_EMAIL'),

    // Hébergement mutualisé sans terminal : /installation crée les tables, seulement si « oui ».
    'installation_autorisee' => env('INSTALLATION_AUTORISEE', 'non') === 'oui',

];
`

  f['bootstrap/app.php'] = php`<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hébergeurs qui passent par un relais (Laravel Cloud, Render) : TRUSTED_PROXIES=*.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }
        $middleware->alias(['admin' => \App\Http\Middleware\EstAdministrateur::class]);
        $middleware->web(append: [\App\Http\Middleware\EnTetesDeSecurite::class]);
        $middleware->redirectGuestsTo(fn () => route('connexion'));
        $middleware->redirectUsersTo(fn () => route('espace'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
`

  f['.htaccess'] = `# Hébergement mutualisé (cPanel, Hostinger) quand tout le projet est dans public_html :
# toutes les adresses passent par le dossier public, les autres dossiers ne sont jamais montrés.
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(\\.env|\\.git|composer\\.(json|lock)|artisan)$ - [F,L]
    RewriteRule ^((?!public/).*)$ public/$1 [L]
</IfModule>
<FilesMatch "^\\.">
    Require all denied
</FilesMatch>
`

  f['public/css/app.css'] = FEUILLE_DE_STYLE

  f['app/Providers/AppServiceProvider.php'] = php`<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Pages de résultats simples, sans dépendance de style.
        Paginator::defaultView('pagination::default');
        Paginator::defaultSimpleView('pagination::simple-default');
    }
}
`

  // ----------------------------------------------------------------------------------------------
  // Base de données
  f['database/migrations/0001_01_01_000000_create_users_table.php'] = php`<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
`

  f[`database/migrations/2026_01_01_000000_create_${e.table}_table.php`] = php`<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('${e.table}', function (Blueprint $table) {
            $table->id();
            // Chaque ${e.singulier} appartient à un utilisateur : ses données restent séparées de celles des autres.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
${champs.map(colonne).join('\n')}
            $table->string('statut', 30)->default('${premierStatut}')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('${e.table}');
    }
};
`

  // ----------------------------------------------------------------------------------------------
  // Modèles
  f['app/Models/User.php'] = php`<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** « is_admin » n'est jamais rempli par un formulaire. */
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // Le mot de passe est toujours chiffré avant d'être enregistré.
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function ${vs}(): HasMany
    {
        return $this->hasMany(${C}::class);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }
}
`

  f[`app/Models/${C}.php`] = php`<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ${C} extends Model
{
    use HasFactory;

    /** États possibles, dans l'ordre, avec leur nom affiché. */
    public const STATUTS = [
${statuts.map((s) => `        ${q(s.code)} => ${q(s.libelle)},`).join('\n')}
    ];
${options.map((c) => `
    /** Choix possibles pour « ${c.libelle} ». */
    public const ${constanteOptions(c)} = [${c.options.map(q).join(', ')}];
`).join('')}
    /** Champs remplis par le formulaire (jamais « user_id » ni « statut »). */
    protected $fillable = [${champs.map((c) => q(c.nom)).join(', ')}];

    protected function casts(): array
    {
        return [
${casts.map((c) => `            ${q(c.nom)} => ${q(c.type === 'date' ? 'date' : 'integer')},`).join('\n')}
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    /** Numéro de l'état (0 pour le premier), pour la couleur de l'étiquette. */
    public function rangStatut(): int
    {
        return (int) array_search($this->statut, array_keys(self::STATUTS), true);
    }
}
`

  // ----------------------------------------------------------------------------------------------
  // Accès : intermédiaires et politique
  f['app/Http/Middleware/EstAdministrateur.php'] = php`<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** L'espace administrateur est réservé aux comptes administrateurs. */
class EstAdministrateur
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return $next($request);
    }
}
`

  f['app/Http/Middleware/EnTetesDeSecurite.php'] = php`<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** En-têtes de sécurité ajoutés à chaque page. */
class EnTetesDeSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
`

  f[`app/Policies/${C}Policy.php`] = php`<?php

namespace App\Policies;

use App\Models${B}${C};
use App\Models\User;

/** Un utilisateur ne voit que ses propres ${e.pluriel} ; l'administrateur les voit toutes. */
class ${C}Policy
{
    public function view(User $user, ${C} $${v}): bool
    {
        return $user->isAdmin() || $${v}->user_id === $user->id;
    }

    public function update(User $user, ${C} $${v}): bool
    {
        return $user->isAdmin();
    }
}
`

  // ----------------------------------------------------------------------------------------------
  // Contrôleurs : comptes
  f['app/Http/Controllers/Auth/CreationCompteController.php'] = php`<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class CreationCompteController extends Controller
{
    public function create(): View
    {
        return view('auth.inscription');
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($donnees);

        // L'adresse choisie par le propriétaire (ADMIN_EMAIL) donne le rôle d'administrateur.
        $admin = config('application.admin_email');
        if ($admin && strcasecmp($admin, $user->email) === 0) {
            $user->forceFill(['is_admin' => true])->save();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('espace')->with('succes', 'Bienvenue ! Votre compte est créé.');
    }
}
`

  f['app/Http/Controllers/Auth/ConnexionController.php'] = php`<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConnexionController extends Controller
{
    public function create(): View
    {
        return view('auth.connexion');
    }

    public function store(Request $request): RedirectResponse
    {
        $identifiants = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Cinq essais par minute au plus pour une même adresse et un même appareil.
        $cle = Str::transliterate(Str::lower($identifiants['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($cle, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Trop d\'essais. Réessayez dans '.RateLimiter::availableIn($cle).' secondes.',
            ]);
        }

        if (! Auth::attempt($identifiants, $request->boolean('remember'))) {
            RateLimiter::hit($cle);

            throw ValidationException::withMessages(['email' => 'Adresse électronique ou mot de passe incorrect.']);
        }

        RateLimiter::clear($cle);
        $request->session()->regenerate();

        return redirect()->intended(route('espace'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('accueil');
    }
}
`

  f['app/Http/Controllers/Auth/MotDePasseController.php'] = php`<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as RegleMotDePasse;
use Illuminate\View\View;

class MotDePasseController extends Controller
{
    public function demande(): View
    {
        return view('auth.mot-de-passe-oublie');
    }

    public function envoyer(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        // Même réponse que l'adresse existe ou non : on ne révèle pas qui a un compte.
        return back()->with('succes', 'Si un compte existe avec cette adresse, un lien pour choisir un nouveau mot de passe vient d\'être envoyé.');
    }

    public function nouveau(Request $request, string $token): View
    {
        return view('auth.nouveau-mot-de-passe', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function enregistrer(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', RegleMotDePasse::min(8)],
        ]);

        $etat = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        return $etat === Password::PASSWORD_RESET
            ? redirect()->route('connexion')->with('succes', 'Votre mot de passe est changé. Vous pouvez vous connecter.')
            : back()->withErrors(['email' => 'Ce lien n\'est plus valable. Demandez-en un nouveau.']);
    }
}
`

  // ----------------------------------------------------------------------------------------------
  // Contrôleurs : espace de l'utilisateur et administration
  f['app/Http/Controllers/EspaceController.php'] = php`<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class EspaceController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Seulement les ${e.pluriel} de l'utilisateur connecté.
        return view('espace', ['${vs}' => $request->user()->${vs}()->latest()->get()]);
    }
}
`

  f[`app/Http/Controllers/${C}Controller.php`] = php`<?php

namespace App\Http\Controllers;

use App\Http\Requests\Store${C}Request;
use App\Models${B}${C};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ${C}Controller extends Controller
{
    public function create(): View
    {
        return view('${route}.create');
    }

    public function store(Store${C}Request $request): RedirectResponse
    {
        // L'utilisateur vient de la session, jamais du formulaire.
        $${v} = $request->user()->${vs}()->create($request->validated());

        return redirect()->route('${route}.show', $${v})->with('succes', ${q(`${majuscule(le(e))} est ${accord(e, 'enregistré')}. Nous vous tiendrons au courant.`)});
    }

    public function show(${C} $${v}): View
    {
        // Un utilisateur ne peut pas voir ${un(e)} qui ne lui appartient pas (réponse 404).
        abort_unless(Gate::allows('view', $${v}), 404);

        return view('${route}.show', ['${v}' => $${v}]);
    }
}
`

  f[`app/Http/Requests/Store${C}Request.php`] = php`<?php

namespace App\Http\Requests;

use App\Models${B}${C};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Store${C}Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
${champs.map((c) => `            ${q(c.nom)} => ${regles(e, c)},`).join('\n')}
        ];
    }

    public function attributes(): array
    {
        return [
${champs.map((c) => `            ${q(c.nom)} => ${q(c.libelle.toLowerCase())},`).join('\n')}
        ];
    }
}
`

  f['app/Http/Controllers/Admin/TableauDeBordController.php'] = php`<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models${B}${C};
use App\Models\User;
use Illuminate\View\View;

class TableauDeBordController extends Controller
{
    public function __invoke(): View
    {
        $parStatut = ${C}::query()->selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut');

        return view('admin.tableau', [
            'utilisateurs' => User::count(),
            'total' => ${C}::count(),
            'parStatut' => $parStatut,
            'derniers' => ${C}::with('user')->latest()->limit(5)->get(),
        ]);
    }
}
`

  f[`app/Http/Controllers/Admin/${C}Controller.php`] = php`<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models${B}${C};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ${C}Controller extends Controller
{
    public function index(Request $request): View
    {
        $statut = $request->query('statut');
        $${vs} = ${C}::with('user')
            ->when(array_key_exists((string) $statut, ${C}::STATUTS), fn ($requete) => $requete->where('statut', $statut))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.${route}.index', ['${vs}' => $${vs}, 'statut' => $statut]);
    }

    public function show(${C} $${v}): View
    {
        return view('admin.${route}.show', ['${v}' => $${v}->load('user')]);
    }

    public function update(Request $request, ${C} $${v}): RedirectResponse
    {
        $donnees = $request->validate(['statut' => ['required', Rule::in(array_keys(${C}::STATUTS))]]);
        $${v}->forceFill($donnees)->save();

        return back()->with('succes', 'L\'état est mis à jour.');
    }
}
`

  f['app/Http/Controllers/Admin/UtilisateurController.php'] = php`<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class UtilisateurController extends Controller
{
    public function index(): View
    {
        return view('admin.utilisateurs', ['utilisateurs' => User::withCount('${vs}')->latest()->paginate(30)]);
    }
}
`

  f['app/Http/Controllers/InstallationController.php'] = php`<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Hébergement mutualisé sans terminal : crée les tables de la base de données.
 * Ne fonctionne que si INSTALLATION_AUTORISEE=oui ; à remettre à « non » juste après.
 */
class InstallationController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(config('application.installation_autorisee'), 404);

        Artisan::call('migrate', ['--force' => true]);

        return view('installation', ['sortie' => Artisan::output()]);
    }
}
`

  // ----------------------------------------------------------------------------------------------
  // Adresses
  f['routes/web.php'] = php`<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\ConnexionController;
use App\Http\Controllers\Auth\CreationCompteController;
use App\Http\Controllers\Auth\MotDePasseController;
use App\Http\Controllers\EspaceController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers${B}${C}Controller;
use Illuminate\Support\Facades\Route;

Route::view('/', 'accueil')->name('accueil');
Route::get('/installation', InstallationController::class)->name('installation');

Route::middleware('guest')->group(function () {
    Route::get('/inscription', [CreationCompteController::class, 'create'])->name('inscription');
    Route::post('/inscription', [CreationCompteController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/connexion', [ConnexionController::class, 'create'])->name('connexion');
    Route::post('/connexion', [ConnexionController::class, 'store']);
    Route::get('/mot-de-passe-oublie', [MotDePasseController::class, 'demande'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [MotDePasseController::class, 'envoyer'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/nouveau-mot-de-passe/{token}', [MotDePasseController::class, 'nouveau'])->name('password.reset');
    Route::post('/nouveau-mot-de-passe', [MotDePasseController::class, 'enregistrer'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [ConnexionController::class, 'destroy'])->name('deconnexion');
    Route::get('/espace', EspaceController::class)->name('espace');
    Route::get('/espace/${route}/nouveau', [${C}Controller::class, 'create'])->name('${route}.create');
    Route::post('/espace/${route}', [${C}Controller::class, 'store'])->middleware('throttle:30,1')->name('${route}.store');
    Route::get('/espace/${route}/{${v}}', [${C}Controller::class, 'show'])->name('${route}.show');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\TableauDeBordController::class)->name('tableau');
    Route::get('/${route}', [Admin${B}${C}Controller::class, 'index'])->name('${route}.index');
    Route::get('/${route}/{${v}}', [Admin${B}${C}Controller::class, 'show'])->name('${route}.show');
    Route::patch('/${route}/{${v}}', [Admin${B}${C}Controller::class, 'update'])->name('${route}.update');
    Route::get('/utilisateurs', [Admin\UtilisateurController::class, 'index'])->name('utilisateurs');
});
`

  f['routes/console.php'] = php`<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

// Donne le rôle d'administrateur à un compte existant : php artisan app:admin adresse@exemple.com
Artisan::command('app:admin {email}', function (string $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        $this->error('Aucun compte avec cette adresse. Créez d\'abord le compte sur le site.');

        return 1;
    }
    $user->forceFill(['is_admin' => true])->save();
    $this->info("Le compte {$email} est maintenant administrateur.");

    return 0;
})->purpose('Donner le rôle d\'administrateur à un compte');
`

  // ----------------------------------------------------------------------------------------------
  // Vues
  f['resources/views/layouts/app.blade.php'] = php`<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre', config('application.nom')) · {{ config('application.nom') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="entete">
    <div class="conteneur">
        <a class="marque" href="{{ route('accueil') }}">{{ config('application.nom') }}</a>
        <nav class="navigation" aria-label="Menu principal">
            @auth
                <a href="{{ route('espace') }}">Mon espace</a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.tableau') }}">Administration</a>
                @endif
                <form method="POST" action="{{ route('deconnexion') }}">
                    @csrf
                    <button type="submit" class="lien-bouton">Se déconnecter</button>
                </form>
            @else
                <a href="{{ route('connexion') }}">Se connecter</a>
                <a href="{{ route('inscription') }}">Créer un compte</a>
            @endauth
        </nav>
    </div>
</header>
<main>
    <div class="conteneur">
        @if (session('succes'))
            <p class="message succes" role="status">{{ session('succes') }}</p>
        @endif
        @yield('contenu')
    </div>
</main>
<footer class="pied">
    <div class="conteneur">© {{ date('Y') }} {{ config('application.nom') }}</div>
</footer>
</body>
</html>
`

  f['resources/views/accueil.blade.php'] = php`@extends('layouts.app')

@section('titre', 'Accueil')

@section('contenu')
    <section class="heros">
        <h1>{{ config('application.nom') }}</h1>
        <p class="accroche">{{ config('application.accroche') }}</p>
        <div class="actions">
            @auth
                <a class="bouton" href="{{ route('${route}.create') }}">${icone('plus')} ${e.action}</a>
                <a class="bouton secondaire" href="{{ route('espace') }}">Mon espace</a>
            @else
                <a class="bouton" href="{{ route('inscription') }}">${icone('utilisateur')} Créer un compte</a>
                <a class="bouton secondaire" href="{{ route('connexion') }}">Se connecter</a>
            @endauth
        </div>
    </section>
    <section class="grille">
        <div class="carte">
            <h2>1. Créez votre compte</h2>
            <p>Une adresse électronique et un mot de passe suffisent.</p>
        </div>
        <div class="carte">
            <h2>2. ${e.action}</h2>
            <p>Remplissez le formulaire depuis votre téléphone, en quelques secondes.</p>
        </div>
        <div class="carte">
            <h2>3. Suivez-${e.feminin ? 'la' : 'le'} depuis votre espace</h2>
            <p>Vous voyez à tout moment où en est ${le(e)}.</p>
        </div>
    </section>
@endsection
`

  f['resources/views/auth/inscription.blade.php'] = php`@extends('layouts.app')

@section('titre', 'Créer un compte')

@section('contenu')
    <h1>Créer un compte</h1>
    <form method="POST" action="{{ route('inscription') }}" class="carte">
        @csrf
        <div class="champ">
            <label for="name">Nom et prénoms</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required>
            @error('name') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ">
            <label for="email">Adresse électronique</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
            @error('email') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ">
            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
            <p class="aide">Au moins 8 caractères.</p>
            @error('password') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ">
            <label for="password_confirmation">Confirmer le mot de passe</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>
        <button type="submit" class="bouton">Créer mon compte</button>
    </form>
    <p>Vous avez déjà un compte ? <a href="{{ route('connexion') }}">Se connecter</a></p>
@endsection
`

  f['resources/views/auth/connexion.blade.php'] = php`@extends('layouts.app')

@section('titre', 'Se connecter')

@section('contenu')
    <h1>Se connecter</h1>
    <form method="POST" action="{{ route('connexion') }}" class="carte">
        @csrf
        <div class="champ">
            <label for="email">Adresse électronique</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            @error('email') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ">
            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            @error('password') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ">
            <label class="case"><input type="checkbox" name="remember" value="1"> Rester connecté</label>
        </div>
        <button type="submit" class="bouton">Se connecter</button>
    </form>
    <p><a href="{{ route('password.request') }}">Mot de passe oublié ?</a></p>
    <p>Pas encore de compte ? <a href="{{ route('inscription') }}">Créer un compte</a></p>
@endsection
`

  f['resources/views/auth/mot-de-passe-oublie.blade.php'] = php`@extends('layouts.app')

@section('titre', 'Mot de passe oublié')

@section('contenu')
    <h1>Mot de passe oublié</h1>
    <p>Indiquez votre adresse électronique : nous vous envoyons un lien pour choisir un nouveau mot de passe.</p>
    <form method="POST" action="{{ route('password.email') }}" class="carte">
        @csrf
        <div class="champ">
            <label for="email">Adresse électronique</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
            @error('email') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="bouton">Recevoir le lien</button>
    </form>
@endsection
`

  f['resources/views/auth/nouveau-mot-de-passe.blade.php'] = php`@extends('layouts.app')

@section('titre', 'Nouveau mot de passe')

@section('contenu')
    <h1>Nouveau mot de passe</h1>
    <form method="POST" action="{{ route('password.update') }}" class="carte">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="champ">
            <label for="email">Adresse électronique</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>
            @error('email') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ">
            <label for="password">Nouveau mot de passe</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
            @error('password') <p class="erreur-champ">{{ $message }}</p> @enderror
        </div>
        <div class="champ">
            <label for="password_confirmation">Confirmer le mot de passe</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>
        <button type="submit" class="bouton">Enregistrer</button>
    </form>
@endsection
`

  const etiquette = (variable) => `<span class="statut s{{ $${variable}->rangStatut() }}">{{ $${variable}->libelleStatut() }}</span>`
  const resume = (variable) => champs.slice(0, 2).map((c) => affichage(variable, c)).join(' · ')

  f['resources/views/espace.blade.php'] = php`@extends('layouts.app')

@section('titre', 'Mon espace')

@section('contenu')
    <h1>Bonjour {{ auth()->user()->name }}</h1>
    <div class="actions">
        <a class="bouton" href="{{ route('${route}.create') }}">${icone('plus')} ${e.action}</a>
    </div>
    <h2>${mes(e)}</h2>
    @if ($${vs}->isEmpty())
        <p class="carte">Vous n'avez encore ${aucun(e)}. Touchez « ${e.action} » pour commencer.</p>
    @else
        <ul class="liste carte">
            @foreach ($${vs} as $${v})
                <li>
                    <a href="{{ route('${route}.show', $${v}) }}">${resume(v)}</a>
                    ${etiquette(v)}
                </li>
            @endforeach
        </ul>
    @endif
@endsection
`

  f[`resources/views/${route}/create.blade.php`] = php`@extends('layouts.app')

@section('titre', ${q(e.action)})

@section('contenu')
    <h1>${e.action}</h1>
    <form method="POST" action="{{ route('${route}.store') }}" class="carte">
        @csrf
${champs.map((c) => champFormulaire(e, c)).join('\n')}
        <button type="submit" class="bouton">Envoyer</button>
    </form>
@endsection
`

  const lignesDetails = (variable) => champs.map((c) => `        <dt>${c.libelle}</dt><dd>${affichage(variable, c)}</dd>`).join('\n')

  f[`resources/views/${route}/show.blade.php`] = php`@extends('layouts.app')

@section('titre', ${q(majuscule(e.singulier))})

@section('contenu')
    <h1>${majuscule(e.singulier)} du {{ $${v}->created_at->format('d/m/Y') }}</h1>
    <p>État : ${etiquette(v)}</p>
    <dl class="details carte">
${lignesDetails(v)}
    </dl>
    <p><a href="{{ route('espace') }}">← Retour à mon espace</a></p>
@endsection
`

  f['resources/views/admin/tableau.blade.php'] = php`@extends('layouts.app')

@section('titre', 'Administration')

@section('contenu')
    <h1>${icone('bouclier')} Administration</h1>
    <div class="chiffres">
        <div class="carte"><p class="chiffre">{{ $total }}</p><p>${majuscule(e.pluriel)} au total</p></div>
        @foreach (\App\Models${B}${C}::STATUTS as $code => $libelle)
            <div class="carte"><p class="chiffre">{{ $parStatut[$code] ?? 0 }}</p><p>{{ $libelle }}</p></div>
        @endforeach
        <div class="carte"><p class="chiffre">{{ $utilisateurs }}</p><p>Comptes</p></div>
    </div>
    <div class="actions">
        <a class="bouton" href="{{ route('admin.${route}.index') }}">${icone('liste')} ${majuscule(tous(e))}</a>
        <a class="bouton secondaire" href="{{ route('admin.utilisateurs') }}">Comptes</a>
    </div>
    <h2>${e.feminin ? 'Dernières' : 'Derniers'} ${e.pluriel}</h2>
    <ul class="liste carte">
        @forelse ($derniers as $${v})
            <li><a href="{{ route('admin.${route}.show', $${v}) }}">{{ $${v}->user->name }} · ${resume(v)}</a> ${etiquette(v)}</li>
        @empty
            <li>Rien pour le moment.</li>
        @endforelse
    </ul>
@endsection
`

  f[`resources/views/admin/${route}/index.blade.php`] = php`@extends('layouts.app')

@section('titre', ${q(majuscule(e.pluriel))})

@section('contenu')
    <h1>${majuscule(e.pluriel)}</h1>
    <nav class="filtres" aria-label="Filtrer par état">
        <a href="{{ route('admin.${route}.index') }}" @class(['actif' => ! $statut])>${e.feminin ? 'Toutes' : 'Tous'}</a>
        @foreach (\App\Models${B}${C}::STATUTS as $code => $libelle)
            <a href="{{ route('admin.${route}.index', ['statut' => $code]) }}" @class(['actif' => $statut === $code])>{{ $libelle }}</a>
        @endforeach
    </nav>
    <div class="defilement carte">
        <table class="tableau">
            <thead>
                <tr><th>Client</th>${champs.slice(0, 2).map((c) => `<th>${c.libelle}</th>`).join('')}<th>État</th></tr>
            </thead>
            <tbody>
                @forelse ($${vs} as $${v})
                    <tr>
                        <td><a href="{{ route('admin.${route}.show', $${v}) }}">{{ $${v}->user->name }}</a></td>
                        ${champs.slice(0, 2).map((c) => `<td>${affichage(v, c)}</td>`).join('')}
                        <td>${etiquette(v)}</td>
                    </tr>
                @empty
                    <tr><td colspan="${Math.min(2, champs.length) + 2}">${majuscule(aucun(e))} pour le moment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $${vs}->links() }}
@endsection
`

  f[`resources/views/admin/${route}/show.blade.php`] = php`@extends('layouts.app')

@section('titre', ${q(majuscule(e.singulier))})

@section('contenu')
    <h1>${majuscule(e.singulier)} de {{ $${v}->user->name }}</h1>
    <dl class="details carte">
        <dt>Client</dt><dd>{{ $${v}->user->name }} ({{ $${v}->user->email }})</dd>
        <dt>${accord(e, 'Reçu')} le</dt><dd>{{ $${v}->created_at->format('d/m/Y à H:i') }}</dd>
${lignesDetails(v)}
    </dl>
    <form method="POST" action="{{ route('admin.${route}.update', $${v}) }}" class="carte">
        @csrf
        @method('PATCH')
        <div class="champ">
            <label for="statut">État</label>
            <select id="statut" name="statut">
                @foreach (\App\Models${B}${C}::STATUTS as $code => $libelle)
                    <option value="{{ $code }}" @selected($${v}->statut === $code)>{{ $libelle }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bouton">Enregistrer l'état</button>
    </form>
    <p><a href="{{ route('admin.${route}.index') }}">← ${majuscule(tous(e))}</a></p>
@endsection
`

  f['resources/views/admin/utilisateurs.blade.php'] = php`@extends('layouts.app')

@section('titre', 'Comptes')

@section('contenu')
    <h1>Comptes</h1>
    <div class="defilement carte">
        <table class="tableau">
            <thead><tr><th>Nom</th><th>Adresse électronique</th><th>${majuscule(e.pluriel)}</th><th>Rôle</th></tr></thead>
            <tbody>
                @foreach ($utilisateurs as $utilisateur)
                    <tr>
                        <td>{{ $utilisateur->name }}</td>
                        <td>{{ $utilisateur->email }}</td>
                        <td>{{ $utilisateur->${vs}_count }}</td>
                        <td>{{ $utilisateur->isAdmin() ? 'Administrateur' : 'Client' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $utilisateurs->links() }}
@endsection
`

  f['resources/views/installation.blade.php'] = php`@extends('layouts.app')

@section('titre', 'Installation')

@section('contenu')
    <h1>Installation terminée</h1>
    <p class="message succes">Les tables de la base de données sont prêtes.</p>
    <p><strong>Important :</strong> remettez maintenant INSTALLATION_AUTORISEE=non dans le fichier .env de l'hébergement.</p>
    <pre class="carte defilement">{{ $sortie }}</pre>
@endsection
`

  // ----------------------------------------------------------------------------------------------
  // Fabriques, données d'essai et tests
  f['database/factories/UserFactory.php'] = php`<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // Mot de passe tiré au hasard pour chaque compte d'essai : jamais un mot de passe écrit dans le code.
            'password' => Str::password(16),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true]);
    }
}
`

  f[`database/factories/${C}Factory.php`] = php`<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models${B}${C}>
 */
class ${C}Factory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
${champs.map((c) => `            ${q(c.nom)} => ${valeurPhp(c)},`).join('\n')}
        ];
    }
}
`

  f['database/seeders/DatabaseSeeder.php'] = php`<?php

namespace Database\Seeders;

use App\Models${B}${C};
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Données d'essai, seulement pour l'aperçu dans Kekeli (jamais en production) : un administrateur dont
 * l'adresse et le mot de passe viennent des variables d'environnement, et un client d'exemple.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        if (($email = env('ESSAI_ADMIN_EMAIL')) && ($motDePasse = env('ESSAI_ADMIN_MOT_DE_PASSE'))) {
            User::updateOrCreate(['email' => $email], ['name' => 'Administrateur (essai)', 'password' => $motDePasse])
                ->forceFill(['is_admin' => true])->save();
        }

        if (${C}::count() === 0) {
            ${C}::factory()->count(3)->for(User::factory()->state(['name' => 'Client d\'exemple']))->create();
        }
    }
}
`

  f['tests/Feature/ComptesTest.php'] = php`<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ComptesTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_pages_publiques_s_ouvrent(): void
    {
        $this->get('/')->assertOk()->assertSee(config('application.nom'));
        $this->get('/inscription')->assertOk();
        $this->get('/connexion')->assertOk();
        $this->get('/mot-de-passe-oublie')->assertOk();
    }

    public function test_un_visiteur_cree_un_compte_et_le_mot_de_passe_est_chiffre(): void
    {
        $this->post('/inscription', [
            'name' => 'Afi Essai',
            'email' => 'afi@exemple.test',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
        ])->assertRedirect('/espace');

        $user = User::where('email', 'afi@exemple.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame('motdepasse-solide', $user->password);
        $this->assertTrue(Hash::check('motdepasse-solide', $user->password));
        $this->assertFalse($user->isAdmin());
    }

    public function test_l_adresse_de_l_administrateur_donne_le_role_d_administrateur(): void
    {
        config(['application.admin_email' => 'patron@exemple.test']);

        $this->post('/inscription', [
            'name' => 'Patron',
            'email' => 'patron@exemple.test',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
        ]);

        $this->assertTrue(User::where('email', 'patron@exemple.test')->firstOrFail()->isAdmin());
    }

    public function test_on_ne_peut_pas_se_donner_le_role_d_administrateur_par_le_formulaire(): void
    {
        $this->post('/inscription', [
            'name' => 'Pirate',
            'email' => 'pirate@exemple.test',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'is_admin' => 1,
        ]);

        $this->assertFalse(User::where('email', 'pirate@exemple.test')->firstOrFail()->isAdmin());
    }

    public function test_connexion_et_deconnexion(): void
    {
        $user = User::factory()->create(['password' => 'motdepasse-solide']);

        $this->post('/connexion', ['email' => $user->email, 'password' => 'mauvais'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/connexion', ['email' => $user->email, 'password' => 'motdepasse-solide'])->assertRedirect('/espace');
        $this->assertAuthenticatedAs($user);

        $this->post('/deconnexion')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_les_essais_de_connexion_sont_limites(): void
    {
        $user = User::factory()->create(['password' => 'motdepasse-solide']);

        foreach (range(1, 5) as $essai) {
            $this->post('/connexion', ['email' => $user->email, 'password' => 'mauvais']);
        }

        $this->post('/connexion', ['email' => $user->email, 'password' => 'motdepasse-solide'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_les_formulaires_sont_proteges(): void
    {
        $this->get('/connexion')->assertSee('name="_token"', false);
        $this->get('/inscription')->assertSee('name="_token"', false);
    }

    public function test_l_espace_est_reserve_aux_comptes(): void
    {
        $this->get('/espace')->assertRedirect('/connexion');
        $this->get('/admin')->assertRedirect('/connexion');
    }
}
`

  const donneesValides = champs.map((c) => `            ${q(c.nom)} => ${valeurPhp(c)},`).join('\n')
  const premierRequis = champs.find((c) => c.requis)

  f[`tests/Feature/${C}Test.php`] = php`<?php

namespace Tests\Feature;

use App\Models${B}${C};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ${C}Test extends TestCase
{
    use RefreshDatabase;

    private function donnees(array $changements = []): array
    {
        return array_merge([
${donneesValides}
        ], $changements);
    }

    public function test_un_client_envoie_le_formulaire_et_retrouve_ses_${e.table}(): void
    {
        $user = User::factory()->create();

        $reponse = $this->actingAs($user)->post('/espace/${route}', $this->donnees());

        $${v} = ${C}::sole();
        $reponse->assertRedirect(route('${route}.show', $${v}));
        $this->assertSame($user->id, $${v}->user_id);
        $this->assertSame(${q(premierStatut)}, $${v}->statut);
        $this->actingAs($user)->get('/espace')->assertOk()->assertSee(route('${route}.show', $${v}), false);
        $this->actingAs($user)->get(route('${route}.show', $${v}))->assertOk()->assertSee(${q(statuts[0].libelle)});
    }

    public function test_le_formulaire_refuse_les_donnees_incompletes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/espace/${route}', $this->donnees([${q(premierRequis.nom)} => '']))->assertSessionHasErrors(${q(premierRequis.nom)});
        $this->assertSame(0, ${C}::count());
    }

    public function test_le_client_ne_peut_choisir_ni_l_etat_ni_le_proprietaire(): void
    {
        $user = User::factory()->create();
        $autre = User::factory()->create();

        $this->actingAs($user)->post('/espace/${route}', $this->donnees(['statut' => ${q(statuts[statuts.length - 1].code)}, 'user_id' => $autre->id]));

        $${v} = ${C}::sole();
        $this->assertSame($user->id, $${v}->user_id);
        $this->assertSame(${q(premierStatut)}, $${v}->statut);
    }

    public function test_il_faut_un_compte_pour_envoyer_le_formulaire(): void
    {
        $this->post('/espace/${route}', $this->donnees())->assertRedirect('/connexion');
        $this->assertSame(0, ${C}::count());
    }
}
`

  f['tests/Feature/SeparationDesDonneesTest.php'] = php`<?php

namespace Tests\Feature;

use App\Models${B}${C};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Chaque utilisateur ne voit que ses propres données. */
class SeparationDesDonneesTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_client_ne_voit_jamais_les_${e.table}_d_un_autre(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $${v}DeBob = ${C}::factory()->for($bob)->create();

        $this->actingAs($alice)->get(route('${route}.show', $${v}DeBob))->assertNotFound();
        $this->actingAs($alice)->get('/espace')->assertDontSee(route('${route}.show', $${v}DeBob), false);
        $this->actingAs($bob)->get(route('${route}.show', $${v}DeBob))->assertOk();
    }

    public function test_un_client_n_entre_pas_dans_l_administration(): void
    {
        $client = User::factory()->create();
        $${v} = ${C}::factory()->create();

        $this->actingAs($client)->get('/admin')->assertForbidden();
        $this->actingAs($client)->get('/admin/${route}')->assertForbidden();
        $this->actingAs($client)->patch(route('admin.${route}.update', $${v}), ['statut' => ${q(statuts[1].code)}])->assertForbidden();
        $this->assertSame(${q(premierStatut)}, $${v}->fresh()->statut);
    }
}
`

  f['tests/Feature/AdministrationTest.php'] = php`<?php

namespace Tests\Feature;

use App\Models${B}${C};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_administrateur_voit_tout_et_change_l_etat(): void
    {
        $admin = User::factory()->admin()->create();
        $${v} = ${C}::factory()->create();

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Administration');
        $this->actingAs($admin)->get('/admin/${route}')->assertOk()->assertSee($${v}->user->name);
        $this->actingAs($admin)->get('/admin/utilisateurs')->assertOk()->assertSee($${v}->user->email);
        $this->actingAs($admin)->get(route('admin.${route}.show', $${v}))->assertOk();

        $this->actingAs($admin)->patch(route('admin.${route}.update', $${v}), ['statut' => ${q(statuts[1].code)}])->assertRedirect();
        $this->assertSame(${q(statuts[1].code)}, $${v}->fresh()->statut);

        $this->actingAs($admin)->patch(route('admin.${route}.update', $${v}), ['statut' => 'inconnu'])->assertSessionHasErrors('statut');
    }

    public function test_la_page_d_installation_est_fermee_par_defaut(): void
    {
        $this->get('/installation')->assertNotFound();
    }
}
`

  f['tests/Unit/.gitkeep'] = ''

  // ----------------------------------------------------------------------------------------------
  // Traductions des messages d'erreur (copiées par le Runner depuis le site Kekeli) et documentation
  f['README.md'] = `# ${app.nom}

Application web faite avec Kekeli (Laravel ${'12'}, PHP 8.2 ou plus récent).

## Ce qu'elle fait
- Comptes : création, connexion, mot de passe oublié (mots de passe chiffrés, essais de connexion limités).
- ${e.action} : formulaire enregistré dans la base de données ; chaque client ne voit que ses ${e.pluriel}.
- Administration (\`/admin\`) : toutes les ${e.pluriel}, leur état, les comptes.

## L'essayer sur un ordinateur
\`\`\`bash
composer install
cp .env.example .env
php artisan key:generate
# Base de données simple pour essayer : DB_CONNECTION=sqlite dans .env, puis :
touch database/database.sqlite
php artisan migrate
php artisan serve
\`\`\`

## Tests
\`\`\`bash
php artisan test
\`\`\`

## Administrateur
Mettre l'adresse du propriétaire dans la variable \`ADMIN_EMAIL\`, puis créer le compte avec cette adresse sur le site.
Ou, avec un terminal : \`php artisan app:admin adresse@exemple.com\`.

## Mise en ligne
Kekeli donne un guide pas à pas pour chaque hébergeur (Laravel Cloud, Hostinger, Render, hébergement mutualisé cPanel).
Aucun mot de passe ni aucune clé ne doit être écrit dans le code : ils vont dans les réglages de l'hébergeur ou dans le fichier \`.env\` du serveur.
`

  // Fichiers pour les hébergeurs (Render : conteneur ; les autres n'ont besoin de rien de plus).
  f['Dockerfile'] = `# Image pour les hébergeurs qui lancent un conteneur (par exemple Render).
FROM dunglas/frankenphp:1-php8.3
RUN install-php-extensions pdo_mysql pdo_pgsql intl zip bcmath opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . /app
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \\
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \\
    && chown -R www-data:www-data storage bootstrap/cache
ENV SERVER_NAME=":\${PORT:-8080}"
# Les tables sont créées ou mises à jour à chaque démarrage, puis le serveur se lance.
CMD ["sh", "-c", "php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && frankenphp php-server --root public --listen :\${PORT:-8080}"]
`

  f['.dockerignore'] = `.env
.git
vendor
node_modules
storage/logs/*
database/*.sqlite
`

  f['render.yaml'] = `# Render : « New » → « Blueprint » → choisir ce dépôt. Les secrets se remplissent dans Render, jamais ici.
services:
  - type: web
    name: ${modele.slug}
    runtime: docker
    plan: free
    healthCheckPath: /up
    envVars:
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: "false"
      - key: APP_KEY
        sync: false
      - key: APP_URL
        sync: false
      - key: ADMIN_EMAIL
        sync: false
      - key: TRUSTED_PROXIES
        value: "*"
      - key: LOG_CHANNEL
        value: stderr
      - key: DB_CONNECTION
        value: pgsql
      - key: DB_URL
        fromDatabase:
          name: ${modele.slug}-base
          property: connectionString
databases:
  - name: ${modele.slug}-base
    plan: free
`

  return f
}

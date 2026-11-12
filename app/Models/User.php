<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'country',
        'appearance',
    ];

    /** Choices of « Réglages → Apparence ». */
    public const APPEARANCES = ['system', 'light', 'dark'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'github_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'energy_window_started_at' => 'datetime',
            'plan_expires_at' => 'datetime',
            'github_token' => 'encrypted',
            'terms_accepted_at' => 'datetime',
        ];
    }

    /**
     * Country of the client, used to adapt images, currency and phone numbers (null until chosen).
     *
     * @return array{code: string, name: string, region: string, city: string, english: string, currency: string, phone: string}|null
     */
    public function countryDetails(): ?array
    {
        $country = config('kekeli.countries.'.$this->country);

        return $this->country && is_array($country) ? ['code' => $this->country, ...$country] : null;
    }

    /**
     * Get the Mobile Money payments sent by the user (screenshots to validate).
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    /**
     * Get the projects owned by the user.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
}

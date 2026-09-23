<?php

namespace App\Models;

use App\Contracts\ProductionActor;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $phone
 * @property string|null $avatar_path
 * @property UserStatus $status
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $name
 */
#[Fillable(['first_name', 'last_name', 'email', 'password', 'phone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements ProductionActor
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $appends = ['name'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Full name, kept for compatibility with UI built against a single "name" field.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    /** @return HasOne<AthleteProfile, $this> */
    public function athleteProfile(): HasOne
    {
        return $this->hasOne(AthleteProfile::class);
    }

    /**
     * The canonical sporting identity, if linked — see
     * docs/adr/0004-athlete-canonical-identity.md. Distinct from
     * `athleteProfile` (public presentation, always User-owned); a User can
     * have an Athlete with no AthleteProfile and vice versa is impossible
     * (AthleteProfile requires a User by construction).
     *
     * @return HasOne<Athlete, $this>
     */
    public function athlete(): HasOne
    {
        return $this->hasOne(Athlete::class);
    }

    /** @return HasOne<LegacyId, $this> */
    public function legacyId(): HasOne
    {
        return $this->hasOne(LegacyId::class);
    }

    /** @return HasMany<Medal, $this> */
    public function medals(): HasMany
    {
        return $this->hasMany(Medal::class);
    }

    /** @return HasMany<Plate, $this> */
    public function plates(): HasMany
    {
        return $this->hasMany(Plate::class);
    }

    /** @return HasMany<LegacyCode, $this> */
    public function legacyCodes(): HasMany
    {
        return $this->hasMany(LegacyCode::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<EventParticipant, $this> */
    public function eventParticipations(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    /** @return HasMany<EventStaffAssignment, $this> */
    public function staffAssignments(): HasMany
    {
        return $this->hasMany(EventStaffAssignment::class);
    }

    /**
     * Users this user follows (Legacy Moments social layer).
     *
     * @return BelongsToMany<User, $this>
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'athlete_follows', 'follower_id', 'following_id')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'athlete_follows', 'following_id', 'follower_id')->withTimestamps();
    }

    /** @return HasMany<LegacyMoment, $this> */
    public function moments(): HasMany
    {
        return $this->hasMany(LegacyMoment::class);
    }

    /** @return HasMany<UserBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_id');
    }

    /** @return HasMany<UserSocialAccount, $this> */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(UserSocialAccount::class);
    }

    /** @return HasMany<PushDevice, $this> */
    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class);
    }

    public function productionActorLabel(): string
    {
        return $this->name;
    }
}

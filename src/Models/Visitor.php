<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Visitor model representing a unique visitor.
 *
 * @property int $id
 * @property string $session_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property int|null $user_id
 * @property string|null $browser
 * @property string|null $browser_version
 * @property string|null $platform
 * @property string|null $platform_version
 * @property string|null $device_type
 * @property bool $is_bot
 * @property string|null $country
 * @property string|null $country_code
 * @property string|null $city
 * @property string|null $region
 * @property float|null $latitude
 * @property float|null $longitude
 * @property CarbonInterface|null $last_activity_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class Visitor extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'session_id',
        'ip',
        'user_agent',
        'user_id',
        'browser',
        'browser_version',
        'platform',
        'platform_version',
        'device_type',
        'is_bot',
        'country',
        'country_code',
        'city',
        'region',
        'latitude',
        'longitude',
        'last_activity_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_bot' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'last_activity_at' => 'datetime',
        'user_id' => 'integer',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('visitor-tracker.tables.visitors', 'visitors');
    }

    /**
     * Get all visits for this visitor.
     *
     * @return HasMany<Visit>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * Get the authenticated user associated with this visitor.
     *
     * @return BelongsTo<Model, Visitor>
     */
    public function user(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        return $this->belongsTo($userModel);
    }

    /**
     * Scope to only include non-bot visitors.
     *
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    public function scopeHumans(Builder $query): Builder
    {
        return $query->where('is_bot', false);
    }

    /**
     * Scope to only include bot visitors.
     *
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    public function scopeBots(Builder $query): Builder
    {
        return $query->where('is_bot', true);
    }

    /**
     * Scope to only include online visitors.
     *
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    public function scopeOnline(Builder $query, ?int $minutes = null): Builder
    {
        $minutes = $minutes ?? (int) config('visitor-tracker.online_threshold', 5);

        return $query->where('last_activity_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Scope to filter by device type.
     *
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    public function scopeDeviceType(Builder $query, string $type): Builder
    {
        return $query->where('device_type', $type);
    }

    /**
     * Scope to filter by browser.
     *
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    public function scopeBrowser(Builder $query, string $browser): Builder
    {
        return $query->where('browser', $browser);
    }

    /**
     * Scope to filter by platform.
     *
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    public function scopePlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope to filter by country.
     *
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    public function scopeCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country_code', $countryCode);
    }

    /**
     * Scope to filter visitors from a date range.
     *
     * @param  Builder<Visitor>  $query
     * @return Builder<Visitor>
     */
    public function scopeDateRange(Builder $query, \DateTimeInterface $start, ?\DateTimeInterface $end = null): Builder
    {
        $query->where('created_at', '>=', $start);

        if ($end) {
            $query->where('created_at', '<=', $end);
        }

        return $query;
    }

    /**
     * Check if the visitor is currently online.
     */
    public function isOnline(?int $minutes = null): bool
    {
        $minutes = $minutes ?? (int) config('visitor-tracker.online_threshold', 5);

        return $this->last_activity_at && $this->last_activity_at->gte(now()->subMinutes($minutes));
    }

    /**
     * Update the last activity timestamp.
     */
    public function updateActivity(): bool
    {
        $this->last_activity_at = now();

        return $this->save();
    }

    /**
     * Get the full location string.
     */
    public function getLocationAttribute(): ?string
    {
        $parts = array_filter([
            $this->city,
            $this->region,
            $this->country,
        ]);

        return ! empty($parts) ? implode(', ', $parts) : null;
    }

    /**
     * Get the browser with version.
     */
    public function getBrowserFullAttribute(): ?string
    {
        if (! $this->browser) {
            return null;
        }

        return $this->browser_version
            ? "{$this->browser} {$this->browser_version}"
            : $this->browser;
    }

    /**
     * Get the platform with version.
     */
    public function getPlatformFullAttribute(): ?string
    {
        if (! $this->platform) {
            return null;
        }

        return $this->platform_version
            ? "{$this->platform} {$this->platform_version}"
            : $this->platform;
    }
}

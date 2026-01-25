<?php

declare(strict_types=1);

namespace Ghdj\VisitorTracker\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Visit model representing a single page visit.
 *
 * @property int $id
 * @property int $visitor_id
 * @property int|null $user_id
 * @property string $url
 * @property string $path
 * @property string $method
 * @property string|null $referrer
 * @property int|null $status_code
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Visit extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'visitor_id',
        'user_id',
        'url',
        'path',
        'method',
        'referrer',
        'status_code',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'visitor_id' => 'integer',
        'user_id' => 'integer',
        'status_code' => 'integer',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('visitor-tracker.tables.visits', 'visits');
    }

    /**
     * Get the visitor that made this visit.
     *
     * @return BelongsTo<Visitor, Visit>
     */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /**
     * Get the authenticated user associated with this visit.
     *
     * @return BelongsTo<Model, Visit>
     */
    public function user(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        return $this->belongsTo($userModel);
    }

    /**
     * Scope to filter by HTTP method.
     *
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeMethod(Builder $query, string $method): Builder
    {
        return $query->where('method', strtoupper($method));
    }

    /**
     * Scope to filter by path.
     *
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopePath(Builder $query, string $path): Builder
    {
        return $query->where('path', $path);
    }

    /**
     * Scope to filter by path pattern (LIKE).
     *
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopePathLike(Builder $query, string $pattern): Builder
    {
        return $query->where('path', 'LIKE', $pattern);
    }

    /**
     * Scope to filter by status code.
     *
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeStatusCode(Builder $query, int $code): Builder
    {
        return $query->where('status_code', $code);
    }

    /**
     * Scope to filter successful visits (2xx status codes).
     *
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereBetween('status_code', [200, 299]);
    }

    /**
     * Scope to filter visits with referrer.
     *
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeWithReferrer(Builder $query): Builder
    {
        return $query->whereNotNull('referrer');
    }

    /**
     * Scope to filter visits from external referrers.
     *
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeExternalReferrer(Builder $query): Builder
    {
        $host = request()->getHost();

        return $query->whereNotNull('referrer')
            ->where('referrer', 'NOT LIKE', "%{$host}%");
    }

    /**
     * Scope to filter visits from a date range.
     *
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
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
     * Scope to filter visits from today.
     *
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope to filter visits from this week.
     *
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->startOfWeek());
    }

    /**
     * Scope to filter visits from this month.
     *
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->startOfMonth());
    }

    /**
     * Scope to only include visits from human visitors.
     *
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeHumans(Builder $query): Builder
    {
        return $query->whereHas('visitor', function (Builder $q) {
            $q->where('is_bot', false);
        });
    }

    /**
     * Get the referrer domain.
     */
    public function getReferrerDomainAttribute(): ?string
    {
        if (! $this->referrer) {
            return null;
        }

        $parsed = parse_url($this->referrer, PHP_URL_HOST);

        return $parsed ?: null;
    }

    /**
     * Check if the referrer is external.
     */
    public function isExternalReferrer(): bool
    {
        if (! $this->referrer) {
            return false;
        }

        $host = request()->getHost();
        $referrerHost = parse_url($this->referrer, PHP_URL_HOST);

        return $referrerHost && $referrerHost !== $host;
    }
}

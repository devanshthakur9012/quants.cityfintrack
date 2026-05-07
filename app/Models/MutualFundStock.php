<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutualFundStock extends Model
{
    protected $fillable = [
        'mutual_fund_id',
        'stock_name',
        'stock_symbol',
        'sector',
        'allocation_percentage',
        'holding_date',
        'status',
    ];

    protected $casts = [
        'status'                => 'boolean',
        'allocation_percentage' => 'decimal:2',
        'holding_date'          => 'date',
    ];

    // ─── Relationships ────────────────────────────────────

    public function fund(): BelongsTo
    {
        return $this->belongsTo(MutualFund::class, 'mutual_fund_id');
    }

    // ─── Scopes ───────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('holding_date', $date);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('holding_date', 'desc');
    }

    public function scopeHighAllocation($query, float $minPercent = 3.0)
    {
        return $query->where('allocation_percentage', '>=', $minPercent);
    }
}
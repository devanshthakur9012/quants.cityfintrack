<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MutualFund extends Model
{
    protected $fillable = [
        'name',
        'code',
        'category',
        'amc',
        'plan_type',
        'option',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────

    public function stocks(): HasMany
    {
        return $this->hasMany(MutualFundStock::class);
    }

    // Latest snapshot only (most recent holding_date)
    public function latestStocks(): HasMany
    {
        return $this->hasMany(MutualFundStock::class)
                    ->whereDate('holding_date', function ($query) {
                        $query->selectRaw('MAX(holding_date)')
                              ->from('mutual_fund_stocks')
                              ->whereColumn('mutual_fund_id', 'mutual_fund_stocks.mutual_fund_id');
                    });
    }

    // ─── Scopes ───────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
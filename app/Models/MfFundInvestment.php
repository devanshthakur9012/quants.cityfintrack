<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfFundInvestment extends Model
{
    protected $table = 'mf_fund_investments';

    protected $fillable = [
        'mutual_fund_id',
        'invested_amount',
        'is_active',
    ];

    protected $casts = [
        'invested_amount' => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(MutualFund::class, 'mutual_fund_id');
    }
}
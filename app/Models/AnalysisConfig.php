<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisConfig extends Model
{
    protected $fillable = [
        'broker_api_id',
        'time_frame',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function symbols()
    {
        return $this->belongsToMany(SymbolList::class, 'analysis_config_symbols', 'analysis_config_id', 'symbol_list_id');
    }
}
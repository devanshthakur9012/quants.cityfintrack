<?php
// FILE: app/Models/AnalysisConfig.php  — REPLACE EXISTING
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisConfig extends Model
{
    protected $fillable = [
        'broker_api_id',
        'time_frame',   // always '15min' — enforced in boot()
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Force 15min on every create and update — UI never exposes this field
    protected static function booted(): void
    {
        static::creating(fn($m) => $m->time_frame = '15min');
        static::updating(fn($m) => $m->time_frame = '15min');
    }

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function symbols()
    {
        return $this->belongsToMany(
            SymbolList::class,
            'analysis_config_symbols',
            'analysis_config_id',
            'symbol_list_id'
        );
    }
}
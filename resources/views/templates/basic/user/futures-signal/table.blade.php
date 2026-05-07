@if($signals->isEmpty())
    <div class="text-center py-5">
        <i class="las la-info-circle la-3x text-muted"></i>
        <p class="mt-3 text-muted">No signals found for the selected filters.</p>
    </div>
@else
    <div class="table-responsive">
        <table class="table custom--table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Symbol</th>
                    <th>Price (OHLC)</th>
                    <th>Volume</th>
                    <th>OI</th>
                    <th>HA Values</th>
                    <th>HA Color</th>
                    <th>HA Strength</th>
                    <th>Structure</th>
                    <th>Vol Change</th>
                    <th>Raw Signal</th>
                    <th>OI Signal</th>
                    <th>Final Signal</th>
                    <th>Candle #</th>
                </tr>
            </thead>
            <tbody>
                @foreach($signals as $signal)
                    <tr>
                        <!-- Time -->
                        <td>
                            <div style="font-size: 11px;">
                                <div>{{ \Carbon\Carbon::parse($signal->data_date)->format('d M Y') }}</div>
                                <div class="text-muted">{{ \Carbon\Carbon::parse($signal->candle_time)->format('H:i') }}</div>
                            </div>
                        </td>
                        
                        <!-- Symbol -->
                        <td>
                            <div style="font-weight: 600;">{{ $signal->underlying }}</div>
                            <div style="font-size: 10px; color: #666;">{{ $signal->symbol }}</div>
                        </td>
                        
                        <!-- Price OHLC -->
                        <td>
                            <div style="font-size: 10px;">
                                <div>O: <span class="fw-bold">{{ number_format($signal->open, 2) }}</span></div>
                                <div>H: <span class="text-success">{{ number_format($signal->high, 2) }}</span></div>
                                <div>L: <span class="text-danger">{{ number_format($signal->low, 2) }}</span></div>
                                <div>C: <span class="fw-bold">{{ number_format($signal->close, 2) }}</span></div>
                            </div>
                        </td>
                        
                        <!-- Volume -->
                        <td>
                            <div style="font-size: 11px;">
                                {{ number_format($signal->volume) }}
                            </div>
                        </td>
                        
                        <!-- OI -->
                        <td>
                            <div style="font-size: 11px; font-weight: 600;">
                                {{ number_format($signal->oi) }}
                            </div>
                        </td>
                        
                        <!-- HA Values -->
                        <td>
                            <div style="font-size: 10px;">
                                <div>O: {{ number_format($signal->ha_open, 2) }}</div>
                                <div>H: {{ number_format($signal->ha_high, 2) }}</div>
                                <div>L: {{ number_format($signal->ha_low, 2) }}</div>
                                <div>C: {{ number_format($signal->ha_close, 2) }}</div>
                            </div>
                        </td>
                        
                        <!-- HA Color -->
                        <td>
                            <span class="ha-badge ha-{{ strtolower($signal->ha_color) }}">
                                {{ $signal->ha_color }}
                            </span>
                        </td>
                        
                        <!-- HA Strength -->
                        <td>
                            @php
                                $strength = $signal->ha_strength * 100;
                                $color = $strength >= 50 ? 'success' : 'warning';
                            @endphp
                            <div class="text-{{ $color }}" style="font-weight: 600; font-size: 11px;">
                                {{ number_format($strength, 1) }}%
                            </div>
                        </td>
                        
                        <!-- Structure Type -->
                        <td>
                            @php
                                $structureClass = '';
                                switch($signal->structure_type) {
                                    case 'LONG_BUILDUP':
                                        $structureClass = 'struct-long-buildup';
                                        break;
                                    case 'SHORT_BUILDUP':
                                        $structureClass = 'struct-short-buildup';
                                        break;
                                    case 'SHORT_COVERING':
                                        $structureClass = 'struct-short-covering';
                                        break;
                                    case 'LONG_UNWINDING':
                                        $structureClass = 'struct-long-unwinding';
                                        break;
                                    default:
                                        $structureClass = 'struct-neutral';
                                }
                            @endphp
                            <span class="structure-badge {{ $structureClass }}">
                                {{ str_replace('_', ' ', $signal->structure_type) }}
                            </span>
                        </td>
                        
                        <!-- Volume Change -->
                        <td>
                            @php
                                $volChange = $signal->structure_vol_change * 100;
                                $volClass = $volChange > 0 ? 'positive' : ($volChange < 0 ? 'negative' : 'zero');
                            @endphp
                            <div class="{{ $volClass }}" style="font-size: 11px;">
                                {{ $volChange > 0 ? '+' : '' }}{{ number_format($volChange, 2) }}%
                            </div>
                        </td>
                        
                        <!-- Raw Signal -->
                        <td>
                            @php
                                $rawClass = '';
                                switch($signal->raw_signal) {
                                    case 'BUY':
                                        $rawClass = 'signal-buy';
                                        break;
                                    case 'SELL':
                                        $rawClass = 'signal-sell';
                                        break;
                                    default:
                                        $rawClass = 'signal-no-trade';
                                }
                            @endphp
                            <span class="signal-badge {{ $rawClass }}">
                                {{ $signal->raw_signal }}
                            </span>
                        </td>
                        
                        <!-- OI Signal -->
                        <td>
                            @php
                                $oiClass = $signal->oi_signal === 'BULLISH' ? 'text-success' : 
                                          ($signal->oi_signal === 'BEARISH' ? 'text-danger' : 'text-muted');
                            @endphp
                            <div class="{{ $oiClass }}" style="font-weight: 600; font-size: 11px;">
                                {{ $signal->oi_signal }}
                            </div>
                        </td>
                        
                        <!-- Final Signal -->
                        <td>
                            @php
                                $finalClass = '';
                                switch($signal->final_signal) {
                                    case 'BUY':
                                        $finalClass = 'signal-buy';
                                        break;
                                    case 'SELL':
                                        $finalClass = 'signal-sell';
                                        break;
                                    default:
                                        $finalClass = 'signal-no-trade';
                                }
                            @endphp
                            <span class="signal-badge {{ $finalClass }}" style="font-size: 12px; padding: 6px 14px;">
                                {{ $signal->final_signal }}
                            </span>
                        </td>
                        
                        <!-- Candle Index -->
                        <td>
                            <div style="font-size: 11px; text-align: center;">
                                #{{ $signal->candle_index }}
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Legend -->
    <div class="mt-3 p-3" style="background: #0d222b;border-radius: 8px;font-size: 11px;border: 1px solid #fff;">
        <div class="fw-bold mb-2">📚 Trading Logic Applied:</div>
        <div class="row">
            <div class="col-md-6">
                <div><strong>• Trend Lock:</strong> 3 candles minimum before reversal</div>
                <div><strong>• Confirmation:</strong> 2 consecutive opposite signals needed</div>
                <div><strong>• HA Strength:</strong> Minimum 50% for valid signals</div>
            </div>
            <div class="col-md-6">
                <div><strong>• Structure Types:</strong> Long Buildup, Short Buildup, Covering, Unwinding</div>
                <div><strong>• OI Conviction:</strong> Bullish (OI up) / Bearish (OI down)</div>
                <div><strong>• Final Signal:</strong> Combined result after all filters</div>
            </div>
        </div>
    </div>
@endif
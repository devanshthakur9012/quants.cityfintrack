{{-- Updated transition-table.blade.php --}}
@if(!empty($processedData))
    <div class="transition-card">
        <div class="transition-header">
            📊 Symbol: {{ $symbol }}
        </div>
        
        <table class="transition-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Date</th>
                    <th style="width: 21.25%;">Long (Buy Buildup)</th>
                    <th style="width: 21.25%;">Short (Sell Buildup)</th>
                    <th style="width: 21.25%;">Short Covering</th>
                    <th style="width: 21.25%;">Long Unwinding</th>
                </tr>
            </thead>
            <tbody>
                @foreach($processedData as $date => $signals)
                    <tr>
                        <td class="date-cell">
                            <strong>{{ \Carbon\Carbon::parse($date)->format('M j, Y') }}</strong><br>
                            <small>{{ \Carbon\Carbon::parse($date)->format('D') }}</small>
                        </td>
                        
                        <td class="{{ $signals['long'] ? 'signal-long-buildup' : '' }}">
                            @if($signals['long'])
                                <div class="">{{ $signals['long']['datetime'] }}</div>
                                <div class="">LTP: {{ number_format($signals['long']['ltp'], 2) }}</div>
                            @else
                                <span class="empty-cell">–</span>
                            @endif
                        </td>
                        
                        <td class="{{ $signals['short'] ? 'signal-short-buildup' : '' }}">
                            @if($signals['short'])
                                <div class="">{{ $signals['short']['datetime'] }}</div>
                                <div class="">LTP: {{ number_format($signals['short']['ltp'], 2) }}</div>
                            @else
                                <span class="empty-cell">–</span>
                            @endif
                        </td>
                        
                        <td class="{{ $signals['covering'] ? 'signal-short-covering' : '' }}">
                            @if($signals['covering'])
                                <div class="">{{ $signals['covering']['datetime'] }}</div>
                                <div class="">LTP: {{ number_format($signals['covering']['ltp'], 2) }}</div>
                            @else
                                <span class="empty-cell">–</span>
                            @endif
                        </td>
                        
                        <td class="{{ $signals['unwinding'] ? 'signal-long-unwinding' : '' }}">
                            @if($signals['unwinding'])
                                <div >{{ $signals['unwinding']['datetime'] }}</div>
                                <div >LTP: {{ number_format($signals['unwinding']['ltp'], 2) }}</div>
                            @else
                                <span class="empty-cell">–</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        
        <div style="padding: 15px 20px; background: #0d222b; font-size: 12px; color: #6c757d; text-align: center;">
            📈 First occurrence of each type per day | 🕐 Time shown in respective cells | Last 30 days data
        </div>
    </div>
@else
    <div class="alert alert-warning text-center">No transitions found for the selected symbol in the last 30 days.</div>
@endif
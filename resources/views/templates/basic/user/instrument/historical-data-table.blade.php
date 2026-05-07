<div class="table-responsive">
    <table class="custom--table table mb-0">
        <thead>
            <tr>
                <th>Date</th>
                <th>Underlying</th>
                <th>Strike</th>
                <th>Type</th>
                <th>Symbol</th>
                <th>Open</th>
                <th>High</th>
                <th>Low</th>
                <th>Close</th>
                <th>Volume</th>
                <th>OI</th>
                <th>Price Chg</th>
                <th>OI Chg</th>
                <th>OI Chg %</th>
                <th>Trend</th>
                <th>FUT Score</th>
                <th>OPT Score</th>
                <th>Final Score</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groupedData as $group)
                @php
                    $future = $group['FUT'];
                    $ce = $group['CE'];
                    $pe = $group['PE'];
                    $rowSpan = 0;
                    if($future) $rowSpan++;
                    if($ce) $rowSpan++;
                    if($pe) $rowSpan++;
                @endphp

                {{-- Future Row --}}
                @if($future)
                <tr>
                    @if($rowSpan > 0)
                        <td rowspan="{{ $rowSpan }}">{{ \Carbon\Carbon::parse($group['date'])->format('d-m-Y') }}</td>
                        <td rowspan="{{ $rowSpan }}"><strong>{{ $group['underlying'] }}</strong></td>
                        <td rowspan="{{ $rowSpan }}">
                            @if($group['strike_price'])
                                {{ number_format($group['strike_price'], 2) }}
                                @if($group['strike_position'] == 0)
                                    <span class="badge bg-warning text-dark">ATM</span>
                                @endif
                            @else
                                -
                            @endif
                        </td>
                    @endif
                    <td><span class="type-badge type-fut">FUT</span></td>
                    <td><small>{{ $future->symbol }}</small></td>
                    <td>{{ $future->open ? number_format($future->open, 2) : '-' }}</td>
                    <td>{{ $future->high ? number_format($future->high, 2) : '-' }}</td>
                    <td>{{ $future->low ? number_format($future->low, 2) : '-' }}</td>
                    <td>{{ $future->close ? number_format($future->close, 2) : '-' }}</td>
                    <td>{{ $future->volume ? number_format($future->volume) : '-' }}</td>
                    <td>{{ $future->oi ? number_format($future->oi) : '-' }}</td>
                    <td>
                        @if($future->price_change)
                            <span class="{{ $future->price_change > 0 ? 'positive' : ($future->price_change < 0 ? 'negative' : 'zero') }}">
                                {{ $future->price_change > 0 ? '+' : '' }}{{ number_format($future->price_change, 2) }}
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if($future->oi_change)
                            <span class="{{ $future->oi_change > 0 ? 'positive' : ($future->oi_change < 0 ? 'negative' : 'zero') }}">
                                {{ $future->oi_change > 0 ? '+' : '' }}{{ number_format($future->oi_change) }}
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $future->oi_change_pct ? number_format($future->oi_change_pct, 2) . '%' : '-' }}</td>
                    @if($rowSpan > 0)
                        <td rowspan="{{ $rowSpan }}">
                            @if($group['trend'])
                                @php
                                    $trendClass = match (strtolower($group['trend'])) {
                                        'strong bullish' => 'trend-strong-bullish',
                                        'mild bullish' => 'trend-mild-bullish',
                                        'neutral / sideways' => 'trend-neutral',
                                        'mild bearish' => 'trend-mild-bearish',
                                        'strong bearish' => 'trend-strong-bearish',
                                        default => 'trend-neutral',
                                    };
                                @endphp
                                <span class="trend-badge {{ $trendClass }}">{{ $group['trend'] }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <td rowspan="{{ $rowSpan }}">
                            <span class="{{ $group['futures_score'] > 0 ? 'positive' : ($group['futures_score'] < 0 ? 'negative' : 'zero') }}">
                                {{ $group['futures_score'] ?? 0 }}
                            </span>
                        </td>
                        <td rowspan="{{ $rowSpan }}">
                            <span class="{{ $group['options_score'] > 0 ? 'positive' : ($group['options_score'] < 0 ? 'negative' : 'zero') }}">
                                {{ $group['options_score'] ?? 0 }}
                            </span>
                        </td>
                        <td rowspan="{{ $rowSpan }}">
                            <span class="{{ $group['final_score'] > 0 ? 'positive' : ($group['final_score'] < 0 ? 'negative' : 'zero') }}">
                                {{ $group['final_score'] ?? 0 }}
                            </span>
                        </td>
                    @endif
                </tr>
                @endif

                {{-- CE Row --}}
                @if($ce)
                <tr>
                    <td><span class="type-badge type-ce">CE</span></td>
                    <td><small>{{ $ce->symbol }}</small></td>
                    <td>{{ $ce->open ? number_format($ce->open, 2) : '-' }}</td>
                    <td>{{ $ce->high ? number_format($ce->high, 2) : '-' }}</td>
                    <td>{{ $ce->low ? number_format($ce->low, 2) : '-' }}</td>
                    <td>{{ $ce->close ? number_format($ce->close, 2) : '-' }}</td>
                    <td>{{ $ce->volume ? number_format($ce->volume) : '-' }}</td>
                    <td>{{ $ce->oi ? number_format($ce->oi) : '-' }}</td>
                    <td>
                        @if($ce->price_change)
                            <span class="{{ $ce->price_change > 0 ? 'positive' : ($ce->price_change < 0 ? 'negative' : 'zero') }}">
                                {{ $ce->price_change > 0 ? '+' : '' }}{{ number_format($ce->price_change, 2) }}
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if($ce->oi_change)
                            <span class="{{ $ce->oi_change > 0 ? 'positive' : ($ce->oi_change < 0 ? 'negative' : 'zero') }}">
                                {{ $ce->oi_change > 0 ? '+' : '' }}{{ number_format($ce->oi_change) }}
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $ce->oi_change_pct ? number_format($ce->oi_change_pct, 2) . '%' : '-' }}</td>
                </tr>
                @endif

                {{-- PE Row --}}
                @if($pe)
                <tr>
                    <td><span class="type-badge type-pe">PE</span></td>
                    <td><small>{{ $pe->symbol }}</small></td>
                    <td>{{ $pe->open ? number_format($pe->open, 2) : '-' }}</td>
                    <td>{{ $pe->high ? number_format($pe->high, 2) : '-' }}</td>
                    <td>{{ $pe->low ? number_format($pe->low, 2) : '-' }}</td>
                    <td>{{ $pe->close ? number_format($pe->close, 2) : '-' }}</td>
                    <td>{{ $pe->volume ? number_format($pe->volume) : '-' }}</td>
                    <td>{{ $pe->oi ? number_format($pe->oi) : '-' }}</td>
                    <td>
                        @if($pe->price_change)
                            <span class="{{ $pe->price_change > 0 ? 'positive' : ($pe->price_change < 0 ? 'negative' : 'zero') }}">
                                {{ $pe->price_change > 0 ? '+' : '' }}{{ number_format($pe->price_change, 2) }}
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if($pe->oi_change)
                            <span class="{{ $pe->oi_change > 0 ? 'positive' : ($pe->oi_change < 0 ? 'negative' : 'zero') }}">
                                {{ $pe->oi_change > 0 ? '+' : '' }}{{ number_format($pe->oi_change) }}
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $pe->oi_change_pct ? number_format($pe->oi_change_pct, 2) . '%' : '-' }}</td>
                </tr>
                @endif

            @empty
                <tr>
                    <td colspan="18" class="text-center">No Data Found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
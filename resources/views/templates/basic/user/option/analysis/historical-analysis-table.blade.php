<div class="table-responsive">
    <table class="custom--table table mb-0">
        <thead>
            <tr>
                <th style="padding:10px !important;">Date</th>
                <th style="padding:10px !important;">Underlying</th>
                <th style="padding:10px !important;">Avg CE OI % Chg</th>
                <th style="padding:10px !important;">Avg PE OI % Chg</th>
                <th style="padding:10px !important;">Sentiment</th>
                <th style="padding:10px !important;">Pattern</th>
                <th style="padding:10px !important;">Strength Score</th>
                <th style="padding:10px !important;">Support Zone</th>
                <th style="padding:10px !important;">Resistance Zone</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row['date'])->format('d-m-Y') }}</td>
                    <td>{{ $row['underlying'] }}</td>

                    <td>
                        @if ($row['avg_ce_oi_change'] > 0)
                            <span class="positive">+{{ number_format($row['avg_ce_oi_change'], 2) }}%</span>
                        @elseif ($row['avg_ce_oi_change'] < 0)
                            <span class="negative">{{ number_format($row['avg_ce_oi_change'], 2) }}%</span>
                        @else
                            <span class="zero">0.00%</span>
                        @endif
                    </td>

                    <td>
                        @if ($row['avg_pe_oi_change'] > 0)
                            <span class="positive">+{{ number_format($row['avg_pe_oi_change'], 2) }}%</span>
                        @elseif ($row['avg_pe_oi_change'] < 0)
                            <span class="negative">{{ number_format($row['avg_pe_oi_change'], 2) }}%</span>
                        @else
                            <span class="zero">0.00%</span>
                        @endif
                    </td>

                    <td>
                        @php
                            $trendClass = match (strtolower($row['sentiment'])) {
                                'strong bullish' => 'trend-strong-bullish',
                                'mild bullish'   => 'trend-mild-bullish',
                                'neutral'        => 'trend-neutral',
                                'mild bearish'   => 'trend-mild-bearish',
                                'strong bearish' => 'trend-strong-bearish',
                                default          => 'trend-neutral',
                            };
                        @endphp
                        <span class="trend-badge {{ $trendClass }}">{{ $row['sentiment'] }}</span>
                    </td>

                    <td>{{ $row['pattern'] ?? '-' }}</td>

                    <td>
                        <span
                            class="{{ $row['strength_score'] > 0 ? 'positive' : ($row['strength_score'] < 0 ? 'negative' : 'zero') }}">
                            {{ number_format($row['strength_score'], 2) }}
                        </span>
                    </td>

                    <td>{{ $row['support_zone'] ?? '-' }}</td>
                    <td>{{ $row['resistance_zone'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted">No Data Found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
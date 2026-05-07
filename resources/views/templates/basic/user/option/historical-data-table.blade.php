<div class="table-responsive">
    <table class="custom--table table mb-0">
        <thead>
            <tr>
                <th style="padding:10px !important;">Date</th>
                <th style="padding:10px !important;">FUT SYMBOL</th>
                <th style="padding:10px !important;">FUT VOL</th>
                <th style="padding:10px !important;">FUT OI</th>
                <th style="padding:10px !important;">FUT Price</th>
                <th style="padding:10px !important;">FUT OI</th>
                <th style="padding:10px !important;">FUT OI % CHG</th>
                <th style="padding:10px !important;">CE SYMBOL</th>
                <th style="padding:10px !important;">CE VOL</th>
                <th style="padding:10px !important;">CE OI</th>
                <th style="padding:10px !important;">CE Price</th>
                <th style="padding:10px !important;">CE OI</th>
                <th style="padding:10px !important;">CE OI % CHG</th>
                <th style="padding:10px !important;">PE SYMBOL</th>
                <th style="padding:10px !important;">PE VOL</th>
                <th style="padding:10px !important;">PE OI</th>
                <th style="padding:10px !important;">PE Price</th>
                <th style="padding:10px !important;">PE OI</th>
                <th style="padding:10px !important;">PE OI % CHG</th>
                <th style="padding:10px !important;">Trend</th>
                <th style="padding:10px !important;">FUT Score</th>
                <th style="padding:10px !important;">OPT Score</th>
                <th style="padding:10px !important;">Final Score</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row->date)->format('d-m') }}</td>

                    <!-- Future Data -->
                    <td>
                        @if ($row->future_symbol)
                            <span>{{ $row->future_symbol }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>{{ $row->future_volume ? number_format($row->future_volume) : '-' }}</td>
                    <td>{{ $row->future_oi ? number_format($row->future_oi) : '-' }}</td>
                    <td>
                        @if ($row->future_price_change)
                            <span
                                class="{{ $row->future_price_change > 0 ? 'positive' : ($row->future_price_change < 0 ? 'negative' : 'zero') }}">
                                {{ $row->future_price_change > 0 ? '+' : '' }}{{ $row->future_price_change }}
                            </span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if ($row->future_oi_change)
                            <span
                                class="{{ $row->future_oi_change > 0 ? 'positive' : ($row->future_oi_change < 0 ? 'negative' : 'zero') }}">
                                {{ $row->future_oi_change > 0 ? '+' : '' }}{{ number_format($row->future_oi_change) }}
                            </span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>{{ $row->future_oi_chg_pct }}</td>

                    <!-- CE Data -->
                    <td>
                        @if ($row->ce_symbol)
                            <span>{{ $row->ce_symbol }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>{{ $row->ce_volume ? number_format($row->ce_volume) : '-' }}</td>
                    <td>{{ $row->ce_oi ? number_format($row->ce_oi) : '-' }}</td>
                    <td>
                        @if ($row->ce_price_change)
                            <span
                                class="{{ $row->ce_price_change > 0 ? 'positive' : ($row->ce_price_change < 0 ? 'negative' : 'zero') }}">
                                {{ $row->ce_price_change > 0 ? '+' : '' }}{{ $row->ce_price_change }}
                            </span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if ($row->ce_oi_change)
                            <span
                                class="{{ $row->ce_oi_change > 0 ? 'positive' : ($row->ce_oi_change < 0 ? 'negative' : 'zero') }}">
                                {{ $row->ce_oi_change > 0 ? '+' : '' }}{{ number_format($row->ce_oi_change) }}
                            </span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>{{ $row->ce_oi_chg_pct }}</td>

                    <!-- PE Data -->
                    <td>
                        @if ($row->pe_symbol)
                            <span>{{ $row->pe_symbol }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>{{ $row->pe_volume ? number_format($row->pe_volume) : '-' }}</td>
                    <td>{{ $row->pe_oi ? number_format($row->pe_oi) : '-' }}</td>
                    <td>
                        @if ($row->pe_price_change)
                            <span
                                class="{{ $row->pe_price_change > 0 ? 'positive' : ($row->pe_price_change < 0 ? 'negative' : 'zero') }}">
                                {{ $row->pe_price_change > 0 ? '+' : '' }}{{ $row->pe_price_change }}
                            </span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if ($row->pe_oi_change)
                            <span
                                class="{{ $row->pe_oi_change > 0 ? 'positive' : ($row->pe_oi_change < 0 ? 'negative' : 'zero') }}">
                                {{ $row->pe_oi_change > 0 ? '+' : '' }}{{ number_format($row->pe_oi_change) }}
                            </span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>{{ $row->pe_oi_chg_pct }}</td>

                    <!-- Trend Analysis -->
                    <td>
                        @if ($row->trend)
                            @php
                                $trendClass = match (strtolower($row->trend)) {
                                    'strong bullish' => 'trend-strong-bullish',
                                    'mild bullish' => 'trend-mild-bullish',
                                    'neutral / sideways' => 'trend-neutral',
                                    'mild bearish' => 'trend-mild-bearish',
                                    'strong bearish' => 'trend-strong-bearish',
                                    default => 'trend-neutral',
                                };
                            @endphp
                            <span class="trend-badge text-center {{ $trendClass }}">{{ $row->trend }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        <span
                            class="{{ $row->futures_score > 0 ? 'positive' : ($row->futures_score < 0 ? 'negative' : 'zero') }}">
                            {{ $row->futures_score ?? 0 }}
                        </span>
                    </td>
                    <td>
                        <span
                            class="{{ $row->options_score > 0 ? 'positive' : ($row->options_score < 0 ? 'negative' : 'zero') }}">
                            {{ $row->options_score ?? 0 }}
                        </span>
                    </td>
                    <td>
                        <span
                            class="{{ $row->final_score > 0 ? 'positive' : ($row->final_score < 0 ? 'negative' : 'zero') }}">
                            {{ $row->final_score ?? 0 }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="21" class="text-center">No Data Found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="card-body p-0">
    <div class="table-responsive--md table-responsive">
        <table class="table custom--table text-nowrap">
            <thead>
                <tr>
                    <th>@lang('TXN Date')</th>
                    <th>@lang('Symbol')</th>
                    <th>@lang('Expiry')</th>
                    <th>@lang('TXN Type')</th>
                    <th>@lang('Lot Size')</th>
                    <th>@lang('Atm Status')</th>
                    <th>@lang('Ce')</th>
                    <th>@lang('Pe')</th>
                    <th>@lang('Ce Entry Price')</th>
                    <th>@lang('pe Entry Price')</th>
                    <th>@lang('Ce Ltp')</th>
                    <th>@lang('Pe Ltp')</th>
                    <th>@lang('Total Premium')</th>
                    <th>@lang('Total Premium *')</th>
                    <th>@lang('MTM')</th>
                    <th>@lang('Target Status')</th>
                </tr>
            </thead>
            <tbody>
                @forelse($paperTrade as $trade)
                    <tr>
                        <td>
                            <strong>{{showDate($trade->date)}}</strong>
                        </td>
                        <td>{{ $trade->symbol }}</td>
                        <td>{{ showDate($trade->expiry) }}</td>
                        <td>{{ $trade->transaction_type }}</td>
                        <td>{{ $trade->lot_size }}</td>
                        <td>{{ $trade->atm_status }}</td>
                        <td>{{ $trade->ce }}</td>
                        <td>{{ $trade->pe }}</td>
                        @php
                            $ce_textColor = "text-success";
                            if ($trade->ce_entry_price > $trade->ce_ltp ) {
                                $ce_textColor = "text-danger";
                            }

                            $pe_textColor = "text-success";
                            if ($trade->pe_entry_price > $trade->pe_ltp ) {
                                $pe_textColor = "text-danger";
                            }
                        @endphp
                        <td>{{ $trade->ce_entry_price }}</td>
                        <td>{{ $trade->pe_entry_price }}</td>
                        <td class="{{$ce_textColor}}">{{ $trade->ce_ltp }}</td>
                        <td class="{{$pe_textColor}}">{{ $trade->pe_ltp }}</td>
                        <td>{{ $trade->combined_premium_ce_pe }}</td>
                        <td>{{ $trade->combined_premium_mul_lot_size }}</td>
                        @php
                            $mtm = (($trade->ce_ltp+$trade->pe_ltp)*$trade->lot_size)-($trade->combined_premium_mul_lot_size);
                            $target = "";
                        @endphp
                        <td>{{ round($mtm,2) }}</td>
                        <td>{{ $trade->target_status }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
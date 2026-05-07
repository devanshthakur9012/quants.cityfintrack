<div class="card-body p-0">
    <div class="table-responsive--md table-responsive">
        <table class="table custom--table text-nowrap">
            <thead>
                <tr>
                    <th>@lang('Date')</th>
                    <th>@lang('Symbol')</th>
                    <th>@lang('Entry Time')</th>
                    <th>@lang('Exit Time')</th>
                    <th>@lang('Trade Type')</th>
                    <th>@lang('CE')</th>
                    <th>@lang('PE')</th>
                    <th>@lang('CE ATM')</th>
                    <th>@lang('PE ATM')</th>
                    {{-- <th>@lang('CE Delta')</th> --}}
                    {{-- <th>@lang('PE Delta')</th> --}}
                    <th>@lang('CE Entry Price')</th>
                    <th>@lang('PE Entry Price')</th>
                    <th>@lang('Difference')</th>
                    <th>@lang('CE LTP')</th>
                    <th>@lang('PE LTP')</th>
                    <th>@lang('TOTAL PREMIUM')</th>
                    <th>@lang('MTM')</th>
                </tr>
            </thead>
            <tbody>
                @forelse($data['paperTrade'] as $trade)

                    @php
                        $ce_textColor = "text-success";
                        if ($trade->ce_entry_price > $trade->ce_ltp ) {
                            $ce_textColor = "text-danger";
                        }

                        $pe_textColor = "text-success";
                        if ($trade->pe_entry_price > $trade->pe_ltp ) {
                            $pe_textColor = "text-danger";
                        }

                        $mtm = "";
                        $target = "";

                    @endphp
                    <tr>
                        <td>
                            <strong>{{showDate($trade->date)}}</strong>
                        </td>
                        <td>{{ $trade->symbol }}</td>
                        <td>{{$trade->entry_time}}</td>
                        <td>{{$trade->exit_time}}</td>
                        <td>{{$trade->trade_type}}</td>
                        <td>{{$trade->ce}}</td>
                        <td>{{$trade->pe}}</td>
                        <td>{{$trade->ce_atm}}</td>
                        <td>{{$trade->pe_atm}}</td>
                        {{-- <td>{{$trade->ce_delta}}</td> --}}
                        {{-- <td>{{$trade->pe_delta}}</td> --}}
                        <td>{{$trade->ce_entry_price}}</td>
                        <td>{{$trade->pe_entry_price}}</td>
                        <td>{{$trade->difference}}</td>
                        <td class="{{$ce_textColor}}">{{$trade->ce_ltp}}</td>
                        <td class="{{$pe_textColor}}">{{$trade->pe_ltp}}</td>
                        <td>{{$trade->combined_premium}}</td>
                        @php $mtm = (($trade->ce_ltp+$trade->pe_ltp))-($trade->combined_premium); @endphp
                        <td>{{ round($mtm,2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-muted text-center" colspan="100%">NO DATA</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
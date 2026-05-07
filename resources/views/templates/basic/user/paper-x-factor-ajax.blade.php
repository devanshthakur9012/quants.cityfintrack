 @forelse($paperFactor as $value)
            <div class="row mb-3">
                <div class="col-lg-12">
                    
                    <div class="custom--card">
                        <div class="card-header">{{$value->symbol}}</div>
                        <div class="card-body p-0">
                            <div class="table-responsive--md table-responsive">
                                <table class="table custom--table text-nowrap">
                                    <thead>
                                        <tr>
                                            <th>@lang('TIME')</th>
                                            <th>@lang('CE SYMBOL')</th>
                                            <th>@lang('CE CLOSE')</th>
                                            <th>@lang('FINAL CE')</th>
                                            <th>@lang('CE QTY LOTS')</th>
                                            <th>@lang('CE ENTRY PRICE')</th>
                                            <th>@lang('CE EXIT PRICE')</th>
                                            <th>@lang('CE PROFIT')</th>
                                            <th>@lang('PE SYMBOL')</th>
                                            <th>@lang('PE CLOSE')</th>
                                            <th>@lang('FINAL PE')</th>
                                            <th>@lang('PE QTY LOTS')</th>
                                            <th>@lang('PE ENTRY PRICE')</th>
                                            <th>@lang('PE EXIT PRICE')</th>
                                            <th>@lang('PE PROFIT')</th>
                                            <th>@lang('STRATEGY PROFIT')</th>
                                            <th>@lang('TRADE STATUS')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $data = json_decode($value->data,true);
                                            $revArr = array_reverse($data);
                                            
                                            $fData = $isFiltered == 0 ? array_slice($revArr,0,5) : $revArr;
                                            $totalItems = 0;
                                            $itemsPerPage = 100;
                                            $currentPage =  isset($_GET['page']) ? $_GET['page'] : 1;

                                            $arrData = $fData;  
                                            $totalItems = count($arrData);
                                            $currentItems = array_slice($arrData, ($currentPage - 1) * $itemsPerPage, $itemsPerPage);
                                        
                                        @endphp
                                        @foreach($currentItems as $val)
                                        <tr>
                                            <td>{{isset($val['time']) ? $val['time']: '-'}}</td>
                                            <td>{{$val['CE_symbol']}}</td>
                                            <td>{{$val['CE_Close']}}</td>
                                            <td>{{$val['FINAL_CE']}}</td>
                                            <td>{{$val['CE_quantity']}}</td>
                                            <td>{{$val['CE_Open']}}</td>
                                            <td>{{$val['CE_Exit_price']}}</td>
                                            <td><span class="{{$val['CE_Profit'] > 0? 'text-success':'text-danger'}}">{{$val['CE_Profit']}}</span></td>
                                            <td>{{$val['PE_symbol']}}</td>
                                            <td>{{$val['PE_Close']}}</td>
                                            <td>{{$val['FINAL_PE']}}</td>
                                            <td>{{$val['PE_quantity']}}</td>
                                            <td>{{$val['PE_Open']}}</td>
                                            <td>{{$val['PE_Exit_price']}}</td>
                                            <td><span class="{{$val['PE_Profit'] > 0? 'text-success':'text-danger'}}">{{$val['PE_Profit']}}</span></td>
                                            <td><span class="{{$val['Strategy_Profit'] > 0? 'text-success':'text-danger'}}">{{$val['Strategy_Profit']}}</span></td>
                                            <td>{{$val['Trade_Status']}}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div>
                            @php
                        if($isFiltered==1):
                                $totalPages = ceil($totalItems / $itemsPerPage);
                                echo '<nav class="mt-3 justify-content-end d-flex">
                                        <ul class="pagination mb-0">';
                            @endphp
                            @for($i = 1; $i <= $totalPages; $i++)
                                @if($i == $currentPage)
                                    <li class="page-item active" aria-current="page"><span class="page-link">{{$i}}</span></li>
                                @else
                                <li class="page-item"><a class="page-link" href="{{url('user/paper-x-factor?factor_symbol='.request('factor_symbol').'&factor_date='.request('factor_date').'&page='.$i.'')}}">{{$i}}</a></li>
                                @endif
                            @endfor
                            @php     
                                echo '  </ul>
                                    </nav>';
                                endif;
                            @endphp
                        </div>
                </div>
            </div>
        @empty
            <div class="row mb-3">
                <div class="col-lg-12">
                    <div class="custom--card">
                       
                        <div class="card-body p-0">
                            <h3 class="text-center text-danger">NO DATA</h3>
                        </div>
                    </div>
                </div>
            </div>
        @endforelse

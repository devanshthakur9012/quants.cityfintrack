@extends($activeTemplate . 'layouts.master')
@section('content')

    @push('style')
        {{-- <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.css" /> --}}
    @endpush


    <section class="pt-100 pb-100">
        <div class="container content-container">
            <div class="mb-1">
                <div class="custom--nav-tabs mb-3">
                    <ul class="nav ">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ url('user/portfolio-top-gainers') }}">Index Options</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ url('user/portfolio-top-gainers-stock') }}">Stock Options</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="{{ url('user/portfolio-greeks') }}">Greeks Options</a>
                        </li>
                    </ul>
                </div>
            </div>
            <form action="" class="transparent-form mb-3">
                <div class="row">
                    <div class="col-lg-3 form-group">
                        <label>@lang('Symbol Name')</label>
                        <select name="stock_name" class="form--control" id="">
                            <option value="">Select Symbol Name</option>
                            @foreach ($symbolArr as $v)
                                @if (in_array($v, [
                                        'CRUDEOIL',
                                        'BANKNIFTY',
                                        'FINNIFTY',
                                        'SILVER',
                                        'NIFTY',
                                        'MIDCPNIFTY',
                                        'NATURALGAS',
                                        'SILVER',
                                        'GOLD',
                                    ]))
                                    <option value="{{ $v }}" {{ $v == $stockName ? 'selected' : '' }}>
                                        {{ $v }}</option>
                                @endif
                            @endforeach
                        </select>
                        {{-- <input type="text" name="search" value="" class="form--control" placeholder="@lang('Stock Name')"> --}}
                    </div>
                    <div class="col-lg-3 form-group">
                        <label>@lang('TimeFrame')</label>
                        <select name="time_frame" class="form--control">
                            @foreach (allTradeTimeFrames() as $item)
                                <option value="{{ $item }}" {{ $item == $timeFrame ? 'selected' : '' }}>
                                    {{ $item }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 form-group mt-auto">
                        <button class="btn btn--base w-100" type="submit"><i class="las la-filter"></i>
                            @lang('Filter')</button>
                    </div>
                    <div class="col-lg-3 col-md-3 col-6 form-group mt-auto">
                        <a href="{{ url('/user/portfolio-greeks') }}" class="btn btn--base w-100"><i
                                class="las la-redo-alt"></i> @lang('Refresh')</a>
                    </div>
                </div>
            </form>


            <div class="mb-1">
                <div class="custom--nav-tabs border-0 mb-3">
                    <ul class="nav d-flex justify-content-end">
                        <li class="nav-item">
                            <a class="nav-link " href="{{ url('user/option-analysis') }}">Price&OI</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="{{ url('user/portfolio-greeks-graphs') }}">Greeks</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div id="pst_hre">
                @php $tableData = []; @endphp
                @if ($stockName != '')
                    @php
                        $data = \DB::connection('mysql_rm')
                            ->table($stockName)
                            ->select('*')
                            ->where(['date' => $todayDate, 'timeframe' => $timeFrame])
                            ->get();
                        if (count($data) == 0) {
                            $data = \DB::connection('mysql_rm')
                                ->table($stockName)
                                ->select('*')
                                ->where(['timeframe' => $timeFrame])
                                ->get();
                        }
                        $atmData = [];
                        foreach ($data as $vvl) {
                            if (isset($vvl->atm) && $vvl->atm == 'ATM') {
                                $atmData[] = $vvl;
                            }
                        }
                        $totalItems = 0;
                        $itemsPerPage = 100;
                        $currentPage = isset($_GET['page']) ? $_GET['page'] : 1;
                    @endphp
                    @forelse($atmData as $val)
                        @php
                            $arrData = json_decode($val->data, true);
                            $totalItems = count($arrData['Date']);
                            $newArr = array_reverse($arrData['Date'], true);
                            $currentItems = array_slice(
                                $newArr,
                                ($currentPage - 1) * $itemsPerPage,
                                $itemsPerPage,
                                true,
                            );
                        @endphp
                    @empty
                    @endforelse
                    @php
                        $DATE_NOW = array_slice($arrData['Date'],20);
                        $TIME_NOW = array_slice($arrData['time'],20);
                        $tableData[$stockName]['time'] = array_map(
                            function ($k, $y) use ($DATE_NOW) {
                                return date('d-M-Y', $DATE_NOW[$k] / 1000) . ', ' . date('g:i a', strtotime($y));
                            },
                            array_keys($DATE_NOW),
                            $TIME_NOW,
                        );

                        $tableData[$stockName]['CE'] = array_slice($arrData['CE'],20);
                        $tableData[$stockName]['PE'] = array_slice($arrData['PE'],20);

                        $tableData[$stockName]['CE_IV'] = array_slice($arrData['CE_IV'],20);
                        $tableData[$stockName]['CE_Delta'] = array_slice($arrData['CE_Delta'],20);
                        $tableData[$stockName]['CE_Theta'] = array_slice($arrData['CE_Theta'],20);
                        $tableData[$stockName]['CE_Vega'] = array_slice($arrData['CE_Vega'],20);
                        $tableData[$stockName]['CE_Gamma'] = array_slice($arrData['CE_Gamma'],20);
                        // FOR PE
                        $tableData[$stockName]['PE_IV'] = array_slice($arrData['PE_IV'],20);
                        $tableData[$stockName]['PE_Delta'] = array_slice($arrData['PE_Delta'],20);
                        $tableData[$stockName]['PE_Theta'] = array_slice($arrData['PE_Theta'],20);
                        $tableData[$stockName]['PE_Vega'] = array_slice($arrData['PE_Vega'],20);
                        $tableData[$stockName]['PE_Gamma'] = array_slice($arrData['PE_Gamma'],20);
                    @endphp
                @else
                    @foreach ($symbolArr as $v)
                        @php
                            if (
                                !in_array($v, [
                                    'CRUDEOIL',
                                    'BANKNIFTY',
                                    'FINNIFTY',
                                    'SILVER',
                                    'NIFTY',
                                    'MIDCPNIFTY',
                                    'NATURALGAS',
                                    'SILVER',
                                    'GOLD',
                                ])
                            ) {
                                continue;
                            }
                            if ($v == 'LTP') {
                            } else {
                                $dataLast = \DB::connection('mysql_rm')
                                    ->table($v)
                                    ->select('date')
                                    ->where(['timeframe' => $timeFrame])
                                    ->orderBy('id', 'DESC')
                                    ->first();
                                if ($dataLast) {
                                    $todayDate = $dataLast->date;
                                }
                                $data = \DB::connection('mysql_rm')
                                    ->table($v)
                                    ->select('*')
                                    ->where(['date' => $todayDate, 'timeframe' => $timeFrame])
                                    ->get();
                            }
                        @endphp
                        @php
                            $atmData = [];
                            foreach ($data as $vvl) {
                                if (isset($vvl->atm) && $vvl->atm == 'ATM') {
                                    $atmData[] = $vvl;
                                }
                            }
                        @endphp
                        @php $i=1; @endphp
                        @forelse($atmData as $val)
                            @php
                                $arrData = json_decode($val->data, true);
                                $CE = array_slice($arrData['CE'], -20);
                                $PE = array_slice($arrData['PE'], -20);
                                $Date = array_slice($arrData['Date'], -20);
                                $time = array_slice($arrData['time'], -20);
                                $CEIV = array_slice($arrData['CE_IV'], -20);
                                $PEIV = array_slice($arrData['PE_IV'], -20);
                                $CEDelta = array_slice($arrData['CE_Delta'], -20);
                                $PEDelta = array_slice($arrData['PE_Delta'], -20);
                                $CETheta = array_slice($arrData['CE_Theta'], -20);
                                $PETheta = array_slice($arrData['PE_Theta'], -20);
                                $CEVega = array_slice($arrData['CE_Vega'], -20);
                                $PEVega = array_slice($arrData['PE_Vega'], -20);
                                $CEGamma = array_slice($arrData['CE_Gamma'], -20);
                                $PEGamma = array_slice($arrData['PE_Gamma'], -20);
                            @endphp
                            @php
                                $tableData[$v]['time'] = array_map(
                                    function ($k, $y) use ($Date) {
                                        return date('d-M-Y', $Date[$k] / 1000) . ', ' . date('g:i a', strtotime($y));
                                    },
                                    array_keys($Date),
                                    $time,
                                );

                                $tableData[$v]['CE'] = $CE;
                                $tableData[$v]['PE'] = $PE;

                                $tableData[$v]['CE_IV'] = $CEIV;
                                $tableData[$v]['CE_Delta'] = $CEDelta;
                                $tableData[$v]['CE_Theta'] = $CETheta;
                                $tableData[$v]['CE_Vega'] = $CEVega;
                                $tableData[$v]['CE_Gamma'] = $CEGamma;
                                // FOR PE
                                $tableData[$v]['PE_IV'] = $PEIV;
                                $tableData[$v]['PE_Delta'] = $PEDelta;
                                $tableData[$v]['PE_Theta'] = $PETheta;
                                $tableData[$v]['PE_Vega'] = $PEVega;
                                $tableData[$v]['PE_Gamma'] = $PEGamma;
                            @endphp
                        @empty
                            @php
                                $time2 = '';
                                $CE2 = ['NO DATA'];
                                $PE2 = ['NO DATA'];
                                $close_CE2 = '';
                                $close_PE2 = '';
                            @endphp
                        @endforelse
                    @endforeach
                @endif
                @foreach ($tableData as $key => $item)
                    <div class="row mb-5">
                        <div class="col-lg-12">
                            <div class="custom--card card">
                                <div class="card-header">
                                    <h6 class="card-title">{{ $key }} <span class="text-warning">(CE-DELTA / PE-DELTA)</span></h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="card-body chart2">
                                        <div id="apex-analysis-chart-delta{{ $loop->index }}" style="width: 100%; min-height: 415px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-5">
                        <div class="col-lg-12">
                            <div class="custom--card card">
                                <div class="card-header">
                                    <h6 class="card-title">{{ $key }} <span class="text-warning">(CE-IV / PE-IV)</span></h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="card-body chart2">
                                        <div id="apex-analysis-chart-iv{{ $loop->index }}" style="width: 100%; min-height: 415px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-5">
                        <div class="col-lg-12">
                            <div class="custom--card card">
                                <div class="card-header">
                                    <h6 class="card-title">{{ $key }} <span class="text-warning">(CE-THETA / PE-THETA)</span></h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="card-body chart2">
                                        <div id="apex-analysis-chart-theta{{ $loop->index }}" style="width: 100%; min-height: 415px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-5">
                        <div class="col-lg-12">
                            <div class="custom--card card">
                                <div class="card-header">
                                    <h6 class="card-title">{{ $key }} <span class="text-warning">(CE-VEGA / PE-VEGA)</span></h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="card-body chart2">
                                        <div id="apex-analysis-chart-vega{{ $loop->index }}" style="width: 100%; min-height: 415px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-5">
                        <div class="col-lg-12">
                            <div class="custom--card card">
                                <div class="card-header">
                                    <h6 class="card-title">{{ $key }} <span class="text-warning">(CE-GAMMA / PE-GAMMA)</span></h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="card-body chart2">
                                        <div id="apex-analysis-chart-gamma{{ $loop->index }}" style="width: 100%; min-height: 415px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection


@push('script')

    <script src="{{ asset('assets/admin/js/vendor/apexcharts.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/vendor/chart.js.2.8.0.js') }}"></script>
    @isset($tableData)
        @foreach ($tableData as $key => $item)
            <script>
                var series = {
                    "monthDataSeries1": {
                        "prices": <?= json_encode($item['CE_Delta']) ?>,
                        "dates": <?= json_encode($item['time']) ?>
                    },
                    "monthDataSeries2": {
                        "prices": <?= json_encode($item['PE_Delta']) ?>,
                        "dates": <?= json_encode($item['time']) ?>
                    }
                }
                var options = {
                    chart: {
                        height: 400,
                        foreColor: '#E4E4E4',
                        type: "line",
                        id: "areachart-2",
                        zoom: {
                            enabled: false
                        },
                        toolbar: {
                            show: false,
                        }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        curve: "straight",
                        width: 2
                    },
                    colors: ['#00bf63', '#FF0000'],
                    series: [{
                            name: {!! json_encode($item['CE'][0]) !!},
                            data: series.monthDataSeries1.prices,
                        },
                        {
                            name: {!! json_encode($item['PE'][0]) !!},
                            data: series.monthDataSeries2.prices
                        }
                    ],
                    tooltip: {
                        enabled: true,
                        theme: 'dark',
                    },
                    labels: series.monthDataSeries1.dates,
                    xaxis: {
                        type: "category",
                        categories: <?= json_encode($item['time']) ?>,
                    },
                    noData: {
                        text: "NO DATA FOUND",
                        align: 'center',
                        verticalAlign: 'middle',
                        offsetX: 0,
                        offsetY: 0,
                    }
                };
                var chart = new ApexCharts(document.querySelector("#apex-analysis-chart-delta" + {{ $loop->index }} + ""), options);
                chart.render();
            </script>
            <script>
                var series = {
                    "monthDataSeries1": {
                        "prices": <?= json_encode($item['CE_IV']) ?>,
                        "dates": <?= json_encode($item['time']) ?>
                    },
                    "monthDataSeries2": {
                        "prices": <?= json_encode($item['PE_IV']) ?>,
                        "dates": <?= json_encode($item['time']) ?>
                    }
                }
                var options = {
                    chart: {
                        height: 400,
                        foreColor: '#E4E4E4',
                        type: "line",
                        id: "areachart-2",
                        zoom: {
                            enabled: false
                        },
                        toolbar: {
                            show: false,
                        }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        curve: "straight",
                        width: 2
                    },
                    colors: ['#00bf63', '#FF0000'],
                    series: [{
                            name: {!! json_encode($item['CE'][0]) !!},
                            data: series.monthDataSeries1.prices,
                        },
                        {
                            name: {!! json_encode($item['PE'][0]) !!},
                            data: series.monthDataSeries2.prices
                        }
                    ],
                    tooltip: {
                        enabled: true,
                        theme: 'dark',
                    },
                    labels: series.monthDataSeries1.dates,
                    xaxis: {
                        type: "category",
                        categories: <?= json_encode($item['time']) ?>,
                    },
                    noData: {
                        text: "NO DATA FOUND",
                        align: 'center',
                        verticalAlign: 'middle',
                        offsetX: 0,
                        offsetY: 0,
                    }
                };
                var chart = new ApexCharts(document.querySelector("#apex-analysis-chart-iv" + {{ $loop->index }} + ""), options);
                chart.render();
            </script>
            <script>
                var series = {
                    "monthDataSeries1": {
                        "prices": <?= json_encode($item['CE_Theta']) ?>,
                        "dates": <?= json_encode($item['time']) ?>
                    },
                    "monthDataSeries2": {
                        "prices": <?= json_encode($item['PE_Theta']) ?>,
                        "dates": <?= json_encode($item['time']) ?>
                    }
                }
                var options = {
                    chart: {
                        height: 400,
                        foreColor: '#E4E4E4',
                        type: "line",
                        id: "areachart-2",
                        zoom: {
                            enabled: false
                        },
                        toolbar: {
                            show: false,
                        }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        curve: "straight",
                        width: 2
                    },
                    colors: ['#00bf63', '#FF0000'],
                    series: [{
                            name: {!! json_encode($item['CE'][0]) !!},
                            data: series.monthDataSeries1.prices,
                        },
                        {
                            name: {!! json_encode($item['PE'][0]) !!},
                            data: series.monthDataSeries2.prices
                        }
                    ],
                    tooltip: {
                        enabled: true,
                        theme: 'dark',
                    },
                    labels: series.monthDataSeries1.dates,
                    xaxis: {
                        type: "category",
                        categories: <?= json_encode($item['time']) ?>,
                    },
                    noData: {
                        text: "NO DATA FOUND",
                        align: 'center',
                        verticalAlign: 'middle',
                        offsetX: 0,
                        offsetY: 0,
                    }
                };
                var chart = new ApexCharts(document.querySelector("#apex-analysis-chart-theta" + {{ $loop->index }} + ""), options);
                chart.render();
            </script>
            <script>
                var series = {
                    "monthDataSeries1": {
                        "prices": <?= json_encode($item['CE_Vega']) ?>,
                        "dates": <?= json_encode($item['time']) ?>
                    },
                    "monthDataSeries2": {
                        "prices": <?= json_encode($item['PE_Vega']) ?>,
                        "dates": <?= json_encode($item['time']) ?>
                    }
                }
                var options = {
                    chart: {
                        height: 400,
                        foreColor: '#E4E4E4',
                        type: "line",
                        id: "areachart-2",
                        zoom: {
                            enabled: false
                        },
                        toolbar: {
                            show: false,
                        }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        curve: "straight",
                        width: 2
                    },
                    colors: ['#00bf63', '#FF0000'],
                    series: [{
                            name: {!! json_encode($item['CE'][0]) !!},
                            data: series.monthDataSeries1.prices,
                        },
                        {
                            name: {!! json_encode($item['PE'][0]) !!},
                            data: series.monthDataSeries2.prices
                        }
                    ],
                    tooltip: {
                        enabled: true,
                        theme: 'dark',
                    },
                    labels: series.monthDataSeries1.dates,
                    xaxis: {
                        type: "category",
                        categories: <?= json_encode($item['time']) ?>,
                    },
                    noData: {
                        text: "NO DATA FOUND",
                        align: 'center',
                        verticalAlign: 'middle',
                        offsetX: 0,
                        offsetY: 0,
                    }
                };
                var chart = new ApexCharts(document.querySelector("#apex-analysis-chart-vega" + {{ $loop->index }} + ""), options);
                chart.render();
            </script>
            <script>
                var series = {
                    "monthDataSeries1": {
                        "prices": <?= json_encode($item['CE_Gamma']) ?>,
                        "dates": <?= json_encode($item['time']) ?>
                    },
                    "monthDataSeries2": {
                        "prices": <?= json_encode($item['PE_Gamma']) ?>,
                        "dates": <?= json_encode($item['time']) ?>
                    }
                }
                var options = {
                    chart: {
                        height: 400,
                        foreColor: '#E4E4E4',
                        type: "line",
                        id: "areachart-2",
                        zoom: {
                            enabled: false
                        },
                        toolbar: {
                            show: false,
                        }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        curve: "straight",
                        width: 2
                    },
                    colors: ['#00bf63', '#FF0000'],
                    series: [{
                            name: {!! json_encode($item['CE'][0]) !!},
                            data: series.monthDataSeries1.prices,
                        },
                        {
                            name: {!! json_encode($item['PE'][0]) !!},
                            data: series.monthDataSeries2.prices
                        }
                    ],
                    tooltip: {
                        enabled: true,
                        theme: 'dark',
                    },
                    labels: series.monthDataSeries1.dates,
                    xaxis: {
                        type: "category",
                        categories: <?= json_encode($item['time']) ?>,
                    },
                    noData: {
                        text: "NO DATA FOUND",
                        align: 'center',
                        verticalAlign: 'middle',
                        offsetX: 0,
                        offsetY: 0,
                    }
                };
                var chart = new ApexCharts(document.querySelector("#apex-analysis-chart-gamma" + {{ $loop->index }} + ""), options);
                chart.render();
            </script>
        @endforeach
    @endisset
@endpush

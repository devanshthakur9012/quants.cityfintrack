@extends($activeTemplate . 'layouts.master')

@section('content')
    @push('style')
        <style>
            .config-card {
                background: #fff;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .indicator-section {
                border-left: 4px solid #3498db;
                padding-left: 15px;
                margin-bottom: 25px;
            }

            .indicator-section h5 {
                color: #2c3e50;
                margin-bottom: 15px;
            }

            .form-group-with-tooltip {
                position: relative;
            }

            .info-icon {
                color: #3498db;
                cursor: help;
                margin-left: 5px;
                font-size: 0.9rem;
            }

            .tooltip-custom {
                position: absolute;
                background: #2c3e50;
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 0.85rem;
                max-width: 300px;
                z-index: 1000;
                display: none;
                white-space: normal;
                line-height: 1.4;
            }

            .symbol-config-row {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 6px;
                margin-bottom: 10px;
                border: 1px solid #dee2e6;
            }

            .btn-recalculate {
                background: #ff9800;
                color: white;
            }

            .btn-recalculate:hover {
                background: #f57c00;
                color: white;
            }
        </style>
    @endpush

    <section class="pt-50 pb-50">
        <div class="container content-container">
            <!-- Header -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4>{{ $pageTitle }}</h4>
                        <p class="text-muted">Configure technical indicators for futures analysis</p>
                    </div>
                    <div>
                        <button type="button" id="recalculate_all_btn" class="btn btn-recalculate">
                            <i class="fas fa-sync-alt"></i> Recalculate All Indicators
                        </button>
                    </div>
                </div>
            </div>

            <!-- Global Configuration -->
            <div class="config-card">
                <h4 class="mb-3 text-dark">Global Default Configuration</h4>
                <p class="text-muted mb-4">These settings apply to all symbols unless overridden by symbol-specific
                    configuration</p>

                <form id="global_config_form">
                    @csrf

                    <!-- Supertrend Section -->
                    <div class="indicator-section">
                        <h5><i class="fas fa-chart-line"></i> Supertrend Indicator</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        ATR Period
                                        <i class="fas fa-info-circle info-icon" data-tooltip="supertrend-atr"></i>
                                    </label>
                                    <input type="number" name="supertrend_atr_period" class="form-control"
                                        value="{{ $globalConfig->supertrend_atr_period ?? 10 }}" min="1"
                                        max="100">
                                    <div class="tooltip-custom" id="tooltip-supertrend-atr">
                                        {{ $descriptions['supertrend']['atr_period'] }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        Multiplier
                                        <i class="fas fa-info-circle info-icon" data-tooltip="supertrend-mult"></i>
                                    </label>
                                    <input type="number" name="supertrend_multiplier" class="form-control"
                                        value="{{ $globalConfig->supertrend_multiplier ?? 3 }}" min="0.1"
                                        max="10" step="0.1">
                                    <div class="tooltip-custom" id="tooltip-supertrend-mult">
                                        {{ $descriptions['supertrend']['multiplier'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Donchian Section -->
                    <div class="indicator-section d-none">
                        <h5><i class="fas fa-chart-area"></i> Donchian Channel</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        High Period
                                        <i class="fas fa-info-circle info-icon" data-tooltip="donchian-high"></i>
                                    </label>
                                    <input type="number" name="donchian_high_period" class="form-control"
                                        value="{{ $globalConfig->donchian_high_period ?? 20 }}" min="1"
                                        max="100">
                                    <div class="tooltip-custom" id="tooltip-donchian-high">
                                        {{ $descriptions['donchian']['high_period'] }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        Low Period
                                        <i class="fas fa-info-circle info-icon" data-tooltip="donchian-low"></i>
                                    </label>
                                    <input type="number" name="donchian_low_period" class="form-control"
                                        value="{{ $globalConfig->donchian_low_period ?? 20 }}" min="1"
                                        max="100">
                                    <div class="tooltip-custom" id="tooltip-donchian-low">
                                        {{ $descriptions['donchian']['low_period'] }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        Risk:Reward Ratio
                                        <i class="fas fa-info-circle info-icon" data-tooltip="donchian-rr"></i>
                                    </label>
                                    <input type="number" name="donchian_risk_reward" class="form-control"
                                        value="{{ $globalConfig->donchian_risk_reward ?? 2 }}" min="0.5" max="10"
                                        step="0.1">
                                    <div class="tooltip-custom" id="tooltip-donchian-rr">
                                        {{ $descriptions['donchian']['risk_reward'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RSI Section -->
                    <div class="indicator-section d-none">
                        <h5><i class="fas fa-tachometer-alt"></i> RSI (Relative Strength Index)</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        Period
                                        <i class="fas fa-info-circle info-icon" data-tooltip="rsi-period"></i>
                                    </label>
                                    <input type="number" name="rsi_period" class="form-control"
                                        value="{{ $globalConfig->rsi_period ?? 14 }}" min="2" max="100">
                                    <div class="tooltip-custom" id="tooltip-rsi-period">
                                        {{ $descriptions['rsi']['period'] }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        Overbought Level
                                        <i class="fas fa-info-circle info-icon" data-tooltip="rsi-ob"></i>
                                    </label>
                                    <input type="number" name="rsi_overbought" class="form-control"
                                        value="{{ $globalConfig->rsi_overbought ?? 70 }}" min="50" max="100">
                                    <div class="tooltip-custom" id="tooltip-rsi-ob">
                                        {{ $descriptions['rsi']['overbought'] }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        Oversold Level
                                        <i class="fas fa-info-circle info-icon" data-tooltip="rsi-os"></i>
                                    </label>
                                    <input type="number" name="rsi_oversold" class="form-control"
                                        value="{{ $globalConfig->rsi_oversold ?? 30 }}" min="0" max="50">
                                    <div class="tooltip-custom" id="tooltip-rsi-os">
                                        {{ $descriptions['rsi']['oversold'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MACD Section -->
                    <div class="indicator-section d-none">
                        <h5><i class="fas fa-signal"></i> MACD (Moving Average Convergence Divergence)</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        Fast Period
                                        <i class="fas fa-info-circle info-icon" data-tooltip="macd-fast"></i>
                                    </label>
                                    <input type="number" name="macd_fast_period" class="form-control"
                                        value="{{ $globalConfig->macd_fast_period ?? 12 }}" min="1"
                                        max="50">
                                    <div class="tooltip-custom" id="tooltip-macd-fast">
                                        {{ $descriptions['macd']['fast_period'] }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        Slow Period
                                        <i class="fas fa-info-circle info-icon" data-tooltip="macd-slow"></i>
                                    </label>
                                    <input type="number" name="macd_slow_period" class="form-control"
                                        value="{{ $globalConfig->macd_slow_period ?? 26 }}" min="1"
                                        max="100">
                                    <div class="tooltip-custom" id="tooltip-macd-slow">
                                        {{ $descriptions['macd']['slow_period'] }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        Signal Period
                                        <i class="fas fa-info-circle info-icon" data-tooltip="macd-signal"></i>
                                    </label>
                                    <input type="number" name="macd_signal_period" class="form-control"
                                        value="{{ $globalConfig->macd_signal_period ?? 9 }}" min="1"
                                        max="50">
                                    <div class="tooltip-custom" id="tooltip-macd-signal">
                                        {{ $descriptions['macd']['signal_period'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="indicator-section d-none">
                        <h5><i class="fas fa-chart-bar"></i> VWAP (Volume Weighted Average Price)</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        Reset Daily
                                        <i class="fas fa-info-circle info-icon" data-tooltip="vwap-reset"></i>
                                    </label>
                                    <select name="vwap_reset_daily" class="form-control">
                                        <option value="1"
                                            {{ $globalConfig->vwap_reset_daily ?? true ? 'selected' : '' }}>Yes (Reset at
                                            9:15 AM)</option>
                                        <option value="0"
                                            {{ !($globalConfig->vwap_reset_daily ?? true) ? 'selected' : '' }}>No
                                            (Continuous)</option>
                                    </select>
                                    <div class="tooltip-custom" id="tooltip-vwap-reset">
                                        {{ $descriptions['vwap']['reset_daily'] }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        Band Multiplier
                                        <i class="fas fa-info-circle info-icon" data-tooltip="vwap-mult"></i>
                                    </label>
                                    <input type="number" name="vwap_band_multiplier" class="form-control"
                                        value="{{ $globalConfig->vwap_band_multiplier ?? 1.0 }}" min="0.1"
                                        max="5" step="0.1">
                                    <div class="tooltip-custom" id="tooltip-vwap-mult">
                                        {{ $descriptions['vwap']['band_multiplier'] }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        Band Period
                                        <i class="fas fa-info-circle info-icon" data-tooltip="vwap-period"></i>
                                    </label>
                                    <input type="number" name="vwap_band_period" class="form-control"
                                        value="{{ $globalConfig->vwap_band_period ?? 20 }}" min="5"
                                        max="100">
                                    <div class="tooltip-custom" id="tooltip-vwap-period">
                                        {{ $descriptions['vwap']['band_period'] }}
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group-with-tooltip">
                                    <label class="text-dark">
                                        Distance Percent (%)
                                        <i class="fas fa-info-circle info-icon" data-tooltip="vwap-distance"></i>
                                    </label>
                                    <input type="number" name="vwap_distance_percent" class="form-control"
                                        value="{{ $globalConfig->vwap_distance_percent ?? 0.4 }}" min="0.1"
                                        max="5" step="0.1">
                                    <div class="tooltip-custom" id="tooltip-vwap-distance">
                                        {{ $descriptions['vwap']['distance_percent'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Global Configuration
                    </button>
                </form>
            </div>

            <!-- Symbol-Specific Configurations -->
            {{-- <div class="config-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Symbol-Specific Configurations</h4>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSymbolModal">
                    <i class="fas fa-plus"></i> Add Symbol Config
                </button>
            </div>

            <div id="symbol-configs-list">
                @if ($symbolConfigs->isEmpty())
                    <p class="text-muted text-center py-4">No symbol-specific configurations yet. All symbols use global defaults.</p>
                @else
                    @foreach ($symbolConfigs as $config)
                    <div class="symbol-config-row">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6><strong>{{ $config->trading_symbol }}</strong></h6>
                                <small class="text-muted">
                                    ST: {{ $config->supertrend_atr_period }}/{{ $config->supertrend_multiplier }} | 
                                    Donchian: {{ $config->donchian_high_period }}/{{ $config->donchian_low_period }} | 
                                    RSI: {{ $config->rsi_period }} | 
                                    MACD: {{ $config->macd_fast_period }}/{{ $config->macd_slow_period }}/{{ $config->macd_signal_period }}
                                </small>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-warning me-2 recalculate-symbol" data-symbol="{{ $config->trading_symbol }}">
                                    <i class="fas fa-sync-alt"></i> Recalculate
                                </button>
                                <button class="btn btn-sm btn-danger delete-symbol-config" data-id="{{ $config->id }}">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>
        </div> --}}

        </div>
    </section>

    <!-- Add Symbol Modal -->
    <div class="modal fade" id="addSymbolModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-dark">Add Symbol-Specific Configuration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="symbol_config_form">
                        @csrf
                        <div class="mb-3">
                            <label class="text-dark">Trading Symbol *</label>
                            <select name="trading_symbol" class="form-control" required>
                                <option value="">-- Select Symbol --</option>
                                @foreach ($monitoredFutures as $future)
                                    <option value="{{ $future->trading_symbol }}">{{ $future->trading_symbol }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Copy same indicator fields as global config -->
                        <!-- Supertrend -->
                        <h6 class="text-dark">Supertrend</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="text-dark">ATR Period</label>
                                <input type="number" name="supertrend_atr_period" class="form-control" value="10"
                                    min="1" max="100">
                            </div>
                            <div class="col-md-6">
                                <label class="text-dark">Multiplier</label>
                                <input type="number" name="supertrend_multiplier" class="form-control" value="3"
                                    min="0.1" max="10" step="0.1">
                            </div>
                        </div>

                        <!-- Donchian -->
                        {{-- <h6 class="text-dark">Donchian Channel</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="text-dark">High Period</label>
                                <input type="number" name="donchian_high_period" class="form-control" value="20"
                                    min="1" max="100">
                            </div>
                            <div class="col-md-4">
                                <label class="text-dark">Low Period</label>
                                <input type="number" name="donchian_low_period" class="form-control" value="20"
                                    min="1" max="100">
                            </div>
                            <div class="col-md-4">
                                <label class="text-dark">Risk:Reward</label>
                                <input type="number" name="donchian_risk_reward" class="form-control" value="2"
                                    min="0.5" max="10" step="0.1">
                            </div>
                        </div> --}}

                        <!-- RSI -->
                        {{-- <h6 class="text-dark">RSI</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="text-dark">Period</label>
                                <input type="number" name="rsi_period" class="form-control" value="14"
                                    min="2" max="100">
                            </div>
                            <div class="col-md-4">
                                <label class="text-dark">Overbought</label>
                                <input type="number" name="rsi_overbought" class="form-control" value="70"
                                    min="50" max="100">
                            </div>
                            <div class="col-md-4">
                                <label class="text-dark">Oversold</label>
                                <input type="number" name="rsi_oversold" class="form-control" value="30"
                                    min="0" max="50">
                            </div>
                        </div> --}}

                        <!-- MACD -->
                        {{-- <h6 class="text-dark">MACD</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="text-dark">Fast Period</label>
                                <input type="number" name="macd_fast_period" class="form-control" value="12"
                                    min="1" max="50">
                            </div>
                            <div class="col-md-4">
                                <label class="text-dark">Slow Period</label>
                                <input type="number" name="macd_slow_period" class="form-control" value="26"
                                    min="1" max="100">
                            </div>
                            <div class="col-md-4">
                                <label class="text-dark">Signal Period</label>
                                <input type="number" name="macd_signal_period" class="form-control" value="9"
                                    min="1" max="50">
                            </div>
                        </div> --}}

                        {{-- <h6 class="text-dark">VWAP</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="text-dark">Reset Daily</label>
                                <select name="vwap_reset_daily" class="form-control">
                                    <option value="1" selected>Yes (Reset at 9:15 AM)</option>
                                    <option value="0">No (Continuous)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="text-dark">Band Multiplier</label>
                                <input type="number" name="vwap_band_multiplier" class="form-control" value="1.0"
                                    min="0.1" max="5" step="0.1">
                            </div>
                            <div class="col-md-4">
                                <label class="text-dark">Band Period</label>
                                <input type="number" name="vwap_band_period" class="form-control" value="20"
                                    min="5" max="100">
                            </div>
                            <div class="col-md-3">
                                <label class="text-dark">Distance Percent (%)</label>
                                <input type="number" name="vwap_distance_percent" class="form-control" value="0.4" min="0.1" max="5" step="0.1">
                            </div>
                        </div> --}}

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Configuration
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).ready(function() {
            // Tooltip functionality
            $('.info-icon').hover(
                function() {
                    const tooltipId = '#tooltip-' + $(this).data('tooltip');
                    const $tooltip = $(tooltipId);
                    const iconOffset = $(this).offset();

                    $tooltip.css({
                        top: iconOffset.top + 25,
                        left: iconOffset.left - 150,
                        display: 'block'
                    });
                },
                function() {
                    $('.tooltip-custom').hide();
                }
            );

            // Global config form submission
            $('#global_config_form').submit(function(e) {
                e.preventDefault();

                $.ajax({
                    url: '{{ route('futures.config.update-global') }}',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        alert(response.message);
                        if (response.success) {
                            location.reload();
                        }
                    },
                    error: function(xhr) {
                        alert(xhr.responseJSON?.message || 'Error updating configuration');
                    }
                });
            });

            // Symbol config form submission
            $('#symbol_config_form').submit(function(e) {
                e.preventDefault();

                $.ajax({
                    url: '{{ route('futures.config.update-symbol') }}',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        alert(response.message);
                        if (response.success) {
                            $('#addSymbolModal').modal('hide');
                            location.reload();
                        }
                    },
                    error: function(xhr) {
                        alert(xhr.responseJSON?.message || 'Error saving configuration');
                    }
                });
            });

            // Delete symbol config
            $(document).on('click', '.delete-symbol-config', function() {
                if (!confirm('Are you sure you want to delete this configuration?')) {
                    return;
                }

                const id = $(this).data('id');

                $.ajax({
                    url: '{{ route('futures.config.delete-symbol', ['id' => ':id']) }}'.replace(
                        ':id', id),
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        alert(response.message);
                        location.reload();
                    },
                    error: function(xhr) {
                        alert(xhr.responseJSON?.message || 'Error deleting configuration');
                    }
                });
            });

            // Recalculate single symbol
            $(document).on('click', '.recalculate-symbol', function() {
                const symbol = $(this).data('symbol');

                const interval = prompt('Select interval (minute / 5minute / 15minute):', '15minute');
                if (!interval) return;

                if (!['minute', '5minute', '15minute'].includes(interval)) {
                    alert('Invalid interval');
                    return;
                }

                if (!confirm(`Recalculate indicators for ${symbol} (${interval})?`)) {
                    return;
                }

                const $btn = $(this);
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Processing...');

                $.ajax({
                    url: '{{ route('futures.config.recalculate') }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        trading_symbol: symbol,
                        interval: interval
                    },
                    success: function(response) {
                        alert(response.message);
                        $btn.prop('disabled', false);
                        $btn.html('<i class="fas fa-sync-alt"></i> Recalculate');
                    },
                    error: function(xhr) {
                        alert(xhr.responseJSON?.message || 'Error recalculating');
                        $btn.prop('disabled', false);
                        $btn.html('<i class="fas fa-sync-alt"></i> Recalculate');
                    }
                });
            });

            // Recalculate all
            $('#recalculate_all_btn').click(function() {
                if (!confirm(
                        'This will recalculate ALL indicators for ALL symbols and ALL intervals. This may take several minutes. Continue?'
                        )) {
                    return;
                }

                const $btn = $(this);
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Processing...');

                $.ajax({
                    url: '{{ route('futures.config.recalculate-all') }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        alert(response.message);
                        $btn.prop('disabled', false);
                        $btn.html('<i class="fas fa-sync-alt"></i> Recalculate All Indicators');
                    },
                    error: function(xhr) {
                        alert(xhr.responseJSON?.message || 'Error recalculating');
                        $btn.prop('disabled', false);
                        $btn.html('<i class="fas fa-sync-alt"></i> Recalculate All Indicators');
                    }
                });
            });
        });
    </script>
@endpush

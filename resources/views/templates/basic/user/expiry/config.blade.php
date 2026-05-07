@extends($activeTemplate . 'layouts.master')

@section('content')
    <section class="pt-50 pb-50">
        <div class="container content-container">
            {{-- Header --}}
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4>{{ $pageTitle }}</h4>
                        <p class="text-muted">Configure Supertrend indicator for both expiry trading and symbol analysis</p>
                        <small class="text-info">
                            <i class="fas fa-info-circle"></i> 
                            Recalculation applies to: <strong>Expiry Data (1-min)</strong> & <strong>Symbol Data (1-min, 5-min, 15-min)</strong>
                        </small>
                    </div>
                    <div>
                        <button type="button" id="recalculate_all_btn" class="btn btn-warning">
                            <i class="fas fa-sync-alt"></i> Recalculate All Indicators
                        </button>
                    </div>
                </div>
            </div>

            {{-- Info Alert --}}
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-lightbulb"></i>
                <strong>Note:</strong> Changes to ATR Period or Multiplier will affect both:
                <ul class="mb-0 mt-2">
                    <li><strong>Expiry Data:</strong> 1-minute candles for option trading</li>
                    <li><strong>Symbol Data:</strong> 1-minute, 5-minute, and 15-minute candles for technical analysis</li>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

            {{-- Global Configuration --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 text-dark">Global Default Configuration</h5>
                    <p class="text-muted mb-0 small">Applies to all symbols (both expiry and symbol data) unless overridden</p>
                </div>
                <div class="card-body">
                    <form id="global_config_form">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="text-dark">
                                        ATR Period
                                        <i class="fas fa-info-circle text-primary"
                                            title="{{ $descriptions['supertrend']['atr_period'] }}"></i>
                                    </label>
                                    <input type="number" name="supertrend_atr_period" class="form-control"
                                        value="{{ $globalConfig->supertrend_atr_period ?? 10 }}" min="1"
                                        max="100" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="text-dark">
                                        Multiplier
                                        <i class="fas fa-info-circle text-primary"
                                            title="{{ $descriptions['supertrend']['multiplier'] }}"></i>
                                    </label>
                                    <input type="number" name="supertrend_multiplier" class="form-control"
                                        value="{{ $globalConfig->supertrend_multiplier ?? 3 }}" min="0.1"
                                        max="10" step="0.1" required>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Global Configuration
                        </button>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            After saving, click "Recalculate All Indicators" to apply changes to existing data
                        </small>
                    </form>
                </div>
            </div>

            {{-- Symbol-Specific Configurations --}}
            {{-- <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-dark">Symbol-Specific Configurations</h5>
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                            data-bs-target="#addSymbolModal">
                            <i class="fas fa-plus"></i> Add Symbol Config
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="symbol-configs-list">
                        @if ($symbolConfigs->isEmpty())
                            <p class="text-muted text-center py-4">
                                No symbol-specific configurations yet. All symbols use global defaults.
                            </p>
                        @else
                            @foreach ($symbolConfigs as $config)
                                <div class="card mb-2">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><strong>{{ $config->symbol }}</strong></h6>
                                                <small class="text-muted">
                                                    ATR Period: {{ $config->supertrend_atr_period }} |
                                                    Multiplier: {{ $config->supertrend_multiplier }}
                                                </small>
                                                <br>
                                                <small class="text-info">
                                                    <i class="fas fa-database"></i> Applies to both expiry & symbol data
                                                </small>
                                            </div>
                                            <div>
                                                <button class="btn btn-sm btn-warning me-2 recalculate-symbol"
                                                    data-symbol="{{ $config->symbol }}"
                                                    title="Recalculates both expiry and symbol data">
                                                    <i class="fas fa-sync-alt"></i> Recalculate
                                                </button>
                                                <button class="btn btn-sm btn-danger delete-symbol-config"
                                                    data-id="{{ $config->id }}">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div> --}}
        </div>
    </section>

    {{-- Add Symbol Modal --}}
    <div class="modal fade" id="addSymbolModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-dark">Add Symbol Configuration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="symbol_config_form">
                        @csrf
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle"></i>
                                This configuration will apply to both expiry data and symbol data for the selected symbol
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="text-dark">Symbol *</label>
                            <select name="symbol" class="form-control" required>
                                <option value="">-- Select Symbol --</option>
                                @foreach ($monitoredSymbols as $symbol)
                                    <option value="{{ $symbol->symbol }}">{{ $symbol->symbol }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <label class="text-dark">ATR Period</label>
                                <input type="number" name="supertrend_atr_period" class="form-control" value="10"
                                    min="1" max="100" required>
                            </div>
                            <div class="col-md-6">
                                <label class="text-dark">Multiplier</label>
                                <input type="number" name="supertrend_multiplier" class="form-control" value="3"
                                    min="0.1" max="10" step="0.1" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="fas fa-save"></i> Save Configuration
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('script')
        <script>
            $(document).ready(function() {
                // Global config form
                $('#global_config_form').submit(function(e) {
                    e.preventDefault();
                    
                    const $btn = $(this).find('button[type="submit"]');
                    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                    
                    $.ajax({
                        url: '{{ route('expiry.config.update-global') }}',
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            alert(response.message + '\n\nReminder: Click "Recalculate All Indicators" to apply changes to existing data.');
                            if (response.success) location.reload();
                        },
                        error: function(xhr) {
                            alert(xhr.responseJSON?.message || 'Error updating configuration');
                            $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Global Configuration');
                        }
                    });
                });

                // Symbol config form
                $('#symbol_config_form').submit(function(e) {
                    e.preventDefault();
                    
                    const $btn = $(this).find('button[type="submit"]');
                    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                    
                    $.ajax({
                        url: '{{ route('expiry.config.update-symbol') }}',
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
                            $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Configuration');
                        }
                    });
                });

                // Delete symbol config
                $(document).on('click', '.delete-symbol-config', function() {
                    if (!confirm('Delete this configuration? The symbol will revert to using global defaults.')) return;

                    const id = $(this).data('id');
                    $.ajax({
                        url: '{{ route('expiry.config.delete-symbol', ':id') }}'.replace(':id', id),
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
                    if (!confirm(`Recalculate indicators for ${symbol}?\n\nThis will update:\n- Expiry data (1-min)\n- Symbol data (1-min, 5-min, 15-min)\n\nThis may take 1-2 minutes.`)) return;

                    const $btn = $(this);
                    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

                    $.ajax({
                        url: '{{ route('expiry.config.recalculate') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            symbol: symbol
                        },
                        success: function(response) {
                            alert(response.message + '\n\nRecalculation completed successfully!');
                            $btn.prop('disabled', false).html(
                                '<i class="fas fa-sync-alt"></i> Recalculate');
                        },
                        error: function(xhr) {
                            alert(xhr.responseJSON?.message || 'Error recalculating');
                            $btn.prop('disabled', false).html(
                                '<i class="fas fa-sync-alt"></i> Recalculate');
                        }
                    });
                });

                // Recalculate all
                $('#recalculate_all_btn').click(function() {
                    if (!confirm('Recalculate ALL indicators for ALL symbols?\n\nThis will update:\n- All expiry data (1-min)\n- All symbol data (1-min, 5-min, 15-min)\n\nThis may take 5-10 minutes. Continue?')) return;

                    const $btn = $(this);
                    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

                    $.ajax({
                        url: '{{ route('expiry.config.recalculate-all') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            alert(response.message + '\n\nAll indicators have been recalculated!');
                            $btn.prop('disabled', false).html(
                                '<i class="fas fa-sync-alt"></i> Recalculate All Indicators');
                        },
                        error: function(xhr) {
                            alert(xhr.responseJSON?.message || 'Error recalculating');
                            $btn.prop('disabled', false).html(
                                '<i class="fas fa-sync-alt"></i> Recalculate All Indicators');
                        }
                    });
                });
            });
        </script>
    @endpush
@endsection
@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    .astro-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    
    .planet-badge {
        display: inline-block;
        background: rgba(255,255,255,0.2);
        padding: 5px 12px;
        border-radius: 20px;
        margin: 3px;
        font-size: 11px;
        backdrop-filter: blur(10px);
    }
    
    .aspect-card {
        background: white;
        border-left: 4px solid #667eea;
        padding: 15px;
        margin: 10px 0;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .aspect-trine { border-left-color: #28a745; }
    .aspect-sextile { border-left-color: #17a2b8; }
    .aspect-square { border-left-color: #dc3545; }
    .aspect-opposition { border-left-color: #fd7e14; }
    .aspect-conjunction { border-left-color: #6f42c1; }
    
    .conviction-meter {
        height: 30px;
        background: #e9ecef;
        border-radius: 15px;
        overflow: hidden;
        position: relative;
    }
    
    .conviction-fill {
        height: 100%;
        background: linear-gradient(90deg, #dc3545, #ffc107, #28a745);
        transition: width 0.5s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 13px;
    }
    
    .volatility-indicator {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin: 0 3px;
    }
    
    .vol-low { background: #28a745; }
    .vol-medium { background: #ffc107; }
    .vol-high { background: #dc3545; }
    
    .sector-pill {
        display: inline-block;
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        margin: 4px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .stock-chip {
        display: inline-block;
        background: #f8f9fa;
        border: 2px solid #dee2e6;
        padding: 5px 12px;
        border-radius: 12px;
        margin: 3px;
        font-size: 11px;
        font-weight: 600;
        color: #495057;
    }
    
    .act-section {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        margin: 20px 0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    
    .act-title {
        font-size: 22px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 3px solid #667eea;
    }
    
    .daily-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        margin: 15px 0;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .daily-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .day-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #edf2f7;
    }
    
    .bias-badge {
        padding: 8px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 13px;
    }
    
    .bias-bullish { background: #d4edda; color: #155724; }
    .bias-bearish { background: #f8d7da; color: #721c24; }
    .bias-neutral { background: #d1ecf1; color: #0c5460; }
    
    .strategy-box {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        border-radius: 12px;
        padding: 25px;
        margin: 20px 0;
    }
    
    .strategy-box h5 {
        color: white;
        font-weight: 700;
        margin-bottom: 15px;
    }
    
    .strategy-box ul {
        list-style: none;
        padding: 0;
    }
    
    .strategy-box li {
        padding: 8px 0;
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    
    .strategy-box li:last-child {
        border-bottom: none;
    }
    
    .filter-panel {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
    }
    
    /* Select2 Custom Styling */
    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 6px;
        border: 1px solid #ced4da;
        min-height: 38px;
    }
    
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        background-color: #667eea;
        border-color: #667eea;
        color: white;
        padding: 3px 8px;
        border-radius: 4px;
        margin: 3px;
    }
    
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
        color: white;
        margin-right: 5px;
    }
    
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #ff6b6b;
    }
    
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    
    .loading-content {
        background: white;
        padding: 40px;
        border-radius: 15px;
        text-align: center;
    }
    
    .zodiac-icon {
        font-size: 24px;
        margin-right: 8px;
    }
    .selection{
        width:100% !important;
    }
   .content-container p{
        color:#000;
    }
    .content-container .planet-badge{
        color:#000;
    }
    .content-container strong{
        color:#000;
    }
    .content-container ul li{
        color:#000;
    }
    .content-container .text-muted{
        color:#000;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">
        <!-- Header -->
        <div class="astro-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">🌟 Financial Astrology - Weekly Market Prediction</h2>
                    <p class="mb-0 opacity-75">Advanced planetary analysis combined with options market data</p>
                </div>
                <div>
                    <button id="generateReport" class="btn btn-light btn-lg">
                        <i class="las la-magic"></i> Generate Weekly Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="filter-panel">
            <h5 class="mb-3 text-dark"><i class="las la-sliders-h"></i> Report Configuration</h5>
            <div class="row g-3">
                <div class="col-lg-3 col-md-6">
                    <label class="text-dark fw-bold mb-2">Start Date (Monday):</label>
                    <input type="date" id="startDate" class="form-control" value="{{ now()->startOfWeek()->format('Y-m-d') }}">
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <label class="text-dark fw-bold mb-2">Trading Days:</label>
                    <select id="tradingDays" class="form-select">
                        <option value="5" selected>5 Days (Mon-Fri)</option>
                        <option value="7">7 Days (Full Week)</option>
                    </select>
                </div>

                <div class="col-lg-4 col-md-12">
                    <label class="text-dark fw-bold mb-2">Analyze Symbols (Optional):</label>
                    <select id="symbolSelect" class="form-select select2-multiple" multiple>
                        <option value="">-- Select Symbols --</option>
                        @foreach ($symbols as $symbol)
                            <option value="{{ $symbol }}">{{ $symbol }}</option>
                        @endforeach
                    </select>
                    <small class="">Leave empty to analyze all symbols</small>
                </div>

                <div class="col-lg-3 col-md-12 d-flex align-items-end">
                    <button id="generateReport2" class="btn btn--base w-100">
                        <i class="las la-rocket"></i> Generate Astro Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-content">
                <i class="las la-sun la-spin" style="font-size: 60px; color: #667eea;"></i>
                <h4 class="mt-3">Calculating Planetary Positions...</h4>
                <p class="">Analyzing market data with astrological overlay</p>
            </div>
        </div>

        <!-- Report Container -->
        <div id="reportContainer"></div>

    </div>
</section>
@endsection

@push('script')
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    
    // Initialize Select2 for multi-select
    $('#symbolSelect').select2({
        theme: 'bootstrap-5',
        placeholder: 'Select symbols to analyze',
        allowClear: true,
        width: '100%',
        closeOnSelect: false,
        tags: false
    });
    
    // Generate report on button click
    $('#generateReport, #generateReport2').on('click', generateAstroReport);

    function generateAstroReport() {
        const startDate = $('#startDate').val();
        const days = $('#tradingDays').val();
        const symbols = $('#symbolSelect').val() || [];

        if (!startDate) {
            alert('Please select a start date');
            return;
        }

        $('#loadingOverlay').css('display', 'flex');

        $.ajax({
            url: "{{ route('user.financial.astrology.generate') }}",
            type: "POST",
            data: {
                _token: '{{ csrf_token() }}',
                start_date: startDate,
                days: days,
                symbols: symbols
            },
            success: function(res) {
                $('#loadingOverlay').hide();
                
                if (res.status === 'success') {
                    renderReport(res.data);
                } else {
                    alert('Error generating report: ' + (res.message || 'Unknown error'));
                }
            },
            error: function(xhr) {
                $('#loadingOverlay').hide();
                alert('Error generating report. Please try again.');
                console.error(xhr);
            }
        });
    }

    function renderReport(data) {
        let html = '';

        // Weekly Theme
        html += `
            <div class="astro-card">
                <h3 class="mb-3">📅 Weekly Overview (${data.week_range.start} to ${data.week_range.end})</h3>
                <p style="font-size: 16px; line-height: 1.6;">${data.theme}</p>
            </div>
        `;

        // Act I
        html += renderAct(data.acts.act_i, 'Act I: The "Risk-On" Engine Ignites', '🚀');

        // Act II
        html += renderAct(data.acts.act_ii, 'Act II: The Mid-Week Pivot', '⚡');

        // Act III
        html += renderAct(data.acts.act_iii, 'Act III: The Follow-Through', '📈');

        // Daily Predictions
        html += '<div class="act-section">';
        html += '<h3 class="act-title">📊 Daily Breakdown & Predictions</h3>';
        
        data.daily_predictions.forEach((day, index) => {
            html += renderDailyPrediction(day, index);
        });
        
        html += '</div>';

        // Sector Recommendations
        html += renderSectorRecommendations(data.sector_recommendations);

        // Options Strategy
        html += renderOptionsStrategy(data.options_strategy);

        // Disclaimer
        html += `
            <div class="alert alert-warning" style="border-radius: 12px;">
                <strong>⚠️ Disclaimer:</strong> Financial astrology is speculative and should not be the sole basis for trading decisions. 
                Always combine with fundamental analysis, technical analysis, and robust risk management. Past planetary alignments 
                do not guarantee future market movements.
            </div>
        `;

        $('#reportContainer').html(html);
    }

    function renderAct(act, title, emoji) {
        if (!act || !act.days || act.days.length === 0) return '';

        let html = `
            <div class="act-section">
                <h3 class="act-title">${emoji} ${title}</h3>
                <p class="mb-4">${act.summary}</p>
                <div class="row">
        `;

        act.days.forEach(day => {
            const biasClass = getBiasClass(day.bias);
            const volIndicators = getVolatilityIndicators(day.volatility);

            html += `
                <div class="col-lg-6">
                    <div class="daily-card">
                        <div class="day-header">
                            <div>
                                <h5 class="mb-1 text-dark">${day.day}</h5>
                                <small class="">${day.date}</small>
                            </div>
                            <span class="bias-badge ${biasClass}">${day.bias}</span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="text-dark fw-bold mb-2">Conviction Score:</label>
                            <div class="conviction-meter">
                                <div class="conviction-fill" style="width: ${day.conviction}%">
                                    ${day.conviction}%
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-dark fw-bold mb-2">Volatility: ${volIndicators}</label>
                        </div>

                        <div>
                            <label class="text-dark fw-bold mb-2">Favored Sectors:</label><br>
                            ${day.sectors.map(s => `<span class="sector-pill">${s}</span>`).join('')}
                        </div>
                    </div>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        return html;
    }

    function renderDailyPrediction(day, index) {
        const biasClass = getBiasClass(day.bias);
        const volIndicators = getVolatilityIndicators(day.volatility);

        let html = `
            <div class="daily-card">
                <div class="day-header">
                    <div>
                        <h4 class="mb-1 text-dark">${day.day} - ${day.date}</h4>
                        <div class="mt-2">
                            <span class="planet-badge">☉ Sun in ${day.sun_sign}</span>
                            <span class="planet-badge">☿ Mercury in ${day.mercury_sign}</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="bias-badge ${biasClass}">${day.bias}</span>
                        <div class="mt-2">
                            <small class="text-dark fw-bold">Volatility: ${volIndicators}</small>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="text-dark fw-bold mb-2">Conviction:</label>
                        <div class="conviction-meter">
                            <div class="conviction-fill" style="width: ${day.conviction}%">
                                ${day.conviction}%
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-dark fw-bold mb-2">Market Sentiment:</label>
                        <div class="mt-2">
                            <span class="badge ${getTrendClass(day.market_sentiment)} fs-6">
                                ${day.market_sentiment}
                            </span>
                        </div>
                    </div>
                </div>

                ${renderAspects(day.aspects)}

                <div class="mb-3">
                    <label class="text-dark fw-bold mb-2">Key Insights:</label>
                    <ul class="mt-2">
                        ${day.bullets.map(b => `<li>${b}</li>`).join('')}
                    </ul>
                </div>

                <div class="mb-3">
                    <label class="text-dark fw-bold mb-2">Trading Tactics:</label>
                    <ul class="mt-2">
                        ${day.tactics.map(t => `<li>${t}</li>`).join('')}
                    </ul>
                </div>

                <div class="mb-3">
                    <label class="text-dark fw-bold mb-2">Favored Sectors:</label><br>
                    ${day.sectors.map(s => `<span class="sector-pill">${s}</span>`).join('')}
                </div>

                <div>
                    <label class="text-dark fw-bold mb-2">Top Stock Ideas:</label><br>
                    ${day.top_stocks.map(s => `<span class="stock-chip">${s}</span>`).join('')}
                </div>
            </div>
        `;

        return html;
    }

    function renderAspects(aspects) {
        if (!aspects || aspects.length === 0) {
            return '<p class="">No major planetary aspects today</p>';
        }

        let html = '<div class="mb-3"><label class="text-dark fw-bold mb-2">Planetary Aspects:</label>';
        
        aspects.forEach(aspect => {
            html += `
                <div class="aspect-card aspect-${aspect.type}">
                    <strong>${aspect.planets}</strong> (${aspect.angle}°)
                    <p class="mb-0 mt-1 ">${aspect.interpretation}</p>
                </div>
            `;
        });

        html += '</div>';
        return html;
    }

    function renderSectorRecommendations(sectors) {
        if (!sectors || sectors.length === 0) return '';

        let html = `
            <div class="act-section">
                <h3 class="act-title">🎯 Weekly Sector Recommendations</h3>
                <p class="mb-4">Sectors aligned with planetary positions this week:</p>
                <div class="row">
        `;

        sectors.forEach(sector => {
            html += `
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="text-dark">${sector.sector}</h5>
                            <div class="mb-2">
                                <strong>Frequency:</strong> ${sector.frequency} days
                            </div>
                            <div class="conviction-meter">
                                <div class="conviction-fill" style="width: ${sector.strength}%">
                                    ${sector.strength}% strength
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        html += '</div></div>';
        return html;
    }

    function renderOptionsStrategy(strategy) {
        if (!strategy) return '';

        let html = `
            <div class="strategy-box">
                <h5><i class="las la-chart-line"></i> Weekly Options Strategy (PE/CE Plan)</h5>
                
                <div class="mb-3">
                    <strong>Primary Strategy:</strong>
                    <p class="mb-0 mt-1">${strategy.primary_strategy}</p>
                </div>

                <div class="mb-3">
                    <strong>Entry Windows:</strong>
                    <ul class="mt-2 mb-0">
                        ${strategy.entry_windows.map(w => `<li>${w}</li>`).join('')}
                    </ul>
                </div>

                <div class="mb-3">
                    <strong>Index Focus:</strong>
                    <p class="mb-0 mt-1">${strategy.index_focus}</p>
                </div>

                <div>
                    <strong>Risk Management:</strong>
                    <ul class="mt-2 mb-0">
                        ${strategy.risk_management.map(r => `<li>${r}</li>`).join('')}
                    </ul>
                </div>
            </div>
        `;

        return html;
    }

    function getBiasClass(bias) {
        const lower = bias.toLowerCase();
        if (lower.includes('bullish') || lower.includes('risk-on')) return 'bias-bullish';
        if (lower.includes('bearish') || lower.includes('distribution')) return 'bias-bearish';
        return 'bias-neutral';
    }

    function getTrendClass(text) {
        const lower = text.toLowerCase();
        if (lower.includes('bullish')) return 'bg-success';
        if (lower.includes('bearish')) return 'bg-danger';
        return 'bg-secondary';
    }

    function getVolatilityIndicators(vol) {
        const dots = Math.ceil(vol / 25);
        let html = '';
        
        for (let i = 0; i < 4; i++) {
            if (i < dots) {
                if (vol < 30) html += '<span class="volatility-indicator vol-low"></span>';
                else if (vol < 60) html += '<span class="volatility-indicator vol-medium"></span>';
                else html += '<span class="volatility-indicator vol-high"></span>';
            } else {
                html += '<span class="volatility-indicator" style="background: #dee2e6;"></span>';
            }
        }
        
        return html + ` ${Math.round(vol)}%`;
    }
});
</script>
@endpush
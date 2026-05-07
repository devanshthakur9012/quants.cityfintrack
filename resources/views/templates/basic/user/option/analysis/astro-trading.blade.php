@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
:root {
    --astro-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --astro-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --astro-danger: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    --astro-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --astro-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

* {
    box-sizing: border-box;
}

body {
    background: #f5f7fa;
}

.astro-hero {
    background: var(--astro-primary);
    color: white;
    border-radius: 20px;
    padding: 50px 40px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(102, 126, 234, 0.4);
}

.astro-hero::before {
    content: '✨';
    position: absolute;
    font-size: 200px;
    opacity: 0.1;
    right: -50px;
    top: -50px;
    transform: rotate(-15deg);
}

.astro-hero h1 {
    font-size: 36px;
    font-weight: 800;
    margin-bottom: 10px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}

.astro-hero p {
    font-size: 16px;
    opacity: 0.95;
    margin: 0;
}

.config-box {
    background: white;
    border-radius: 20px;
    padding: 35px;
    margin-bottom: 30px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.08);
}

.week-selector {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.week-selector:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
}

.summary-card {
    background: var(--astro-primary);
    color: white;
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 30px;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-top: 25px;
}

.summary-stat {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    padding: 25px;
    border-radius: 15px;
    text-align: center;
    transition: transform 0.3s;
}

.summary-stat:hover {
    transform: translateY(-5px);
    background: rgba(255,255,255,0.2);
}

.summary-stat h3 {
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 8px;
}

.summary-stat p {
    font-size: 13px;
    margin: 0;
    opacity: 0.9;
}

.day-card {
    background: white;
    border-radius: 20px;
    padding: 35px;
    margin-bottom: 25px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.08);
    border-left: 6px solid #667eea;
    transition: all 0.3s;
}

.day-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 35px rgba(0,0,0,0.12);
}

.day-card.bullish { border-left-color: #38ef7d; }
.day-card.bearish { border-left-color: #f45c43; }
.day-card.neutral { border-left-color: #ffc107; }
.day-card.weekend { border-left-color: #cbd5e0; opacity: 0.8; }

.day-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.day-title h3 {
    font-size: 24px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 5px;
}

.day-date {
    font-size: 14px;
    color: #718096;
    font-weight: 500;
}

.energy-badge {
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 700;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.energy-badge.bullish {
    background: var(--astro-success);
    color: white;
}

.energy-badge.bearish {
    background: var(--astro-danger);
    color: white;
}

.energy-badge.neutral {
    background: #ffc107;
    color: #856404;
}

.metric-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.metric-box {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    border-radius: 15px;
    padding: 20px;
    position: relative;
    overflow: hidden;
}

.metric-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--astro-primary);
}

.metric-label {
    font-size: 12px;
    font-weight: 600;
    color: #718096;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.metric-value {
    font-size: 26px;
    font-weight: 800;
    color: #2d3748;
    margin-bottom: 5px;
}

.metric-sub {
    font-size: 12px;
    color: #a0aec0;
}

.score-bar {
    height: 12px;
    background: #e2e8f0;
    border-radius: 6px;
    overflow: hidden;
    margin-top: 10px;
}

.score-fill {
    height: 100%;
    background: var(--astro-success);
    transition: width 0.5s ease;
    border-radius: 6px;
}

.score-fill.bearish {
    background: var(--astro-danger);
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    font-size: 22px;
    color: #667eea;
}

.market-view-box {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 20px;
}

.market-view-box h4 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 15px;
    opacity: 0.95;
}

.view-item {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    padding: 12px 15px;
    border-radius: 10px;
    margin-bottom: 10px;
    font-size: 13px;
}

.view-item strong {
    display: block;
    margin-bottom: 4px;
    font-size: 11px;
    text-transform: uppercase;
    opacity: 0.9;
}

.aspect-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
}

.aspect-chip {
    background: white;
    border: 2px solid #e2e8f0;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #2d3748;
    transition: all 0.3s;
}

.aspect-chip:hover {
    border-color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.aspect-chip.harmonious { border-color: #38ef7d; color: #155724; }
.aspect-chip.tense { border-color: #f45c43; color: #721c24; }
.aspect-chip.powerful { border-color: #667eea; color: #5a67d8; }

.stock-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.stock-table thead {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.stock-table th {
    padding: 12px 15px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: #2d3748;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stock-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
    color: #4a5568;
}

.stock-table tbody tr:hover {
    background: #f7fafc;
}

.stock-symbol {
    font-weight: 700;
    color: #2d3748;
    font-size: 14px;
}

.stock-score {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 12px;
}

.stock-score.bullish {
    background: #c6f6d5;
    color: #22543d;
}

.stock-score.bearish {
    background: #fed7d7;
    color: #742a2a;
}

.timing-box {
    background: #fffbeb;
    border-left: 4px solid #fbbf24;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.timing-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #fde68a;
}

.timing-item:last-child {
    border-bottom: none;
}

.timing-time {
    font-weight: 700;
    color: #92400e;
}

.timing-desc {
    color: #78350f;
    font-size: 13px;
}

.sector-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.sector-badge {
    background: var(--astro-primary);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.sector-badge.weak {
    background: #cbd5e0;
    color: #2d3748;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.85);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-content {
    background: white;
    padding: 60px;
    border-radius: 25px;
    text-align: center;
    max-width: 450px;
}

.spinner {
    width: 70px;
    height: 70px;
    border: 6px solid #f3f3f3;
    border-top: 6px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 25px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-content h3 {
    font-size: 22px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 10px;
}

.loading-content p {
    color: #718096;
    font-size: 14px;
}

.alert-custom {
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.alert-custom.info {
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    color: #075985;
}

.alert-custom.warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
}

.text-danger{
    color:#ff0000 !important;
}

.text-success{
    color:#11922f !important;
}

.planet-position {
    font-size: 11px;
    color: #718096;
    font-style: italic;
}

.conviction-high { color: #38ef7d; font-weight: 700; }
.conviction-medium { color: #fbbf24; font-weight: 700; }
.conviction-low { color: #f45c43; font-weight: 700; }

@media (max-width: 768px) {
    .astro-hero {
        padding: 30px 20px;
    }
    
    .astro-hero h1 {
        font-size: 26px;
    }
    
    .day-card {
        padding: 20px;
    }
    
    .metric-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-grid {
        grid-template-columns: 1fr 1fr;
    }
}

.no-data {
    text-align: center;
    padding: 80px 20px;
    color: #a0aec0;
}

.no-data i {
    font-size: 80px;
    margin-bottom: 20px;
    opacity: 0.3;
}

p, h3, h4, h5, h6, span, small, strong, label {
    color: #2d3748;
}

.text-white, .text-white * {
    color: white !important;
}
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid">
        <!-- Hero Section -->
        <div class="astro-hero">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1>🌟 Astro Trading Intelligence</h1>
                    <p>Planetary-Based Market Predictions | Next Week Forecast</p>
                </div>
                <button id="btnGenerate" class="week-selector">
                    <i class="las la-rocket"></i> Generate Weekly Forecast
                </button>
            </div>
        </div>

        <!-- Configuration -->
        <div class="config-box">
            <div class="row align-items-end g-3">
                <div class="col-md-8">
                    <label class="fw-bold mb-2">Select Week Start Date (Preferably Monday)</label>
                    <input type="date" id="weekStartDate" class="form-control form-control-lg" 
                           value="{{ $nextMonday->format('Y-m-d') }}" 
                           style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px 20px;">
                </div>
                <div class="col-md-4">
                    <button id="btnGenerate2" class="week-selector w-100" style="height: 55px;">
                        <i class="las la-calendar-week"></i> Analyze Week
                    </button>
                </div>
            </div>
            
            <div class="alert-custom info mt-4">
                <strong><i class="las la-info-circle"></i> How It Works:</strong>
                This system uses real planetary positions and aspects to predict market movements. 
                It analyzes each planet's influence on different sectors and stocks based on their business nature. 
                <strong>Perfect for planning your weekly trades!</strong>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-content">
                <div class="spinner"></div>
                <h3>Calculating Planetary Positions...</h3>
                <p>Analyzing market energies for the week</p>
            </div>
        </div>

        <!-- Results Container -->
        <div id="resultsContainer"></div>

    </div>
</section>
@endsection

@push('script')
<script>
$(document).ready(function() {
    
    $('#btnGenerate, #btnGenerate2').on('click', generateForecast);

    function generateForecast() {
        const startDate = $('#weekStartDate').val();

        if (!startDate) {
            alert('Please select a start date');
            return;
        }

        $('#loadingOverlay').css('display', 'flex');

        $.ajax({
            url: "{{ route('user.astro.trading.generate') }}",
            type: "POST",
            data: {
                _token: '{{ csrf_token() }}',
                start_date: startDate
            },
            success: function(res) {
                $('#loadingOverlay').hide();
                
                if (res.status === 'success') {
                    renderForecast(res.data);
                } else {
                    showError(res.message);
                }
            },
            error: function(xhr) {
                $('#loadingOverlay').hide();
                showError('Error generating forecast. Please try again.');
                console.error(xhr);
            }
        });
    }

    function renderForecast(data) {
        let html = '';

        // Weekly Summary
        html += renderWeeklySummary(data.weekly_summary, data.week_start, data.week_end);

        // Daily Forecasts
        html += '<h2 class="mt-5 mb-4 fw-bold" style="color: #2d3748;"><i class="las la-calendar-alt"></i> Daily Forecasts</h2>';
        
        data.daily_forecasts.forEach((day, index) => {
            html += renderDayForecast(day, index + 1);
        });

        // Disclaimer
        html += `
            <div class="alert-custom warning">
                <h5 class="fw-bold mb-2"><i class="las la-exclamation-triangle"></i> Important Disclaimer</h5>
                <p class="mb-0">This forecast is based on financial astrology principles and planetary positions. 
                It is for educational and research purposes only. <strong>Not financial advice.</strong> 
                Always conduct your own analysis, use proper risk management, and consult a licensed financial advisor before trading. 
                Past planetary correlations do not guarantee future results.</p>
            </div>
        `;

        $('#resultsContainer').html(html);
        
        // Smooth scroll to results
        $('html, body').animate({
            scrollTop: $('#resultsContainer').offset().top - 20
        }, 800);
    }

    function renderWeeklySummary(summary, weekStart, weekEnd) {
        const bullishStocks = Object.entries(summary.consistent_bullish_stocks).slice(0, 6);
        const bearishStocks = Object.entries(summary.consistent_bearish_stocks).slice(0, 6);
        
        return `
            <div class="summary-card text-white">
                <h2 class="mb-2 fw-bold">📊 Weekly Summary</h2>
                <p class="mb-0" style="opacity: 0.95;">${weekStart} to ${weekEnd}</p>
                
                <div class="summary-grid">
                    <div class="summary-stat">
                        <h3>${summary.week_bias.split(' - ')[0]}</h3>
                        <p>Week Bias</p>
                    </div>
                    <div class="summary-stat">
                        <h3>${summary.bullish_days}</h3>
                        <p>Bullish Days</p>
                    </div>
                    <div class="summary-stat">
                        <h3>${summary.bearish_days}</h3>
                        <p>Bearish Days</p>
                    </div>
                    <div class="summary-stat">
                        <h3>${summary.high_volatility_days}</h3>
                        <p>High Volatility Days</p>
                    </div>
                </div>

                <div class="mt-4 p-4" style="background: rgba(255,255,255,0.15); border-radius: 15px; backdrop-filter: blur(10px);">
                    <h5 class="fw-bold mb-3 text-white">📈 Weekly Strategy</h5>
                    <p class="mb-3 text-white">${summary.week_bias}</p>
                    <p class="mb-3 text-white"><strong>Trading Approach:</strong> ${summary.trading_strategy}</p>
                    
                    ${summary.best_trading_day ? `
                        <p class="mb-2 text-white">
                            <strong>Best Day:</strong> ${summary.best_trading_day.day} (${summary.best_trading_day.date})
                        </p>
                    ` : ''}
                    
                    ${summary.riskiest_day ? `
                        <p class="mb-0 text-white">
                            <strong>Riskiest Day:</strong> ${summary.riskiest_day.day} (${summary.riskiest_day.date})
                        </p>
                    ` : ''}
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="p-3" style="background: rgba(56,239,125,0.2); border-radius: 12px;">
                            <h6 class="fw-bold mb-3 text-white">🚀 Consistent Bullish Stocks</h6>
                            ${bullishStocks.map(([symbol, count]) => `
                                <span class="badge bg-success me-2 mb-2">${symbol} (${count}d)</span>
                            `).join('')}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3" style="background: rgba(244,92,67,0.2); border-radius: 12px;">
                            <h6 class="fw-bold mb-3">📉 Consistent Bearish Stocks</h6>
                            ${bearishStocks.map(([symbol, count]) => `
                                <span class="badge bg-danger me-2 mb-2">${symbol} (${count}d)</span>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderDayForecast(day, index) {
        const energyClass = day.energies.dominant_energy.toLowerCase();
        const isTradingDay = day.is_trading_day;
        const weekendClass = !isTradingDay ? 'weekend' : '';
        
        return `
            <div class="day-card ${energyClass} ${weekendClass}">
                <div class="day-header">
                    <div class="day-title">
                        <h3>${index}. ${day.day} ${!isTradingDay ? '(Weekend)' : ''}</h3>
                        <div class="day-date">${day.date}</div>
                    </div>
                    <div>
                        <span class="energy-badge ${energyClass}">
                            ${energyClass === 'bullish' ? '📈' : energyClass === 'bearish' ? '📉' : '➡️'}
                            ${day.energies.dominant_energy}
                        </span>
                    </div>
                </div>

                ${!isTradingDay ? '<div class="alert-custom info"><strong>Market Closed</strong> - Use this day for analysis and planning</div>' : ''}

                <!-- Energy Metrics -->
                <div class="metric-grid">
                    <div class="metric-box">
                        <div class="metric-label">Bullish Score</div>
                        <div class="metric-value" style="color: #38ef7d;">${day.energies.bullish_score}</div>
                        <div class="score-bar">
                            <div class="score-fill" style="width: ${Math.min(day.energies.bullish_score * 2, 100)}%;"></div>
                        </div>
                    </div>
                    
                    <div class="metric-box">
                        <div class="metric-label">Bearish Score</div>
                        <div class="metric-value" style="color: #f45c43;">${day.energies.bearish_score}</div>
                        <div class="score-bar">
                            <div class="score-fill bearish" style="width: ${Math.min(day.energies.bearish_score * 2, 100)}%;"></div>
                        </div>
                    </div>
                    
                    <div class="metric-box">
                        <div class="metric-label">Volatility Score</div>
                        <div class="metric-value" style="color: #fbbf24;">${day.energies.volatility_score}</div>
                        <div class="metric-sub">${getVolatilityLabel(day.energies.volatility_score)}</div>
                    </div>
                    
                    <div class="metric-box">
                        <div class="metric-label">Conviction</div>
                        <div class="metric-value conviction-${day.market_view.conviction.toLowerCase()}">${day.market_view.conviction}</div>
                        <div class="metric-sub">${day.energies.bullish_percentage}% Bullish Bias</div>
                    </div>
                </div>

                <!-- Market View -->
                <div class="market-view-box">
                    <h4 class="text-white">🎯 Market Outlook</h4>
                    <div class="view-item">
                        <strong class="text-white">Opening</strong>
                        <span class="text-white">${day.market_view.opening} (${day.market_view.opening_range})</span>
                    </div>
                    <div class="view-item">
                        <strong class="text-white">Intraday Trend</strong>
                        <span class="text-white">${day.market_view.intraday_trend}</span>
                    </div>
                    <div class="view-item">
                        <strong class="text-white">Recovery Potential</strong>
                        <span class="text-white">${day.market_view.recovery_potential}</span>
                    </div>
                    <div class="view-item">
                        <strong class="text-white">Selloff Risk</strong>
                        <span class="text-white">${day.market_view.selloff_risk}</span>
                    </div>
                    <div class="view-item">
                        <strong class="text-white">Overall Bias</strong>
                        <span class="text-white">${day.market_view.overall_bias}</span>
                    </div>
                    <div class="view-item">
                        <strong class="text-white">Recommendation</strong>
                        <span class="text-white">${day.market_view.recommendation}</span>
                    </div>
                </div>

                <!-- Planetary Aspects -->
                ${day.aspects.length > 0 ? `
                    <div class="section-title"><i class="las la-star"></i> Planetary Aspects</div>
                    <div class="aspect-list">
                        ${day.aspects.map(aspect => `
                            <div class="aspect-chip ${aspect.nature}" title="${aspect.market_impact}">
                                ${aspect.planets} ${aspect.aspect}
                            </div>
                        `).join('')}
                    </div>
                ` : ''}

                <!-- Timings -->
                <div class="section-title"><i class="las la-clock"></i> Key Timings</div>
                <div class="timing-box">
                    <div class="timing-item">
                        <div><span class="timing-time">Best Entry:</span></div>
                        <div class="timing-desc">${day.timings.best_entry_time}</div>
                    </div>
                    <div class="timing-item">
                        <div><span class="timing-time">Best Exit:</span></div>
                        <div class="timing-desc">${day.timings.best_exit_time}</div>
                    </div>
                    <div class="timing-item">
                        <div><span class="timing-time">High Vol Windows:</span></div>
                        <div class="timing-desc">${day.timings.high_volatility_windows.join(', ')}</div>
                    </div>
                    <div class="timing-item">
                        <div><span class="timing-time">Reversal Probability:</span></div>
                        <div class="timing-desc">${day.timings.reversal_probability}</div>
                    </div>
                </div>

                <!-- Sectors -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="section-title"><i class="las la-chart-pie"></i> Top Sectors</div>
                        <div class="sector-badges">
                            ${Object.entries(day.sector_outlook.top_sectors).map(([sector, score]) => `
                                <span class="sector-badge">📈 ${sector} (${score})</span>
                            `).join('')}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="section-title"><i class="las la-chart-line"></i> Weak Sectors</div>
                        <div class="sector-badges">
                            ${Object.entries(day.sector_outlook.weak_sectors).map(([sector, score]) => `
                                <span class="sector-badge weak">📉 ${sector} (${score})</span>
                            `).join('')}
                        </div>
                    </div>
                </div>

                <!-- Stocks -->
                <div class="row mt-4">
                    <div class="col-lg-6">
                        <div class="section-title"><i class="las la-arrow-up"></i> Bullish Stocks (Top 10)</div>
                        ${day.bullish_stocks.length > 0 ? `
                            <table class="stock-table">
                                <thead>
                                    <tr>
                                        <th>Symbol</th>
                                        <th>Score</th>
                                        <th>Target</th>
                                        <th>Stop Loss</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${day.bullish_stocks.slice(0, 10).map(stock => `
                                        <tr>
                                            <td>
                                                <span class="stock-symbol">${stock.symbol}</span>
                                                <div style="font-size: 11px; color: #a0aec0;">${stock.reason}</div>
                                            </td>
                                            <td><span class="stock-score bullish">${stock.score}</span></td>
                                            <td style="color: #38ef7d; font-weight: 600;">${stock.target}</td>
                                            <td style="color: #f45c43; font-weight: 600;">${stock.stop_loss}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        ` : '<p style="color: #a0aec0;">No strong bullish stocks identified</p>'}
                    </div>

                    <div class="col-lg-6">
                        <div class="section-title"><i class="las la-arrow-down"></i> Bearish Stocks (Top 10)</div>
                        ${day.bearish_stocks.length > 0 ? `
                            <table class="stock-table">
                                <thead>
                                    <tr>
                                        <th>Symbol</th>
                                        <th>Score</th>
                                        <th>Target</th>
                                        <th>Stop Loss</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${day.bearish_stocks.slice(0, 10).map(stock => `
                                        <tr>
                                            <td>
                                                <span class="stock-symbol">${stock.symbol}</span>
                                                <div style="font-size: 11px; color: #a0aec0;">${stock.reason}</div>
                                            </td>
                                            <td><span class="stock-score bearish">${stock.score}</span></td>
                                            <td style="color: #f45c43; font-weight: 600;">${stock.target}</td>
                                            <td style="color: #38ef7d; font-weight: 600;">${stock.stop_loss}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        ` : '<p style="color: #a0aec0;">No strong bearish stocks identified</p>'}
                    </div>
                </div>

                <!-- Planetary Positions -->
                <div class="mt-4 p-3" style="background: #f7fafc; border-radius: 12px;">
                    <div class="section-title" style="margin-bottom: 10px;"><i class="las la-globe"></i> Planetary Positions</div>
                    <div class="row">
                        <div class="col-md-3 col-6 mb-2">
                            <div class="planet-position">☉ Sun: ${day.planets.sun_sign} ${day.planets.sun_degree}°</div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="planet-position">☽ Moon: ${day.planets.moon_sign} ${day.planets.moon_degree}°</div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="planet-position">☿ Mercury: ${day.planets.mercury_sign} ${day.planets.mercury_degree}°</div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="planet-position">♀ Venus: ${day.planets.venus_sign} ${day.planets.venus_degree}°</div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="planet-position">♂ Mars: ${day.planets.mars_sign} ${day.planets.mars_degree}°</div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="planet-position">♃ Jupiter: ${day.planets.jupiter_sign} ${day.planets.jupiter_degree}°</div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="planet-position">♄ Saturn: ${day.planets.saturn_sign} ${day.planets.saturn_degree}°</div>
                        </div>
                        <div class="col-md-3 col-6 mb-2">
                            <div class="planet-position">☊ Rahu: ${day.planets.rahu_sign} ${day.planets.rahu_degree}°</div>
                        </div>
                    </div>
                    ${day.energies.strong_planets.length > 0 ? `
                        <div class="mt-2">
                            <small style="color: #38ef7d; font-weight: 600;">
                                <i class="las la-star"></i> Strong Planets: ${day.energies.strong_planets.map(p => p.toUpperCase()).join(', ')}
                            </small>
                        </div>
                    ` : ''}
                    ${day.energies.weak_planets.length > 0 ? `
                        <div class="mt-1">
                            <small style="color: #f45c43; font-weight: 600;">
                                <i class="las la-exclamation-circle"></i> Weak Planets: ${day.energies.weak_planets.map(p => p.toUpperCase()).join(', ')}
                            </small>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    function getVolatilityLabel(score) {
        if (score > 45) return 'Extreme Volatility';
        if (score > 30) return 'High Volatility';
        if (score > 20) return 'Moderate Volatility';
        return 'Low Volatility';
    }

    function showError(message) {
        $('#resultsContainer').html(`
            <div class="no-data">
                <i class="las la-exclamation-circle"></i>
                <h3>Unable to Generate Forecast</h3>
                <p>${message}</p>
                <button class="week-selector mt-3" onclick="location.reload()">
                    <i class="las la-redo"></i> Try Again
                </button>
            </div>
        `);
    }
});
</script>
@endpush
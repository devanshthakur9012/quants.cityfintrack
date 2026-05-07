@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
:root {
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --gradient-danger: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --shadow-lg: 0 10px 40px rgba(0,0,0,0.15);
}

body { background: #f8f9fa; }

.hero-section {
    background: var(--gradient-primary);
    color: white;
    border-radius: 24px;
    padding: 50px 40px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}

.hero-section::before {
    content: '🌟';
    position: absolute;
    font-size: 220px;
    opacity: 0.08;
    right: -60px;
    top: -60px;
    transform: rotate(-20deg);
}

.hero-section h1 {
    font-size: 38px;
    font-weight: 900;
    margin: 0 0 12px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}

.control-panel {
    background: white;
    border-radius: 20px;
    padding: 35px;
    margin-bottom: 30px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.08);
}

.btn-generate {
    background: var(--gradient-primary);
    color: white;
    border: none;
    padding: 14px 35px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

.btn-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
    color: white;
}

.week-summary-card {
    background: var(--gradient-primary);
    color: white;
    border-radius: 24px;
    padding: 40px;
    margin-bottom: 30px;
    box-shadow: var(--shadow-lg);
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 25px;
}

.metric-card {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s;
}

.metric-card:hover {
    transform: translateY(-5px);
    background: rgba(255,255,255,0.25);
}

.metric-card h3 {
    font-size: 36px;
    font-weight: 900;
    margin: 0 0 8px;
    color: white;
}

.metric-card p {
    font-size: 13px;
    margin: 0;
    opacity: 0.95;
    color: white;
}

.day-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.08);
    border-left: 6px solid #667eea;
    transition: all 0.3s;
}

.day-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 35px rgba(0,0,0,0.12);
}

.day-card.trading-day { border-left-color: #22c55e; }
.day-card.non-trading-day { border-left-color: #6b7280; opacity: 0.7; }

.day-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.day-title h3 {
    font-size: 26px;
    font-weight: 800;
    color: #1f2937;
    margin: 0 0 5px;
}

.energy-badge {
    padding: 10px 24px;
    border-radius: 30px;
    font-weight: 800;
    font-size: 15px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.energy-badge.bullish {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
}

.energy-badge.bearish {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.energy-badge.neutral {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #78350f;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.info-box {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 15px;
    padding: 20px;
    position: relative;
    overflow: hidden;
}

.info-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    background: var(--gradient-primary);
}

.info-label {
    font-size: 12px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 8px;
}

.info-value {
    font-size: 20px;
    font-weight: 800;
    color: #1f2937;
}

.market-view-box {
    background: var(--gradient-info);
    color: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 20px;
}

.market-view-box h5 {
    font-size: 18px;
    font-weight: 800;
    margin: 0 0 15px;
    color: white;
}

.market-item {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    padding: 12px 18px;
    border-radius: 10px;
    margin-bottom: 10px;
    font-size: 14px;
}

.market-item strong {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    opacity: 0.9;
    margin-bottom: 4px;
}

.aspects-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 15px 0;
}

.aspect-tag {
    background: white;
    border: 2px solid #e5e7eb;
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 12px;
    font-weight: 700;
    color: #374151;
    transition: all 0.3s;
}

.aspect-tag:hover {
    border-color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.aspect-tag.harmonious { border-color: #22c55e; color: #15803d; }
.aspect-tag.tense { border-color: #ef4444; color: #991b1b; }
.aspect-tag.powerful { border-color: #667eea; color: #4f46e5; }
.aspect-tag.challenging { border-color: #f97316; color: #9a3412; }
.aspect-tag.unpredictable { border-color: #8b5cf6; color: #6d28d9; }

.stocks-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 20px 0;
}

.stock-list {
    background: #f8fafc;
    border-radius: 12px;
    padding: 20px;
}

.stock-list h6 {
    font-weight: 800;
    margin-bottom: 15px;
    color: #1f2937;
}

.stock-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e5e7eb;
}

.stock-item:last-child {
    border-bottom: none;
}

.stock-symbol {
    font-weight: 700;
    color: #1f2937;
}

.stock-score {
    font-weight: 800;
    font-size: 14px;
}

.stock-score.positive { color: #22c55e; }
.stock-score.negative { color: #ef4444; }

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
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
    width: 80px;
    height: 80px;
    border: 8px solid #f3f3f3;
    border-top: 8px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 25px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.section-title {
    font-size: 20px;
    font-weight: 800;
    color: #1f2937;
    margin: 30px 0 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-title i {
    font-size: 26px;
    color: #667eea;
}

.alert-box {
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 25px;
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.alert-box.info {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    color: #1e40af;
}

.alert-box.warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
}

.sector-outlook {
    background: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.08);
}

.sector-item {
    border-left: 5px solid #667eea;
    background: #f8f9fa;
    padding: 20px;
    margin-bottom: 15px;
    border-radius: 12px;
    transition: all 0.3s;
}

.sector-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.sector-item.strong { border-left-color: #22c55e; }
.sector-item.weak { border-left-color: #ef4444; }

@media (max-width: 768px) {
    .hero-section {
        padding: 30px 20px;
    }
    
    .hero-section h1 {
        font-size: 28px;
    }
    
    .day-card {
        padding: 20px;
    }
    
    .metrics-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .stocks-grid {
        grid-template-columns: 1fr;
    }
}

p, h3, h4, h5, h6, span, label {
    color: #1f2937;
}

.text-white, .text-white * {
    color: white !important;
}
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid">
        <!-- Hero Section -->
        <div class="hero-section">
            <h1>🔮 Weekly Astro Trading Forecast</h1>
            <p class="text-white">7-Day Planetary Analysis | Stock Predictions | Sector Outlook</p>
        </div>

        <!-- Control Panel -->
        <div class="control-panel">
            <div class="row align-items-end g-3">
                <div class="col-md-8">
                    <label class="fw-bold mb-2">Select Week Starting Date (Monday)</label>
                    <input type="date" id="weekStartDate" class="form-control form-control-lg" 
                           value="{{ date('Y-m-d', strtotime('next monday')) }}" 
                           style="border-radius: 12px; border: 2px solid #e2e8f0;">
                </div>
                <div class="col-md-4">
                    <button id="btnGenerateWeekly" class="btn-generate w-100" style="height: 55px;">
                        <i class="las la-calendar-week"></i> Generate Weekly Forecast
                    </button>
                </div>
            </div>
            
            <div class="alert-box info mt-4">
                <strong><i class="las la-info-circle"></i> Analysis Includes:</strong>
                Daily planetary aspects, market energy scores, bullish/bearish stocks, sector outlook, and timing windows.
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loading-content">
                <div class="spinner"></div>
                <h3 style="color: #1f2937;">Analyzing Weekly Planetary Positions...</h3>
                <p style="color: #6b7280;">Generating 7-day trading forecast</p>
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
    $('#btnGenerateWeekly').on('click', generateWeeklyForecast);

    function generateWeeklyForecast() {
        const startDate = $('#weekStartDate').val();

        if (!startDate) {
            alert('Please select a start date');
            return;
        }

        $('#loadingOverlay').css('display', 'flex');

        $.ajax({
            url: "{{ route('user.new.astro.analysis.generate') }}",
            type: "POST",
            data: {
                _token: '{{ csrf_token() }}',
                start_date: startDate
            },
            success: function(res) {
                $('#loadingOverlay').hide();
                
                if (res.status === 'success') {
                    renderWeeklyForecast(res.data);
                } else {
                    showError(res.message);
                }
            },
            error: function(xhr) {
                $('#loadingOverlay').hide();
                showError('Error generating weekly forecast. Please try again.');
                console.error(xhr);
            }
        });
    }

    function renderWeeklyForecast(data) {
        let html = '';

        // Weekly Summary
        html += renderWeeklySummary(data.weekly_summary, data.week_start, data.week_end);

        // Daily Forecasts
        html += '<div class="section-title"><i class="las la-calendar-day"></i> Daily Forecasts</div>';
        
        data.daily_forecasts.forEach(day => {
            html += renderDayCard(day);
        });

        // Disclaimer
        html += `
            <div class="alert-box warning">
                <h5 class="fw-bold mb-2"><i class="las la-exclamation-triangle"></i> Disclaimer</h5>
                <p class="mb-0">This astrological analysis is for educational purposes only. 
                <strong>Not financial advice.</strong> Always use proper risk management and consult a financial advisor before trading.</p>
            </div>
        `;

        $('#resultsContainer').html(html);
        
        $('html, body').animate({
            scrollTop: $('#resultsContainer').offset().top - 20
        }, 800);
    }

    function renderWeeklySummary(summary, weekStart, weekEnd) {
        return `
            <div class="week-summary-card text-white">
                <h2 class="fw-bold mb-3">📅 Weekly Summary: ${weekStart} to ${weekEnd}</h2>
                
                <div class="metrics-grid">
                    <div class="metric-card">
                        <h3>${summary.bullish_days}</h3>
                        <p>Bullish Days</p>
                    </div>
                    <div class="metric-card">
                        <h3>${summary.bearish_days}</h3>
                        <p>Bearish Days</p>
                    </div>
                    <div class="metric-card">
                        <h3>${summary.high_volatility_days}</h3>
                        <p>High Vol Days</p>
                    </div>
                </div>

                <div class="mt-4 p-4" style="background: rgba(255,255,255,0.15); border-radius: 16px; backdrop-filter: blur(10px);">
                    <h5 class="fw-bold mb-3 text-white">${summary.week_bias}</h5>
                    <p class="mb-2 text-white"><strong>Trading Strategy:</strong> ${summary.trading_strategy}</p>
                    
                    ${summary.best_trading_day ? `
                        <p class="mb-2 text-white">
                            <strong>Best Trading Day:</strong> ${summary.best_trading_day.day} (${summary.best_trading_day.date}) - ${summary.best_trading_day.reason}
                        </p>
                    ` : ''}
                    
                    ${summary.riskiest_day ? `
                        <p class="mb-0 text-white">
                            <strong>Riskiest Day:</strong> ${summary.riskiest_day.day} (${summary.riskiest_day.date}) - ${summary.riskiest_day.reason}
                        </p>
                    ` : ''}
                </div>

                <!-- Consistent Stocks -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="p-3" style="background: rgba(34, 197, 94, 0.2); border-radius: 12px;">
                            <h6 class="fw-bold mb-2 text-white">📈 Consistent Bullish Stocks</h6>
                            ${Object.entries(summary.consistent_bullish_stocks || {}).map(([symbol, count]) => `
                                <div class="d-flex justify-content-between text-white mb-1">
                                    <span>${symbol}</span>
                                    <span class="fw-bold">${count} days</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3" style="background: rgba(239, 68, 68, 0.2); border-radius: 12px;">
                            <h6 class="fw-bold mb-2 text-white">📉 Consistent Bearish Stocks</h6>
                            ${Object.entries(summary.consistent_bearish_stocks || {}).map(([symbol, count]) => `
                                <div class="d-flex justify-content-between text-white mb-1">
                                    <span>${symbol}</span>
                                    <span class="fw-bold">${count} days</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderDayCard(day) {
        const energyClass = day.energies.dominant_energy.toLowerCase();
        const cardClass = day.is_trading_day ? 'trading-day' : 'non-trading-day';
        
        return `
            <div class="day-card ${cardClass}">
                <div class="day-header">
                    <div class="day-title">
                        <h3>${day.date} - ${day.day}</h3>
                        <div class="d-flex gap-3 flex-wrap">
                            <span style="color: #6b7280; font-weight: 600;">
                                ${day.is_trading_day ? '📊 Trading Day' : '🏖️ Non-Trading Day'}
                            </span>
                            <span style="color: #6b7280; font-weight: 600;">
                                Moon: ${day.planets.moon_sign || 'N/A'}
                            </span>
                        </div>
                    </div>
                    <div>
                        <span class="energy-badge ${energyClass}">
                            ${getEnergyIcon(day.energies.dominant_energy)} ${day.energies.dominant_energy}
                        </span>
                    </div>
                </div>

                ${day.is_trading_day ? renderTradingDayContent(day) : renderNonTradingDayContent(day)}
            </div>
        `;
    }

    function renderTradingDayContent(day) {
        return `
            <!-- Energy Metrics -->
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-label">Bullish Score</div>
                    <div class="info-value" style="color: #22c55e;">${Math.round(day.energies.bullish_score)}</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Bearish Score</div>
                    <div class="info-value" style="color: #ef4444;">${Math.round(day.energies.bearish_score)}</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Volatility Score</div>
                    <div class="info-value" style="color: #f59e0b;">${Math.round(day.energies.volatility_score)}</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Bullish %</div>
                    <div class="info-value">${day.energies.bullish_percentage}%</div>
                </div>
            </div>

            <!-- Market View -->
            <div class="market-view-box">
                <h5>🎯 Market View</h5>
                <div class="market-item">
                    <strong class="text-white">Opening</strong>
                    <span class="text-white">${day.market_view.opening} - ${day.market_view.opening_range}</span>
                </div>
                <div class="market-item">
                    <strong class="text-white">Intraday Trend</strong>
                    <span class="text-white">${day.market_view.intraday_trend}</span>
                </div>
                <div class="market-item">
                    <strong class="text-white">Trading Style</strong>
                    <span class="text-white">${day.market_view.trading_style}</span>
                </div>
                <div class="market-item">
                    <strong class="text-white">Overall Bias</strong>
                    <span class="text-white">${day.market_view.overall_bias} (${day.market_view.conviction} conviction)</span>
                </div>
            </div>

            <!-- Planetary Aspects -->
            <div class="mb-4">
                <h6 class="fw-bold mb-3">⭐ Planetary Aspects</h6>
                <div class="aspects-container">
                    ${day.aspects.map(aspect => `
                        <div class="aspect-tag ${aspect.nature}" title="${aspect.market_impact}">
                            ${aspect.planets} - ${aspect.aspect} (${aspect.strength})
                        </div>
                    `).join('')}
                </div>
            </div>

            <!-- Stock Predictions -->
            <div class="stocks-grid">
                <div class="stock-list">
                    <h6 style="color: #22c55e;">📈 Bullish Stocks</h6>
                    ${day.bullish_stocks.slice(0, 8).map(stock => `
                        <div class="stock-item">
                            <span class="stock-symbol">${stock.symbol}</span>
                            <span class="stock-score positive">+${stock.score}</span>
                        </div>
                    `).join('')}
                </div>
                <div class="stock-list">
                    <h6 style="color: #ef4444;">📉 Bearish Stocks</h6>
                    ${day.bearish_stocks.slice(0, 8).map(stock => `
                        <div class="stock-item">
                            <span class="stock-symbol">${stock.symbol}</span>
                            <span class="stock-score negative">${stock.score}</span>
                        </div>
                    `).join('')}
                </div>
            </div>

            <!-- Sector Outlook -->
            <div class="sector-outlook">
                <h6 class="fw-bold mb-3">🏢 Sector Outlook</h6>
                <div class="row">
                    <div class="col-md-6">
                        <h6 style="color: #22c55e;">Strong Sectors</h6>
                        ${Object.entries(day.sector_outlook.top_sectors || {}).map(([sector, score]) => `
                            <div class="sector-item strong">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">${sector}</span>
                                    <span style="color: #22c55e; font-weight: 800;">${score}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    <div class="col-md-6">
                        <h6 style="color: #ef4444;">Weak Sectors</h6>
                        ${Object.entries(day.sector_outlook.weak_sectors || {}).map(([sector, score]) => `
                            <div class="sector-item weak">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">${sector}</span>
                                    <span style="color: #ef4444; font-weight: 800;">${score}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>

            <!-- Timings -->
            <div class="p-3" style="background: #f0fdf4; border-radius: 12px;">
                <h6 class="fw-bold mb-3" style="color: #166534;">🕒 Trading Timings</h6>
                <div class="row">
                    <div class="col-md-6">
                        <strong style="color: #166534;">High Volatility Windows:</strong>
                        <div style="color: #166534;">${day.timings.high_volatility_windows.join(', ')}</div>
                    </div>
                    <div class="col-md-6">
                        <strong style="color: #166534;">Reversal Probability:</strong>
                        <div style="color: #166534;">${day.timings.reversal_probability}</div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderNonTradingDayContent(day) {
        return `
            <div class="text-center py-4" style="color: #6b7280;">
                <i class="las la-umbrella-beach" style="font-size: 48px; margin-bottom: 15px;"></i>
                <h4>Market Closed</h4>
                <p>No trading analysis available for ${day.day}. Use this time for research and planning.</p>
                
                ${day.aspects && day.aspects.length > 0 ? `
                    <div class="mt-4">
                        <h6>Planetary Aspects (for reference):</h6>
                        <div class="aspects-container justify-content-center">
                            ${day.aspects.slice(0, 3).map(aspect => `
                                <div class="aspect-tag ${aspect.nature}">
                                    ${aspect.planets} - ${aspect.aspect}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
    }

    function getEnergyIcon(energy) {
        if (energy === 'Bullish') return '🚀';
        if (energy === 'Bearish') return '⚠️';
        return '➡️';
    }

    function showError(message) {
        $('#resultsContainer').html(`
            <div class="alert-box warning">
                <h5 class="fw-bold mb-2"><i class="las la-exclamation-circle"></i> Error</h5>
                <p class="mb-0">${message}</p>
            </div>
        `);
    }
});
</script>
@endpush
@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --danger-gradient: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.hero-banner {
    background: var(--primary-gradient);
    color: white;
    border-radius: 16px;
    padding: 40px;
    margin-bottom: 30px;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
}

.hero-banner h1 {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 10px;
}

.config-panel {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}

.prediction-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
    border-left: 5px solid #667eea;
}

.prediction-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.signal-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
    display: inline-block;
}

.signal-strong-buy { background: linear-gradient(135deg, #11998e, #38ef7d); color: white; }
.signal-buy { background: #d4edda; color: #155724; }
.signal-neutral { background: #d1ecf1; color: #0c5460; }
.signal-sell { background: #f8d7da; color: #721c24; }
.signal-strong-sell { background: linear-gradient(135deg, #eb3349, #f45c43); color: white; }

.metric-box {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    border-left: 4px solid #667eea;
}

.metric-label {
    font-size: 12px;
    color: #6c757d;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.metric-value {
    font-size: 24px;
    font-weight: 700;
    color: #2d3748;
    margin-top: 5px;
}

.aspect-chip {
    display: inline-block;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    margin: 4px;
    font-size: 11px;
    font-weight: 600;
}

.aspect-card {
    background: #fff;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 10px;
}

.aspect-conjunction { border-left-color: #6f42c1; }
.aspect-opposition { border-left-color: #fd7e14; }
.aspect-trine { border-left-color: #28a745; }
.aspect-square { border-left-color: #dc3545; }
.aspect-sextile { border-left-color: #17a2b8; }

.pcr-meter {
    height: 40px;
    background: #e9ecef;
    border-radius: 20px;
    position: relative;
    overflow: hidden;
}

.pcr-indicator {
    position: absolute;
    top: 0;
    height: 100%;
    width: 4px;
    background: #dc3545;
    transition: left 0.3s ease;
}

.pcr-zones {
    display: flex;
    height: 100%;
}

.pcr-zone {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 600;
}

.zone-extreme-bullish { background: #28a745; color: white; }
.zone-bullish { background: #38ef7d; color: #155724; }
.zone-neutral { background: #ffc107; color: #856404; }
.zone-bearish { background: #f45c43; color: white; }
.zone-extreme-bearish { background: #dc3545; color: white; }

.vol-indicator {
    display: flex;
    align-items: center;
    gap: 5px;
}

.vol-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #dee2e6;
}

.vol-dot.active-low { background: #28a745; }
.vol-dot.active-medium { background: #ffc107; }
.vol-dot.active-high { background: #dc3545; }

.summary-card {
    background: var(--primary-gradient);
    color: white;
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 30px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.summary-item {
    background: rgba(255,255,255,0.15);
    padding: 20px;
    border-radius: 12px;
    backdrop-filter: blur(10px);
}

.summary-item h4 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 5px;
}

.summary-item p {
    font-size: 13px;
    opacity: 0.9;
    margin: 0;
}

.sector-badge {
    display: inline-block;
    background: #667eea;
    color: white;
    padding: 6px 12px;
    border-radius: 16px;
    margin: 3px;
    font-size: 12px;
    font-weight: 600;
}

.action-box {
    background: #f8f9fa;
    border-left: 4px solid #667eea;
    border-radius: 8px;
    padding: 15px;
    margin: 10px 0;
}

.loading-screen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-content {
    background: white;
    padding: 50px;
    border-radius: 20px;
    text-align: center;
    max-width: 400px;
}

.spinner {
    width: 60px;
    height: 60px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.conviction-bar {
    height: 10px;
    background: #e9ecef;
    border-radius: 5px;
    overflow: hidden;
    margin-top: 8px;
}

.conviction-fill {
    height: 100%;
    background: linear-gradient(90deg, #dc3545, #ffc107, #28a745);
    transition: width 0.5s ease;
}

.select2-container--bootstrap-5 .select2-selection {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    min-height: 45px;
}

.select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
    background: #667eea;
    border: none;
    color: white;
    padding: 5px 10px;
    border-radius: 12px;
}
.selection{
    width: 100%;
}
p, h5, h6, small, strong{
    color:#000;
}
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid">
        <!-- Hero Banner -->
        <div class="hero-banner">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1>🌌 Market Astrology Intelligence</h1>
                    <p class="mb-0" style="font-size: 16px; opacity: 0.9;">
                        Correlating Planetary Cycles with Options Market Data for Accurate Predictions
                    </p>
                </div>
                <button id="btnGenerate" class="btn btn-light btn-lg">
                    <i class="las la-rocket"></i> Generate Analysis
                </button>
            </div>
        </div>

        <!-- Configuration Panel -->
        <div class="config-panel">
            <h5 class="mb-4 fw-bold"><i class="las la-cog"></i> Analysis Configuration</h5>
            <div class="row g-3">
                <div class="col-lg-3 col-md-6">
                    <label class="text-dark fw-bold">Start Date (Monday Preferred)</label>
                    <input type="date" id="startDate" class="form-control" value="{{ \Carbon\Carbon::now()->startOfWeek()->format('Y-m-d') }}">
                </div>
                
                <div class="col-lg-2 col-md-6">
                    <label class="text-dark fw-bold">Trading Days</label>
                    <select id="tradingDays" class="form-select">
                        <option value="5" selected>5 Days (Week)</option>
                        <option value="7">7 Days (Full)</option>
                        <option value="10">10 Days (Fortnight)</option>
                    </select>
                </div>

                <div class="col-lg-5 col-md-12">
                    <label class="text-dark fw-bold">Focus Symbols (Optional - Leave empty for all)</label>
                    <select id="symbolSelect" class="form-select" multiple>
                        @foreach ($symbols as $symbol)
                            <option value="{{ $symbol }}">{{ $symbol }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-2 col-md-12 d-flex align-items-end">
                    <button id="btnGenerate2" class="btn btn-primary w-100" style="background: var(--primary-gradient); border: none; height: 45px;">
                        <i class="las la-chart-line"></i> Analyze
                    </button>
                </div>
            </div>

            <div class="alert alert-info mt-3 mb-0" style="border-left: 4px solid #17a2b8;">
                <strong>💡 How it works:</strong> This system combines real options market data (PCR, OI changes, volume) 
                with planetary aspects to identify high-probability trading opportunities. PCR interpretation follows 
                industry standards: <strong>&lt;0.7 = Bullish, 0.9-1.1 = Neutral, &gt;1.4 = Bearish</strong>.
            </div>
        </div>

        <!-- Loading Screen -->
        <div class="loading-screen" id="loadingScreen">
            <div class="loading-content">
                <div class="spinner"></div>
                <h4>Analyzing Market Data...</h4>
                <p class="text-muted">Correlating planetary positions with options flow</p>
            </div>
        </div>

        <!-- Results Container -->
        <div id="resultsContainer"></div>

    </div>
</section>
@endsection

@push('script')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    
    // Initialize Select2
    $('#symbolSelect').select2({
        theme: 'bootstrap-5',
        placeholder: 'Select symbols to focus on (optional)',
        allowClear: true,
        width: '100%',
        closeOnSelect: false
    });
    
    // Generate Analysis
    $('#btnGenerate, #btnGenerate2').on('click', generateAnalysis);

    function generateAnalysis() {
        const startDate = $('#startDate').val();
        const days = $('#tradingDays').val();
        const symbols = $('#symbolSelect').val() || [];

        if (!startDate) {
            alert('Please select a start date');
            return;
        }

        $('#loadingScreen').css('display', 'flex');

        $.ajax({
            url: "{{ route('user.market.astrology.generate') }}",
            type: "POST",
            data: {
                _token: '{{ csrf_token() }}',
                start_date: startDate,
                days: days,
                symbols: symbols
            },
            success: function(res) {
                $('#loadingScreen').hide();
                
                if (res.status === 'success') {
                    renderResults(res.data);
                } else {
                    showError(res.message);
                }
            },
            error: function(xhr) {
                $('#loadingScreen').hide();
                showError('Error generating analysis. Please try again.');
                console.error(xhr);
            }
        });
    }

    function renderResults(data) {
        let html = '';

        // Executive Summary
        html += renderSummary(data.summary, data.period);

        // Key Signals
        html += renderSignals(data.signals);

        // Daily Predictions
        html += '<h3 class="mt-5 mb-4 fw-bold"><i class="las la-calendar-day"></i> Daily Predictions & Trading Plan</h3>';
        
        data.predictions.forEach((day, index) => {
            html += renderDayPrediction(day, index + 1);
        });

        // Disclaimer
        html += `
            <div class="alert alert-warning" style="border-radius: 16px; border-left: 4px solid #ffc107;">
                <h6 class="fw-bold"><i class="las la-exclamation-triangle"></i> Important Disclaimer</h6>
                <p class="mb-0">This analysis combines financial astrology with quantitative market data for educational and research purposes only. 
                <strong>Not financial advice.</strong> Always use proper risk management, stop-losses, and position sizing. 
                Past correlations do not guarantee future results. Consult a licensed financial advisor before trading.</p>
            </div>
        `;

        $('#resultsContainer').html(html);
    }

    function renderSummary(summary, period) {
        return `
            <div class="summary-card">
                <h3 class="mb-1">📊 Executive Summary</h3>
                <p class="mb-0" style="opacity: 0.9; font-size: 14px;">${period.start} to ${period.end}</p>
                
                <div class="summary-grid">
                    <div class="summary-item">
                        <h4>${summary.week_bias}</h4>
                        <p>Week Bias</p>
                    </div>
                    <div class="summary-item">
                        <h4>${summary.avg_pcr}</h4>
                        <p>Average PCR</p>
                    </div>
                    <div class="summary-item">
                        <h4>${summary.avg_volatility}%</h4>
                        <p>Avg Volatility</p>
                    </div>
                    <div class="summary-item">
                        <h4>${summary.bullish_days}/${summary.bearish_days}</h4>
                        <p>Bullish/Bearish Days</p>
                    </div>
                </div>

                <div class="mt-4 p-3" style="background: rgba(255,255,255,0.15); border-radius: 12px;">
                    <h6 class="fw-bold mb-2">Key Insight:</h6>
                    <p class="mb-1">${summary.key_message}</p>
                    ${summary.key_aspect !== 'No major aspects' ? 
                        `<p class="mb-1"><strong>Major Planetary Event:</strong> ${summary.key_aspect}</p>` : ''}
                    <p class="mb-0"><strong>Risk Advisory:</strong> ${summary.risk_advice}</p>
                </div>
            </div>
        `;
    }

    function renderSignals(signals) {
        const highestBuy = signals.highest_conviction_buy;
        const highestSell = signals.highest_conviction_sell;
        const mostVolatile = signals.most_volatile_day;

        return `
            <div class="row mb-4">
                <div class="col-lg-4">
                    <div class="prediction-card" style="border-left-color: #28a745;">
                        <h6 class="fw-bold mb-3 text-success"><i class="las la-arrow-up"></i> Best Buy Setup</h6>
                        ${highestBuy ? `
                            <p class="mb-2"><strong>Date:</strong> ${highestBuy.date} (${highestBuy.day})</p>
                            <p class="mb-2"><strong>Signal:</strong> <span class="signal-badge signal-${highestBuy.combined_signal.toLowerCase().replace(' ', '-')}">${highestBuy.combined_signal}</span></p>
                            <p class="mb-2"><strong>PCR:</strong> ${highestBuy.pcr_oi} | <strong>Conv:</strong> ${highestBuy.conviction}%</p>
                            <p class="mb-0 text-muted" style="font-size: 13px;">${highestBuy.recommended_action}</p>
                        ` : '<p class="text-muted">No strong buy signals this period</p>'}
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="prediction-card" style="border-left-color: #dc3545;">
                        <h6 class="fw-bold mb-3 text-danger"><i class="las la-arrow-down"></i> Best Sell Setup</h6>
                        ${highestSell ? `
                            <p class="mb-2"><strong>Date:</strong> ${highestSell.date} (${highestSell.day})</p>
                            <p class="mb-2"><strong>Signal:</strong> <span class="signal-badge signal-${highestSell.combined_signal.toLowerCase().replace(' ', '-')}">${highestSell.combined_signal}</span></p>
                            <p class="mb-2"><strong>PCR:</strong> ${highestSell.pcr_oi} | <strong>Conv:</strong> ${highestSell.conviction}%</p>
                            <p class="mb-0 text-muted" style="font-size: 13px;">${highestSell.recommended_action}</p>
                        ` : '<p class="text-muted">No strong sell signals this period</p>'}
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="prediction-card" style="border-left-color: #ffc107;">
                        <h6 class="fw-bold mb-3 text-warning"><i class="las la-exclamation-triangle"></i> Highest Risk Day</h6>
                        ${mostVolatile ? `
                            <p class="mb-2"><strong>Date:</strong> ${mostVolatile.date} (${mostVolatile.day})</p>
                            <p class="mb-2"><strong>Volatility:</strong> ${mostVolatile.volatility}%</p>
                            <p class="mb-2"><strong>Risk Level:</strong> ${mostVolatile.risk_level}</p>
                            <p class="mb-0 text-muted" style="font-size: 13px;">Reduce position sizes and use wider stops on this day</p>
                        ` : '<p class="text-muted">Volatility data unavailable</p>'}
                    </div>
                </div>
            </div>
        `;
    }

    function renderDayPrediction(day, index) {
        const signalClass = day.combined_signal.toLowerCase().replace(' ', '-');
        
        return `
            <div class="prediction-card">
                <div class="row">
                    <!-- Header -->
                    <div class="col-12 mb-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div>
                                <h5 class="fw-bold mb-1">${index}. ${day.day} - ${day.date}</h5>
                                <div class="mb-2">
                                    <span class="badge bg-secondary me-2">☉ ${day.sun_sign}</span>
                                    <span class="badge bg-secondary">☿ ${day.mercury_sign}</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="signal-badge signal-${signalClass}">${day.combined_signal}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Market Metrics -->
                    <div class="col-lg-8">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="metric-box">
                                    <div class="metric-label">Put-Call Ratio (OI)</div>
                                    <div class="metric-value">${day.pcr_oi}</div>
                                    <div class="pcr-meter mt-2">
                                        <div class="pcr-zones">
                                            <div class="pcr-zone zone-extreme-bullish">&lt;0.6</div>
                                            <div class="pcr-zone zone-bullish">0.6-0.9</div>
                                            <div class="pcr-zone zone-neutral">0.9-1.1</div>
                                            <div class="pcr-zone zone-bearish">1.1-1.5</div>
                                            <div class="pcr-zone zone-extreme-bearish">&gt;1.5</div>
                                        </div>
                                        <div class="pcr-indicator" style="left: ${Math.min((day.pcr_oi / 2) * 100, 95)}%;"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="metric-box">
                                    <div class="metric-label">Market Sentiment</div>
                                    <div class="metric-value" style="font-size: 16px;">${day.market_sentiment}</div>
                                    <div class="mt-2">
                                        <small class="text-muted">Conviction: ${day.conviction}%</small>
                                        <div class="conviction-bar">
                                            <div class="conviction-fill" style="width: ${day.conviction}%;"></div>
                                        </div>
                                    </small>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="metric-box">
                                    <div class="metric-label">Volatility Index</div>
                                    <div class="metric-value">${day.volatility}%</div>
                                    <div class="vol-indicator mt-2">
                                        ${renderVolDots(day.volatility)}
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="metric-box">
                                    <div class="metric-label">Astro Score</div>
                                    <div class="metric-value" style="color: ${day.astro_score > 0 ? '#28a745' : '#dc3545'};">
                                        ${day.astro_score > 0 ? '+' : ''}${day.astro_score}
                                    </div>
                                    <small class="text-muted">Planetary influence rating</small>
                                </div>
                            </div>
                        </div>

                        <!-- Trading Plan -->
                        <div class="action-box mt-3">
                            <h6 class="fw-bold mb-2"><i class="las la-bullseye"></i> Recommended Action</h6>
                            <p class="mb-2">${day.recommended_action}</p>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <small class="text-muted d-block"><strong>Entry Time:</strong></small>
                                    <small>${day.entry_time}</small>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block"><strong>Risk Level:</strong></small>
                                    <small>${day.risk_level}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Planetary & Tactical -->
                    <div class="col-lg-4">
                        <!-- Aspects -->
                        ${day.aspects && day.aspects.length > 0 ? `
                            <h6 class="fw-bold mb-2"><i class="las la-star"></i> Planetary Aspects</h6>
                            ${day.aspects.map(aspect => `
                                <div class="aspect-card aspect-${aspect.type} mb-2">
                                    <div class="fw-bold text-dark" style="font-size: 12px;">${aspect.aspect}</div>
                                    <div style="font-size: 11px; color: #6c757d; margin-top: 3px;">${aspect.market_impact}</div>
                                </div>
                            `).join('')}
                        ` : '<p class="text-muted">No major aspects</p>'}

                        <!-- Sectors -->
                        <h6 class="fw-bold mt-3 mb-2"><i class="las la-chart-pie"></i> Favored Sectors</h6>
                        <div class="mb-3">
                            ${day.sectors.map(s => `<span class="sector-badge">${s}</span>`).join('')}
                        </div>

                        <!-- Strategy -->
                        <div class="action-box">
                            <small class="text-muted d-block mb-1"><strong>Index Focus:</strong></small>
                            <small class="text-dark d-block mb-2">${day.index_preference}</small>
                            
                            <small class="text-muted d-block mb-1"><strong>Option Strategy:</strong></small>
                            <small>${day.option_strategy}</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function renderVolDots(vol) {
        const dots = 4;
        const threshold = vol / 25;
        let html = '';
        
        for (let i = 0; i < dots; i++) {
            let className = 'vol-dot';
            if (i < threshold) {
                if (vol < 30) className += ' active-low';
                else if (vol < 60) className += ' active-medium';
                else className += ' active-high';
            }
            html += `<div class="${className}"></div>`;
        }
        
        return html;
    }

    function showError(message) {
        $('#resultsContainer').html(`
            <div class="empty-state">
                <i class="las la-exclamation-circle"></i>
                <h4>Unable to Generate Analysis</h4>
                <p>${message}</p>
                <button class="btn btn-primary mt-3" onclick="location.reload()">Try Again</button>
            </div>
        `);
    }
});
</script>
@endpush
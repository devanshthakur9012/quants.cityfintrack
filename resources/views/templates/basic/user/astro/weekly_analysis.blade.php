@extends($activeTemplate . 'layouts.master')
@section('content')
    <section class="pt-100 pb-100">
        <div class="container content-container">

            <!-- Hero Header -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="astro-hero-card">
                        <div class="hero-content">
                            <div class="hero-icon">
                                <i class="las la-chart-line"></i>
                            </div>
                            <div>
                                <h2 class="hero-title">{{ $pageTitle }}</h2>
                                <p class="hero-subtitle">Real-time planetary analysis for intraday F&O trading • No past data, pure astronomical signals</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Row (if data exists) -->
            @if (isset($weeklyData) && count($weeklyData) > 0)
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="quick-stats-card">
                            <div class="stats-header">
                                <h4>
                                    <i class="las la-calendar-week"></i>
                                    {{ $selectedSymbol }} — Week at a Glance
                                </h4>
                                <div class="date-range">
                                    {{ \Carbon\Carbon::parse($weeklyData[0]['date'])->format('d M') }} - 
                                    {{ \Carbon\Carbon::parse($weeklyData[count($weeklyData)-1]['date'])->format('d M Y') }}
                                </div>
                            </div>

                            <div class="stats-grid">
                                @php
                                    $bullishCount = collect($weeklyData)->filter(fn($d) => str_contains($d['bias'], 'Bullish'))->count();
                                    $bearishCount = collect($weeklyData)->filter(fn($d) => str_contains($d['bias'], 'Bearish'))->count();
                                    $neutralCount = 5 - $bullishCount - $bearishCount;
                                    $avgSentiment = round(collect($weeklyData)->avg('sentiment_score'));
                                    $avgVolatility = round(collect($weeklyData)->avg('volatility_score'));
                                    $highVolDays = collect($weeklyData)->filter(fn($d) => $d['volatility_score'] >= 60)->count();
                                    
                                    // Weekly recommendation
                                    if ($bullishCount >= 3) {
                                        $weeklyBias = 'Bullish Week';
                                        $weeklyColor = 'success';
                                        $weeklyRec = 'Favor long positions, use dips to add';
                                    } elseif ($bearishCount >= 3) {
                                        $weeklyBias = 'Bearish Week';
                                        $weeklyColor = 'danger';
                                        $weeklyRec = 'Cautious approach, prefer shorts';
                                    } else {
                                        $weeklyBias = 'Mixed Week';
                                        $weeklyColor = 'warning';
                                        $weeklyRec = 'Day-by-day approach, stay flexible';
                                    }
                                @endphp

                                <div class="stat-box primary">
                                    <div class="stat-icon">
                                        <i class="las la-bullseye"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-label">Weekly Bias</div>
                                        <div class="stat-value badge-{{ $weeklyColor }}">{{ $weeklyBias }}</div>
                                        <div class="stat-detail">{{ $weeklyRec }}</div>
                                    </div>
                                </div>

                                <div class="stat-box">
                                    <div class="stat-icon success">
                                        <i class="las la-arrow-up"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-label">Bullish Days</div>
                                        <div class="stat-value">{{ $bullishCount }}/5</div>
                                        <div class="stat-detail">{{ round($bullishCount/5*100) }}% of week</div>
                                    </div>
                                </div>

                                <div class="stat-box">
                                    <div class="stat-icon danger">
                                        <i class="las la-arrow-down"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-label">Bearish Days</div>
                                        <div class="stat-value">{{ $bearishCount }}/5</div>
                                        <div class="stat-detail">{{ round($bearishCount/5*100) }}% of week</div>
                                    </div>
                                </div>

                                <div class="stat-box">
                                    <div class="stat-icon info">
                                        <i class="las la-chart-bar"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-label">Avg Sentiment</div>
                                        <div class="stat-value">{{ $avgSentiment }}</div>
                                        <div class="stat-detail">
                                            @if($avgSentiment >= 60) Strong positive @elseif($avgSentiment >= 45) Neutral @else Weak @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="stat-box">
                                    <div class="stat-icon warning">
                                        <i class="las la-bolt"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-label">Volatility</div>
                                        <div class="stat-value">{{ $avgVolatility }}</div>
                                        <div class="stat-detail">{{ $highVolDays }} high-vol days</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Symbol Selection Form -->
            <div class="row mb-4">
                <div class="col-lg-12">
                    <div class="selection-card">
                        <form action="{{ route('user.weekly.astro.generate') }}" method="POST" id="astroForm">
                            @csrf
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="las la-chart-line"></i> Trading Symbol
                                    </label>
                                    <select name="symbol" class="form--control" required>
                                        <option value="">Select Symbol...</option>
                                        @foreach ($symbols as $sym)
                                            <option value="{{ $sym }}" 
                                                {{ isset($selectedSymbol) && $selectedSymbol == $sym ? 'selected' : '' }}>
                                                {{ $sym }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="las la-calendar"></i> Week Starting (Monday)
                                    </label>
                                    <input type="date" name="start_date" class="form--control"
                                        value="{{ $startDateFormatted ?? date('Y-m-d', strtotime('monday this week')) }}" required>
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn btn--base btn-generate">
                                        <i class="las la-magic"></i> Generate Analysis
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if (isset($weeklyData) && count($weeklyData) > 0)
                <!-- Daily Analysis Cards -->
                <div class="row g-4">
                    @foreach ($weeklyData as $index => $day)
                        <div class="col-lg-12">
                            <div class="day-card {{ strtolower($day['bias_color']) }}-theme">
                                
                                <!-- Card Header -->
                                <div class="day-header">
                                    <div class="day-info">
                                        <div class="day-number">Day {{ $index + 1 }}</div>
                                        <div>
                                            <h3 class="day-title">{{ $day['weekday'] }}</h3>
                                            <p class="day-date">{{ $day['day_num'] }}</p>
                                        </div>
                                    </div>
                                    <div class="day-badge-group">
                                        <span class="badge-main badge-{{ $day['bias_color'] }}">
                                            <i class="{{ $day['bias_icon'] }}"></i>
                                            {{ $day['bias'] }}
                                        </span>
                                        <span class="badge-gap badge-{{ $day['gap_color'] }}">
                                            {{ $day['gap'] }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Score Cards -->
                                <div class="score-row">
                                    <div class="score-card sentiment">
                                        <div class="score-label">
                                            <i class="las la-smile"></i> Market Sentiment
                                        </div>
                                        <div class="score-visual">
                                            <div class="circular-progress" data-value="{{ $day['sentiment_score'] }}">
                                                <svg viewBox="0 0 100 100">
                                                    <circle cx="50" cy="50" r="45" class="progress-bg"></circle>
                                                    <circle cx="50" cy="50" r="45" class="progress-fill" 
                                                        style="stroke-dashoffset: {{ 283 - (283 * $day['sentiment_score'] / 100) }}"></circle>
                                                </svg>
                                                <div class="progress-text">{{ $day['sentiment_score'] }}</div>
                                            </div>
                                            <div class="score-interpretation">
                                                @if($day['sentiment_score'] >= 70)
                                                    <strong style="color: #28a745;">Very Bullish</strong><br>
                                                    High confidence in upward moves
                                                @elseif($day['sentiment_score'] >= 55)
                                                    <strong style="color: #17a2b8;">Moderately Bullish</strong><br>
                                                    Positive bias with caution
                                                @elseif($day['sentiment_score'] >= 45)
                                                    <strong style="color: #ffc107;">Neutral</strong><br>
                                                    Mixed signals, trade ranges
                                                @elseif($day['sentiment_score'] >= 30)
                                                    <strong style="color: #fd7e14;">Moderately Bearish</strong><br>
                                                    Negative bias expected
                                                @else
                                                    <strong style="color: #dc3545;">Very Bearish</strong><br>
                                                    Strong downward pressure
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="score-card volatility">
                                        <div class="score-label">
                                            <i class="las la-bolt"></i> Expected Volatility
                                        </div>
                                        <div class="score-visual">
                                            <div class="circular-progress" data-value="{{ $day['volatility_score'] }}">
                                                <svg viewBox="0 0 100 100">
                                                    <circle cx="50" cy="50" r="45" class="progress-bg"></circle>
                                                    <circle cx="50" cy="50" r="45" class="progress-fill volatility-fill" 
                                                        style="stroke-dashoffset: {{ 283 - (283 * $day['volatility_score'] / 100) }}"></circle>
                                                </svg>
                                                <div class="progress-text">{{ $day['volatility_score'] }}</div>
                                            </div>
                                            <div class="score-interpretation">
                                                @if($day['volatility_score'] >= 70)
                                                    <strong style="color: #dc3545;">Extreme Swings</strong><br>
                                                    Tight stops, quick decisions
                                                @elseif($day['volatility_score'] >= 50)
                                                    <strong style="color: #fd7e14;">High Volatility</strong><br>
                                                    Wider ranges expected
                                                @elseif($day['volatility_score'] >= 35)
                                                    <strong style="color: #ffc107;">Moderate</strong><br>
                                                    Normal intraday moves
                                                @else
                                                    <strong style="color: #28a745;">Low Volatility</strong><br>
                                                    Stable, range-bound
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="score-card strategy">
                                        <div class="score-label">
                                            <i class="las la-chess"></i> Recommended Strategy
                                        </div>
                                        <div class="strategy-content">
                                            <div class="strategy-text">
                                                {{ $day['strategy'] }}
                                            </div>
                                            <div class="strategy-tips">
                                                @if($day['moon_void'])
                                                    <div class="alert-box warning">
                                                        <i class="las la-exclamation-triangle"></i>
                                                        <strong>Void Moon:</strong> Avoid new positions, close existing trades
                                                    </div>
                                                @endif
                                                @if(count($day['retrogrades']) > 0)
                                                    <div class="alert-box info">
                                                        <i class="las la-undo"></i>
                                                        <strong>Retrogrades Active:</strong> Review & reassess, avoid impulsive trades
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Trading Windows -->
                                <div class="windows-section">
                                    <h5 class="section-title">
                                        <i class="las la-clock"></i> Optimal Trading Windows (IST)
                                    </h5>
                                    <div class="windows-grid">
                                        <div class="window-card entry">
                                            <div class="window-header">
                                                <i class="las la-sign-in-alt"></i>
                                                <span>Best Entry Window</span>
                                            </div>
                                            <div class="window-time">{{ $day['windows']['best_entry'] }}</div>
                                            <div class="window-note">Setup positions when bias confirms</div>
                                        </div>

                                        <div class="window-card exit">
                                            <div class="window-header">
                                                <i class="las la-hand-holding-usd"></i>
                                                <span>Profit Booking</span>
                                            </div>
                                            <div class="window-time">{{ $day['windows']['profit_booking'] }}</div>
                                            <div class="window-note">Book profits, reduce exposure</div>
                                        </div>

                                        <div class="window-card avoid">
                                            <div class="window-header">
                                                <i class="las la-ban"></i>
                                                <span>Avoid Trading</span>
                                            </div>
                                            <div class="window-time">{{ $day['windows']['avoid'] }}</div>
                                            <div class="window-note">High uncertainty, wait for clarity</div>
                                        </div>

                                        <div class="window-card scalp">
                                            <div class="window-header">
                                                <i class="las la-running"></i>
                                                <span>Scalping Zones</span>
                                            </div>
                                            <div class="window-time">
                                                @foreach($day['windows']['scalp_zones'] as $zone)
                                                    <div>{{ $zone }}</div>
                                                @endforeach
                                            </div>
                                            <div class="window-note">Quick in-and-out opportunities</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Key Levels -->
                                <div class="levels-section">
                                    <h5 class="section-title">
                                        <i class="las la-layer-group"></i> Key Levels to Watch
                                    </h5>
                                    <div class="levels-grid">
                                        @foreach($day['key_levels'] as $key => $value)
                                            <div class="level-item">
                                                <div class="level-label">{{ ucwords(str_replace('_', ' ', $key)) }}</div>
                                                <div class="level-value">{{ $value }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Planetary Analysis -->
                                <div class="analysis-section">
                                    <h5 class="section-title">
                                        <i class="las la-star"></i> Planetary Analysis & Reasoning
                                    </h5>
                                    
                                    @if(count($day['aspects']) > 0)
                                        <div class="aspects-container">
                                            <h6 class="subsection-title">Active Aspects:</h6>
                                            <div class="aspects-list">
                                                @foreach($day['aspects'] as $aspect)
                                                    <div class="aspect-card aspect-{{ strtolower(str_replace(' ', '-', $aspect['type'])) }}">
                                                        <div class="aspect-header">
                                                            <strong>{{ $aspect['label'] }}</strong>
                                                            <span class="aspect-strength">{{ $aspect['strength'] }}% strength</span>
                                                        </div>
                                                        <div class="aspect-meaning">{{ $aspect['meaning'] }}</div>
                                                        <div class="aspect-meta">
                                                            Orb: {{ $aspect['orb'] }}° • Impact Score: 
                                                            <span class="score-badge">{{ $aspect['score'] > 0 ? '+' : '' }}{{ $aspect['score'] }}</span>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if(count($day['ingresses']) > 0)
                                        <div class="ingresses-container mt-3">
                                            <h6 class="subsection-title">Sign Changes:</h6>
                                            <div class="ingresses-list">
                                                @foreach($day['ingresses'] as $ingress)
                                                    <div class="ingress-item">
                                                        <i class="las la-exchange-alt"></i>
                                                        <div>
                                                            <strong>{{ $ingress['text'] }}</strong>
                                                            <p>{{ $ingress['meaning'] }}</p>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if(count($day['retrogrades']) > 0)
                                        <div class="retrogrades-container mt-3">
                                            <h6 class="subsection-title">Retrograde Planets:</h6>
                                            <div class="retrogrades-list">
                                                @foreach($day['retrogrades'] as $retro)
                                                    <div class="retro-item">
                                                        <i class="las la-undo-alt"></i>
                                                        <div>
                                                            <strong>{{ $retro['planet'] }} Retrograde</strong>
                                                            <p>{{ $retro['impact'] }}</p>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <div class="moon-info mt-3">
                                        <div class="moon-phase">
                                            <i class="las la-moon"></i>
                                            <strong>Moon Phase:</strong> {{ $day['moon_phase'] }}
                                            @if($day['moon_void'])
                                                <span class="void-badge">VOID</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- How to Use This Guide -->
                <div class="row mt-5">
                    <div class="col-lg-12">
                        <div class="guide-card">
                            <h4 class="guide-title">
                                <i class="las la-question-circle"></i> How to Use This Astrological Analysis
                            </h4>
                            <div class="guide-grid">
                                <div class="guide-item">
                                    <div class="guide-icon">
                                        <i class="las la-book-open"></i>
                                    </div>
                                    <div class="guide-content">
                                        <h6>Step 1: Check Weekly Bias</h6>
                                        <p>Start with the weekly overview to understand the overall planetary trend. This sets your baseline positioning.</p>
                                    </div>
                                </div>

                                <div class="guide-item">
                                    <div class="guide-icon">
                                        <i class="las la-chart-line"></i>
                                    </div>
                                    <div class="guide-content">
                                        <h6>Step 2: Review Daily Sentiment</h6>
                                        <p>Each day's sentiment score (0-100) indicates directional bias. Above 60 = bullish, below 40 = bearish.</p>
                                    </div>
                                </div>

                                <div class="guide-item">
                                    <div class="guide-icon">
                                        <i class="las la-clock"></i>
                                    </div>
                                    <div class="guide-content">
                                        <h6>Step 3: Follow Time Windows</h6>
                                        <p>Trade during recommended windows. Avoid high volatility zones unless you're scalping with tight stops.</p>
                                    </div>
                                </div>

                                <div class="guide-item">
                                    <div class="guide-icon">
                                        <i class="las la-shield-alt"></i>
                                    </div>
                                    <div class="guide-content">
                                        <h6>Step 4: Use Key Levels</h6>
                                        <p>Watch suggested support/resistance. These work with technical levels you identify on charts.</p>
                                    </div>
                                </div>

                                <div class="guide-item">
                                    <div class="guide-icon">
                                        <i class="las la-exclamation-triangle"></i>
                                    </div>
                                    <div class="guide-content">
                                        <h6>Step 5: Heed Warnings</h6>
                                        <p>Void Moon days: avoid new trades. Retrogrades: be extra cautious. High volatility: reduce position size.</p>
                                    </div>
                                </div>

                                <div class="guide-item">
                                    <div class="guide-icon">
                                        <i class="las la-lightbulb"></i>
                                    </div>
                                    <div class="guide-content">
                                        <h6>Step 6: Combine with Technicals</h6>
                                        <p>This is NOT a standalone system. Use it alongside your technical analysis, price action, and risk management.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="disclaimer">
                                <i class="las la-info-circle"></i>
                                <strong>Disclaimer:</strong> Financial astrology is speculative. Past planetary patterns do not guarantee future results. 
                                Always use proper risk management, position sizing, and stop losses. This tool is for educational purposes only.
                            </div>
                        </div>
                    </div>
                </div>

            @else
                <!-- Empty State -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="las la-satellite-dish"></i>
                            </div>
                            <h3 class="empty-title">Ready to Analyze Planetary Influences</h3>
                            <p class="empty-text">Select a symbol and week to generate your trading analysis based on current planetary positions</p>
                            <div class="empty-features">
                                <div class="feature-tag">
                                    <i class="las la-check-circle"></i> Real-time astronomical calculations
                                </div>
                                <div class="feature-tag">
                                    <i class="las la-check-circle"></i> Intraday timing windows
                                </div>
                                <div class="feature-tag">
                                    <i class="las la-check-circle"></i> Volatility predictions
                                </div>
                                <div class="feature-tag">
                                    <i class="las la-check-circle"></i> Trading strategies
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </section>
@endsection

@push('style')
    <style>
        :root {
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --secondary-color: #6c757d;
        }

        /* Hero Card */
        .astro-hero-card {
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.15) 0%, rgba(111, 66, 193, 0.15) 100%);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 35px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .hero-content {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .hero-icon {
            font-size: 4rem;
            color: #4facfe;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .hero-title {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 8px;
            color: #fff;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .hero-subtitle {
            font-size: 1.05rem;
            opacity: 0.9;
            margin: 0;
            color: #e0e0e0;
        }

        /* Quick Stats Card */
        .quick-stats-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .stats-header h4 {
            color: #fff;
            margin: 0;
            font-size: 1.4rem;
        }

        .date-range {
            font-size: 1.05rem;
            color: #4facfe;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .stat-box {
            background: rgba(255, 255, 255, 0.06);
            padding: 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-box:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
        }

        .stat-box.primary {
            grid-column: span 2;
            background: linear-gradient(135deg, rgba(79, 172, 254, 0.2) 0%, rgba(0, 242, 254, 0.2) 100%);
            border: 1px solid rgba(79, 172, 254, 0.4);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        .stat-icon.success { background: rgba(40, 167, 69, 0.25); color: #28a745; }
        .stat-icon.danger { background: rgba(220, 53, 69, 0.25); color: #dc3545; }
        .stat-icon.info { background: rgba(23, 162, 184, 0.25); color: #17a2b8; }
        .stat-icon.warning { background: rgba(255, 193, 7, 0.25); color: #ffc107; }

        .stat-content {
            flex: 1;
        }

        .stat-label {
            font-size: 0.85rem;
            opacity: 0.7;
            color: #fff;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }

        .stat-detail {
            font-size: 0.8rem;
            opacity: 0.65;
            color: #fff;
            margin-top: 3px;
        }

        /* Selection Card */
        .selection-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 20px;
            align-items: end;
        }

        .form-label {
            color: #fff;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .btn-generate {
            height: 48px;
            padding: 0 30px;
            font-size: 1.05rem;
            font-weight: 700;
            white-space: nowrap;
        }

        /* Day Card */
        .day-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 30px;
            border: 2px solid rgba(255, 255, 255, 0.15);
            transition: all 0.4s ease;
        }

        .day-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
        }

        .day-card.success-theme {
            border-color: rgba(40, 167, 69, 0.4);
            background: rgba(40, 167, 69, 0.08);
        }

        .day-card.danger-theme {
            border-color: rgba(220, 53, 69, 0.4);
            background: rgba(220, 53, 69, 0.08);
        }

        .day-card.warning-theme {
            border-color: rgba(255, 193, 7, 0.4);
            background: rgba(255, 193, 7, 0.08);
        }

        .day-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.15);
        }

        .day-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .day-number {
            background: rgba(79, 172, 254, 0.3);
            color: #4facfe;
            font-weight: 800;
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 0.9rem;
        }

        .day-title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            color: #fff;
        }

        .day-date {
            font-size: 1rem;
            opacity: 0.7;
            margin: 5px 0 0;
            color: #fff;
        }

        .day-badge-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .badge-main, .badge-gap {
            padding: 12px 24px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .badge-success { background: #28a745; color: #fff; }
        .badge-danger { background: #dc3545; color: #fff; }
        .badge-warning { background: #ffc107; color: #000; }
        .badge-secondary { background: #6c757d; color: #fff; }
        .badge-info { background: #17a2b8; color: #fff; }

        /* Score Cards */
        .score-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .score-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .score-label {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .score-label i {
            font-size: 1.3rem;
            color: #4facfe;
        }

        .circular-progress {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 15px;
        }

        .circular-progress svg {
            transform: rotate(-90deg);
        }

        .circular-progress circle {
            fill: none;
            stroke-width: 8;
        }

        .circular-progress .progress-bg {
            stroke: rgba(255, 255, 255, 0.1);
        }

        .circular-progress .progress-fill {
            stroke: #28a745;
            stroke-dasharray: 283;
            stroke-dashoffset: 283;
            transition: stroke-dashoffset 1s ease;
        }

        .circular-progress .volatility-fill {
            stroke: #ffc107;
        }

        .circular-progress .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
        }

        .score-interpretation {
            text-align: center;
            font-size: 0.9rem;
            color: #ddd;
            line-height: 1.5;
        }

        .strategy-content {
            padding: 10px 0;
        }

        .strategy-text {
            font-size: 1.05rem;
            color: #fff;
            line-height: 1.6;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .alert-box {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }

        .alert-box.warning {
            background: rgba(255, 193, 7, 0.2);
            border-left: 4px solid #ffc107;
            color: #fff;
        }

        .alert-box.info {
            background: rgba(23, 162, 184, 0.2);
            border-left: 4px solid #17a2b8;
            color: #fff;
        }

        .alert-box i {
            font-size: 1.3rem;
        }

        /* Windows Section */
        .windows-section, .levels-section, .analysis-section {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #4facfe;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .windows-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 15px;
        }

        .window-card {
            background: rgba(255, 255, 255, 0.08);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid;
        }

        .window-card.entry { border-left-color: #28a745; }
        .window-card.exit { border-left-color: #17a2b8; }
        .window-card.avoid { border-left-color: #dc3545; }
        .window-card.scalp { border-left-color: #ffc107; }

        .window-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .window-header i {
            font-size: 1.3rem;
        }

        .window-time {
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 8px;
        }

        .window-note {
            font-size: 0.85rem;
            opacity: 0.7;
            color: #fff;
        }

        /* Key Levels */
        .levels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .level-item {
            background: rgba(255, 255, 255, 0.08);
            padding: 15px;
            border-radius: 10px;
        }

        .level-label {
            font-size: 0.85rem;
            opacity: 0.7;
            color: #fff;
            margin-bottom: 5px;
        }

        .level-value {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
        }

        /* Aspects */
        .subsection-title {
            font-size: 1rem;
            font-weight: 700;
            color: #ffd700;
            margin-bottom: 15px;
        }

        .aspects-list, .ingresses-list, .retrogrades-list {
            display: grid;
            gap: 12px;
        }

        .aspect-card {
            background: rgba(255, 255, 255, 0.08);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid;
        }

        .aspect-card.aspect-very-bullish, .aspect-card.aspect-bullish { border-left-color: #28a745; }
        .aspect-card.aspect-very-bearish, .aspect-card.aspect-bearish { border-left-color: #dc3545; }
        .aspect-card.aspect-highly-volatile, .aspect-card.aspect-volatile { border-left-color: #ffc107; }
        .aspect-card.aspect-mildly-bullish, .aspect-card.aspect-neutral, .aspect-card.aspect-mixed { border-left-color: #6c757d; }
        .aspect-card.aspect-volatile-bullish { border-left-color: #fd7e14; }

        .aspect-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            color: #fff;
        }

        .aspect-strength {
            font-size: 0.85rem;
            font-weight: 600;
            color: #4facfe;
        }

        .aspect-meaning {
            font-size: 0.95rem;
            color: #ddd;
            margin-bottom: 8px;
        }

        .aspect-meta {
            font-size: 0.8rem;
            opacity: 0.7;
            color: #fff;
        }

        .score-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 700;
        }

        .ingress-item, .retro-item {
            display: flex;
            align-items: start;
            gap: 12px;
            background: rgba(255, 255, 255, 0.08);
            padding: 12px;
            border-radius: 8px;
        }

        .ingress-item i, .retro-item i {
            font-size: 1.5rem;
            color: #4facfe;
            margin-top: 2px;
        }

        .ingress-item strong, .retro-item strong {
            color: #fff;
            display: block;
            margin-bottom: 4px;
        }

        .ingress-item p, .retro-item p {
            font-size: 0.9rem;
            color: #ddd;
            margin: 0;
        }

        .moon-info {
            background: rgba(255, 255, 255, 0.08);
            padding: 15px;
            border-radius: 8px;
        }

        .moon-phase {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-size: 1rem;
        }

        .moon-phase i {
            font-size: 1.5rem;
            color: #ffd700;
        }

        .void-badge {
            background: rgba(220, 53, 69, 0.3);
            color: #dc3545;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-left: 10px;
        }

        /* Guide Card */
        .guide-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .guide-title {
            color: #4facfe;
            margin-bottom: 25px;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .guide-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .guide-item {
            display: flex;
            gap: 15px;
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 12px;
        }

        .guide-icon {
            width: 50px;
            height: 50px;
            background: rgba(79, 172, 254, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: #4facfe;
            flex-shrink: 0;
        }

        .guide-content h6 {
            color: #fff;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .guide-content p {
            color: #ddd;
            font-size: 0.9rem;
            margin: 0;
            line-height: 1.6;
        }

        .disclaimer {
            background: rgba(255, 193, 7, 0.15);
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            border-radius: 8px;
            color: #fff;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .disclaimer i {
            color: #ffc107;
            margin-right: 8px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-icon {
            font-size: 5rem;
            color: #4facfe;
            opacity: 0.5;
            margin-bottom: 20px;
        }

        .empty-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #fff;
        }

        .empty-text {
            font-size: 1.1rem;
            opacity: 0.8;
            color: #ddd;
            margin-bottom: 30px;
        }

        .empty-features {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
        }

        .feature-tag {
            background: rgba(79, 172, 254, 0.15);
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 0.95rem;
            color: #4facfe;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-box.primary {
                grid-column: span 1;
            }
        }

        @media (max-width: 768px) {
            .hero-content {
                flex-direction: column;
                text-align: center;
            }

            .hero-title {
                font-size: 1.6rem;
            }

            .day-header {
                flex-direction: column;
                gap: 15px;
                align-items: start;
            }

            .day-title {
                font-size: 1.5rem;
            }

            .score-row {
                grid-template-columns: 1fr;
            }

            .guide-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@push('script')
    <script>
        $(document).ready(function() {
            // Smooth animations on load
            $('.day-card').each(function(index) {
                $(this).css('opacity', '0').delay(index * 100).animate({
                    opacity: 1
                }, 500);
            });

            // Form validation
            $('#astroForm').on('submit', function(e) {
                const symbol = $('select[name="symbol"]').val();
                const date = $('input[name="start_date"]').val();

                if (!symbol || !date) {
                    e.preventDefault();
                    alert('Please select both symbol and date');
                    return false;
                }

                // Show loading state
                $('.btn-generate').html('<i class="las la-spinner la-spin"></i> Generating Analysis...')
                    .prop('disabled', true);
            });

            // Animate circular progress bars
            $('.circular-progress').each(function() {
                const value = $(this).data('value');
                const circle = $(this).find('.progress-fill');
                const circumference = 283;
                const offset = circumference - (circumference * value / 100);
                
                setTimeout(() => {
                    circle.css('stroke-dashoffset', offset);
                }, 300);
            });

            // Tooltip for aspect cards
            $('.aspect-card').hover(
                function() {
                    $(this).css('transform', 'translateX(5px)');
                },
                function() {
                    $(this).css('transform', 'translateX(0)');
                }
            );
        });
    </script>
@endpush
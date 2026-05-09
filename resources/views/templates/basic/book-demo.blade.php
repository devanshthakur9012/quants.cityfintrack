@extends($activeTemplate.'layouts.frontend')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* =========================================
   PAGE WRAPPER
========================================= */
.qd-page {
    min-height: 100vh;
    background: linear-gradient(135deg, #0D1B2A 0%, #1a3560 50%, #0D2545 100%);
    display: flex; align-items: center; justify-content: center;
    padding: 40px 20px;
    font-family: 'Exo 2', sans-serif;
    position: relative; overflow: hidden;
}
.qd-page::before {
    content: '';
    position: absolute; inset: 0;
    background:
        radial-gradient(ellipse at 20% 80%, rgba(245,166,35,.12) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(21,101,192,.18) 0%, transparent 50%);
    pointer-events: none;
}
.qd-page * { box-sizing: border-box; }

/* =========================================
   CARD
========================================= */
.qd-card {
    background: #fff;
    border-radius: 24px;
    width: 100%; max-width: 1100px;
    display: grid; grid-template-columns: 1fr 420px;
    overflow: hidden;
    box-shadow: 0 32px 100px rgba(0,0,0,.35);
    position: relative; z-index: 1;
}
@media(max-width:860px){
    .qd-card { grid-template-columns: 1fr; max-width: 500px; }
}

/* =========================================
   LEFT — PROMO PANEL (dark)
========================================= */
.qd-left {
    background: linear-gradient(160deg, #0D1B2A 0%, #162844 100%);
    padding: 52px 48px;
    display: flex; flex-direction: column;
    position: relative; overflow: hidden;
}
.qd-left::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 30% 70%, rgba(245,166,35,.08), transparent 60%);
    pointer-events: none;
}
@media(max-width:860px){ .qd-left { padding: 36px 28px; order: 2; } }

/* logo */
.qd-logo { display: flex; align-items: center; gap: 10px; margin-bottom: 36px; position: relative; z-index: 1; }
.qd-logo-icon {
    width: 48px; height: 48px; border-radius: 10px; background: #F5A623;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; font-weight: 700; color: #fff; font-family: 'Rajdhani', sans-serif; flex-shrink: 0;
}
.qd-logo-name { font-family: 'Rajdhani', sans-serif; font-size: 19px; font-weight: 700; color: #fff; }
.qd-logo-sub  { font-size: 11px; color: rgba(255,255,255,.45); letter-spacing: .03em; }

/* headline */
.qd-promo-head {
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(26px, 3vw, 38px); font-weight: 700;
    color: #fff; margin: 0 0 10px; line-height: 1.1;
    position: relative; z-index: 1;
}
.qd-promo-head span { color: #F5A623; }
.qd-promo-sub {
    font-size: 14px; color: rgba(255,255,255,.55); line-height: 1.75; margin: 0 0 32px;
    position: relative; z-index: 1; max-width: 380px;
}

/* what you get list */
.qd-what-list { display: flex; flex-direction: column; gap: 14px; margin-bottom: 34px; position: relative; z-index: 1; }
.qd-what-item { display: flex; align-items: flex-start; gap: 14px; }
.qd-what-icon {
    width: 40px; height: 40px; border-radius: 10px;
    background: rgba(245,166,35,.15); border: 1px solid rgba(245,166,35,.3);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    font-size: 16px; color: #F5A623;
}
.qd-what-text {}
.qd-what-title { font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 2px; }
.qd-what-desc  { font-size: 12px; color: rgba(255,255,255,.5); line-height: 1.55; }

/* stats row */
.qd-stats-row {
    display: flex; gap: 24px; flex-wrap: wrap;
    border-top: 1px solid rgba(255,255,255,.08); padding-top: 24px; margin-top: auto;
    position: relative; z-index: 1;
}
.qd-stat { display: flex; flex-direction: column; }
.qd-stat-val { font-family: 'Rajdhani', sans-serif; font-size: 24px; font-weight: 700; color: #F5A623; line-height: 1; }
.qd-stat-lbl { font-size: 11px; color: rgba(255,255,255,.4); margin-top: 2px; }

/* testimonial mini */
.qd-testimonial {
    background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px; padding: 16px 20px; margin-bottom: 28px;
    position: relative; z-index: 1;
}
.qd-testimonial p { font-size: 13px; color: rgba(255,255,255,.65); line-height: 1.65; margin: 0 0 10px; font-style: italic; }
.qd-testimonial-author { display: flex; align-items: center; gap: 10px; }
.qd-testimonial-avatar {
    width: 36px; height: 36px; border-radius: 50%; background: #F5A623;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; color: #000; font-family: 'Rajdhani', sans-serif; flex-shrink: 0;
}
.qd-testimonial-name { font-size: 13px; font-weight: 700; color: #fff; }
.qd-testimonial-role { font-size: 11px; color: rgba(255,255,255,.4); }
.qd-stars { color: #F5A623; font-size: 11px; letter-spacing: 1px; }

/* =========================================
   RIGHT — FORM PANEL (white)
========================================= */
.qd-right {
    background: #fafafa;
    padding: 52px 44px 40px;
    display: flex; flex-direction: column;
    border-left: 1px solid #e8e8e8;
}
@media(max-width:860px){ .qd-right { padding: 36px 28px; order: 1; border-left: none; border-bottom: 1px solid #e8e8e8; } }

/* form heading */
.qd-form-head {
    font-family: 'Rajdhani', sans-serif; font-size: 30px; font-weight: 700;
    color: #1a1a2e; margin: 0 0 6px;
}
.qd-form-sub { font-size: 14px; color: #888; margin: 0 0 28px; line-height: 1.55; }

/* step tracker */
.qd-steps-row { display: flex; align-items: center; gap: 0; margin-bottom: 28px; }
.qd-step-dot {
    width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; font-family: 'Rajdhani', sans-serif;
    transition: all .3s;
}
.qd-step-dot.done    { background: #43a047; color: #fff; }
.qd-step-dot.active  { background: #F5A623; color: #000; }
.qd-step-dot.pending { background: #e0e0e0; color: #aaa; }
.qd-step-line { flex: 1; height: 2px; background: #e0e0e0; transition: background .3s; }
.qd-step-line.done { background: #43a047; }
.qd-step-label {
    position: absolute; top: 32px; font-size: 10px; color: #aaa; white-space: nowrap;
    transform: translateX(-50%); left: 50%; font-weight: 600; letter-spacing: .04em;
}
.qd-step-wrap { position: relative; display: flex; flex-direction: column; align-items: center; }

/* input groups */
.qd-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
.qd-field-row.single { grid-template-columns: 1fr; }
.qd-field-group { display: flex; flex-direction: column; gap: 5px; }
.qd-field-label { font-size: 11px; color: #999; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
.qd-field-label .req { color: #e53935; margin-left: 2px; }
.qd-field-input,
.qd-field-select,
.qd-field-textarea {
    border: 1.5px solid #ddd; border-radius: 9px;
    padding: 11px 14px; font-size: 14px; color: #1a1a2e; background: #fff;
    font-family: 'Exo 2', sans-serif; outline: none; transition: border-color .2s, box-shadow .2s;
    width: 100%;
}
.qd-field-input:focus,
.qd-field-select:focus,
.qd-field-textarea:focus {
    border-color: #F5A623; box-shadow: 0 0 0 3px rgba(245,166,35,.12);
}
.qd-field-input::placeholder,
.qd-field-textarea::placeholder { color: #ccc; }
.qd-field-select {
    appearance: none;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 12px center;
    cursor: pointer;
}
.qd-field-textarea { resize: vertical; min-height: 80px; line-height: 1.6; }

/* phone row */
.qd-phone-row { display: flex; gap: 8px; }
.qd-country-btn {
    display: flex; align-items: center; gap: 6px; padding: 0 14px; height: 48px;
    border-radius: 9px; border: 1.5px solid #ddd; background: #fff;
    font-size: 14px; font-weight: 600; color: #1a1a2e; cursor: pointer;
    white-space: nowrap; flex-shrink: 0; transition: border-color .2s;
}
.qd-country-btn:hover { border-color: #F5A623; }

/* slot picker */
.qd-slots-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 6px; }
.qd-slot-btn {
    padding: 9px 10px; border-radius: 8px; border: 1.5px solid #ddd;
    background: #fff; font-size: 12.5px; font-weight: 600; color: #555;
    cursor: pointer; text-align: center; transition: all .2s; font-family: 'Exo 2', sans-serif;
    line-height: 1.3;
}
.qd-slot-btn:hover { border-color: #F5A623; color: #b87800; background: rgba(245,166,35,.06); }
.qd-slot-btn.on    { border-color: #F5A623; color: #b87800; background: rgba(245,166,35,.12); }
.qd-slot-btn.full  { background: #f5f5f5; color: #bbb; cursor: not-allowed; border-color: #e8e8e8; }

/* CTA */
.qd-cta-btn {
    width: 100%; height: 52px; border: none; border-radius: 10px;
    background: #F5A623; color: #000; font-family: 'Rajdhani', sans-serif;
    font-size: 18px; font-weight: 700; letter-spacing: .05em;
    cursor: pointer; transition: all .2s; display: flex; align-items: center; justify-content: center; gap: 10px;
    margin-bottom: 14px;
}
.qd-cta-btn:hover  { background: #d4890e; }
.qd-cta-btn:active { transform: scale(.98); }

.qd-back { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #999; cursor: pointer; margin-bottom: 22px; transition: color .2s; background: none; border: none; padding: 0; }
.qd-back:hover { color: #F5A623; }

/* trust badges */
.qd-trust { display: flex; align-items: center; gap: 18px; justify-content: center; margin-top: 2px; flex-wrap: wrap; }
.qd-trust-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #aaa; }
.qd-trust-item i { color: #43a047; font-size: 12px; }

/* step panels */
.qd-step-panel     { display: none; }
.qd-step-panel.on  { display: block; }

/* success state */
.qd-success {
    text-align: center; padding: 20px 0;
}
.qd-success-icon {
    width: 80px; height: 80px; border-radius: 50%;
    background: #e8f5e9; border: 3px solid #43a047;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px; font-size: 36px; color: #43a047;
}
.qd-success h3 { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; color: #1a1a2e; margin: 0 0 10px; }
.qd-success p  { font-size: 14px; color: #777; line-height: 1.7; margin: 0 0 24px; }
.qd-success-ref {
    background: #fff8ed; border: 1px solid #ffe0b2;
    border-radius: 10px; padding: 14px 18px; margin-bottom: 22px;
    font-size: 13px; color: #b45309; font-weight: 600;
}
.qd-success-ref span { font-family: 'Rajdhani', sans-serif; font-size: 18px; color: #F5A623; font-weight: 700; }
.qd-app-cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
.qd-app-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 22px; border-radius: 9px; font-size: 13px; font-weight: 700;
    font-family: 'Exo 2', sans-serif; transition: all .2s;
}
.qd-app-btn.primary   { background: #F5A623; color: #000; }
.qd-app-btn.primary:hover { background: #d4890e; }
.qd-app-btn.secondary { border: 1.5px solid #ddd; color: #555; background: #fff; }
.qd-app-btn.secondary:hover { border-color: #F5A623; color: #b87800; }

/* divider */
.qd-sep { height: 1px; background: #eee; margin: 18px 0; }
</style>

<div class="qd-page">
<div class="qd-card">

    {{-- ══════════════════════════════
         LEFT — PROMO
    ══════════════════════════════ --}}
    <div class="qd-left">

        <div class="qd-logo">
            <div class="qd-logo-icon">Q</div>
            <div>
                <div class="qd-logo-name">CityQuants<sup style="font-size:9px;">®</sup></div>
                <div class="qd-logo-sub">Optimize Opportunities</div>
            </div>
        </div>

        <h2 class="qd-promo-head">See <span>CityQuants</span><br>in Action — Live</h2>
        <p class="qd-promo-sub">
            Book a free, personalised 30-minute demo with one of our options analytics experts. We'll walk you through the platform based on your trading style.
        </p>

        <div class="qd-what-list">
            <div class="qd-what-item">
                <div class="qd-what-icon"><i class="fas fa-chart-bar"></i></div>
                <div class="qd-what-text">
                    <div class="qd-what-title">Live Platform Walkthrough</div>
                    <div class="qd-what-desc">See all 47+ tools — OI analytics, option chain, PCR, IV charts and algo engine — live with real market data.</div>
                </div>
            </div>
            <div class="qd-what-item">
                <div class="qd-what-icon"><i class="fas fa-user-tie"></i></div>
                <div class="qd-what-text">
                    <div class="qd-what-title">1-on-1 with an Expert Trader</div>
                    <div class="qd-what-desc">Not a sales call — a genuine session with an options analytics expert who trades the same strategies.</div>
                </div>
            </div>
            <div class="qd-what-item">
                <div class="qd-what-icon"><i class="fas fa-sliders-h"></i></div>
                <div class="qd-what-text">
                    <div class="qd-what-title">Personalised to Your Style</div>
                    <div class="qd-what-desc">Intraday buyer, expiry seller or positional trader — we tailor the demo to what you actually trade.</div>
                </div>
            </div>
            <div class="qd-what-item">
                <div class="qd-what-icon"><i class="fas fa-gift"></i></div>
                <div class="qd-what-text">
                    <div class="qd-what-title">Free Trial Access</div>
                    <div class="qd-what-desc">Every demo attendee gets 7-day premium trial access — no credit card required.</div>
                </div>
            </div>
        </div>

        {{-- testimonial --}}
        {{-- <div class="qd-testimonial">
            <div class="qd-stars">★★★★★</div>
            <p>"The demo session completely changed how I look at options data. The expert spent 40 minutes with me and walked through exactly the tools I needed for expiry trading. Booked a subscription the same day."</p>
            <div class="qd-testimonial-author">
                <div class="qd-testimonial-avatar">R</div>
                <div>
                    <div class="qd-testimonial-name">Rohit Sharma</div>
                    <div class="qd-testimonial-role">Positional Options Trader, Pune</div>
                </div>
            </div>
        </div> --}}

        {{-- <div class="qd-stats-row">
            <div class="qd-stat">
                <div class="qd-stat-val">6,500+</div>
                <div class="qd-stat-lbl">Demos Given</div>
            </div>
            <div class="qd-stat">
                <div class="qd-stat-val">30 Min</div>
                <div class="qd-stat-lbl">Duration</div>
            </div>
            <div class="qd-stat">
                <div class="qd-stat-val">100%</div>
                <div class="qd-stat-lbl">Free</div>
            </div>
            <div class="qd-stat">
                <div class="qd-stat-val">4.9★</div>
                <div class="qd-stat-lbl">Avg Rating</div>
            </div>
        </div> --}}

    </div>{{-- /.qd-left --}}

    {{-- ══════════════════════════════
         RIGHT — FORM
    ══════════════════════════════ --}}
    <div class="qd-right">

        {{-- Step tracker --}}
        {{-- <div class="qd-steps-row" id="qdStepsRow" style="margin-bottom:32px;">
            <div class="qd-step-wrap">
                <div class="qd-step-dot active" id="sdot0">1</div>
                <div class="qd-step-label">Your Info</div>
            </div>
            <div class="qd-step-line" id="sline0"></div>
            <div class="qd-step-wrap">
                <div class="qd-step-dot pending" id="sdot1">2</div>
                <div class="qd-step-label">Preferences</div>
            </div>
            <div class="qd-step-line" id="sline1"></div>
            <div class="qd-step-wrap">
                <div class="qd-step-dot pending" id="sdot2">3</div>
                <div class="qd-step-label">Pick Slot</div>
            </div>
        </div> --}}

        {{-- ── STEP 1: Personal Info ── --}}
        <div class="qd-step-panel on" id="qdStep0">
            <div class="qd-form-head">Book a Free Demo</div>
            <p class="qd-form-sub">30-minute 1-on-1 session with a CityQuants expert. 100% free, no credit card needed.</p>

            <div class="qd-field-row">
                <div class="qd-field-group">
                    <label class="qd-field-label">First Name <span class="req">*</span></label>
                    <input class="qd-field-input" type="text" id="dFName" placeholder="Rahul">
                </div>
                <div class="qd-field-group">
                    <label class="qd-field-label">Last Name <span class="req">*</span></label>
                    <input class="qd-field-input" type="text" id="dLName" placeholder="Sharma">
                </div>
            </div>

            <div class="qd-field-row single">
                <div class="qd-field-group">
                    <label class="qd-field-label">Email Address <span class="req">*</span></label>
                    <input class="qd-field-input" type="email" id="dEmail" placeholder="rahul@example.com">
                </div>
            </div>

            <div class="qd-field-row single" style="margin-bottom:14px;">
                <div class="qd-field-group">
                    <label class="qd-field-label">Mobile Number <span class="req">*</span></label>
                    <div class="qd-phone-row">
                        <button class="qd-country-btn">
                            <span style="font-size:18px;">🇮🇳</span>
                            <span>+91</span>
                            <i class="fas fa-chevron-down" style="font-size:10px;color:#bbb;margin-left:4px;"></i>
                        </button>
                        <input class="qd-field-input" type="tel" id="dPhone" placeholder="10-digit mobile"
                               maxlength="10" oninput="this.value=this.value.replace(/\D/g,'')" style="flex:1;">
                    </div>
                </div>
            </div>

            <div class="qd-field-row">
                <div class="qd-field-group">
                    <label class="qd-field-label">City</label>
                    <input class="qd-field-input" type="text" id="dCity" placeholder="Mumbai">
                </div>
                <div class="qd-field-group">
                    <label class="qd-field-label">Broker</label>
                    <select class="qd-field-select" id="dBroker">
                        <option value="">Select Broker</option>
                        <option>Zerodha</option>
                        <option>Upstox</option>
                        <option>Dhan</option>
                        <option>5Paisa</option>
                        <option>Fyers</option>
                        <option>Angel Broking</option>
                        <option>ICICI Direct</option>
                        <option>Groww</option>
                        <option>Kotak Securities</option>
                        <option>Other</option>
                    </select>
                </div>
            </div>

            <button class="qd-cta-btn" onclick="qdGoStep(1)">
                Submit <i class="fas fa-arrow-right"></i>
            </button>

            {{-- <div class="qd-trust">
                <div class="qd-trust-item"><i class="fas fa-shield-alt"></i> 100% Free</div>
                <div class="qd-trust-item"><i class="fas fa-lock"></i> No Spam</div>
                <div class="qd-trust-item"><i class="fas fa-check-circle"></i> No Credit Card</div>
            </div> --}}
        </div>

        {{-- ── STEP 2: Trading Preferences ── --}}
        <div class="qd-step-panel" id="qdStep1">
            <button class="qd-back" onclick="qdGoStep(0)">
                <i class="fas fa-arrow-left"></i> Back
            </button>
            <div class="qd-form-head">Your Trading Style</div>
            <p class="qd-form-sub">Help us personalise your demo session.</p>

            <div class="qd-field-row single">
                <div class="qd-field-group">
                    <label class="qd-field-label">Trading Style <span class="req">*</span></label>
                    <select class="qd-field-select" id="dStyle">
                        <option value="">Select your style</option>
                        <option>Intraday Options Buyer</option>
                        <option>Intraday Options Seller / Writer</option>
                        <option>Positional Options Trader</option>
                        <option>Expiry Day Trader</option>
                        <option>Swing Trader</option>
                        <option>Algo / Quantitative Trader</option>
                        <option>Beginner (Learning)</option>
                    </select>
                </div>
            </div>

            <div class="qd-field-row single">
                <div class="qd-field-group">
                    <label class="qd-field-label">Experience Level <span class="req">*</span></label>
                    <select class="qd-field-select" id="dExp">
                        <option value="">Select level</option>
                        <option>Beginner (< 1 year)</option>
                        <option>Intermediate (1–3 years)</option>
                        <option>Advanced (3–7 years)</option>
                        <option>Expert (7+ years)</option>
                    </select>
                </div>
            </div>

            <div class="qd-field-row single">
                <div class="qd-field-group">
                    <label class="qd-field-label">Instruments You Trade</label>
                    <select class="qd-field-select" id="dInstr">
                        <option value="">Select instrument</option>
                        <option>Nifty Options</option>
                        <option>BankNifty Options</option>
                        <option>FinNifty Options</option>
                        <option>Stock Options (NSE F&O)</option>
                        <option>Index + Stock Options</option>
                        <option>Futures</option>
                    </select>
                </div>
            </div>

            <div class="qd-field-row single">
                <div class="qd-field-group">
                    <label class="qd-field-label">What do you want to improve?</label>
                    <textarea class="qd-field-textarea" id="dGoal" placeholder="e.g. I want to learn how to use OI data to find better entry points for intraday selling strategies..."></textarea>
                </div>
            </div>

            <button class="qd-cta-btn" onclick="qdGoStep(2)">
                Pick a Slot <i class="fas fa-arrow-right"></i>
            </button>
        </div>

        {{-- ── STEP 3: Slot Picker ── --}}
        <div class="qd-step-panel" id="qdStep2">
            <button class="qd-back" onclick="qdGoStep(1)">
                <i class="fas fa-arrow-left"></i> Back
            </button>
            <div class="qd-form-head">Pick a Demo Slot</div>
            <p class="qd-form-sub">All times in IST. Sessions are 30 minutes via Google Meet.</p>

            <div class="qd-field-row single" style="margin-bottom:18px;">
                <div class="qd-field-group">
                    <label class="qd-field-label">Preferred Date <span class="req">*</span></label>
                    <input class="qd-field-input" type="date" id="dDate" min="{{ date('Y-m-d', strtotime('+1 day')) }}" onchange="qdLoadSlots(this.value)">
                </div>
            </div>

            <div class="qd-field-group" style="margin-bottom:18px;">
                <label class="qd-field-label">Available Slots</label>
                <div class="qd-slots-grid" id="qdSlotsGrid">
                    <div style="grid-column:1/-1;color:#ccc;font-size:13px;text-align:center;padding:12px 0;">
                        <i class="fas fa-calendar-alt"></i> Please select a date above
                    </div>
                </div>
            </div>

            <div class="qd-field-row single">
                <div class="qd-field-group">
                    <label class="qd-field-label">Additional Notes</label>
                    <textarea class="qd-field-textarea" id="dNotes" placeholder="Anything specific you'd like to cover in the demo?" style="min-height:68px;"></textarea>
                </div>
            </div>

            <div class="qd-sep"></div>

            {{-- summary --}}
            <div id="qdSummary" style="background:#fff8ed;border:1px solid #ffe0b2;border-radius:10px;padding:14px 16px;margin-bottom:18px;font-size:13px;color:#555;line-height:1.7;display:none;">
                <div style="font-weight:700;color:#b45309;margin-bottom:6px;font-family:'Rajdhani',sans-serif;font-size:15px;"><i class="fas fa-clipboard-check" style="margin-right:6px;"></i>Booking Summary</div>
                <div id="qdSumContent"></div>
            </div>

            <button class="qd-cta-btn" onclick="qdSubmit()" id="qdSubmitBtn">
                <i class="fas fa-calendar-check"></i> Confirm My Demo Slot
            </button>

            <div class="qd-trust">
                <div class="qd-trust-item"><i class="fas fa-envelope"></i> Confirmation via Email & SMS</div>
                <div class="qd-trust-item"><i class="fas fa-video"></i> Google Meet link sent instantly</div>
            </div>
        </div>

        {{-- ── STEP 4: SUCCESS ── --}}
        <div class="qd-step-panel" id="qdStep3">
            <div class="qd-success">
                <div class="qd-success-icon"><i class="fas fa-check"></i></div>
                <h3>Demo Booked Successfully! 🎉</h3>
                <p>You're all set. Our expert will join you on Google Meet at your chosen time. A confirmation with the meeting link has been sent to your email and mobile.</p>
                <div class="qd-success-ref">
                    Booking Reference: <span id="qdRefId">CQ-DEMO-XXXXX</span>
                </div>
                <div style="font-size:13px;color:#888;margin-bottom:24px;">
                    <i class="fas fa-info-circle" style="color:#F5A623;margin-right:6px;"></i>
                    In the meantime, explore <strong>25 free tools</strong> on CityQuants.
                </div>
                <div class="qd-app-cta">
                    <a href="#" class="qd-app-btn primary"><i class="fas fa-rocket"></i> Explore Free Tools</a>
                    <a href="{{ route('webinars') }}" class="qd-app-btn secondary"><i class="fas fa-video"></i> Watch Webinars</a>
                </div>
            </div>
        </div>

    </div>{{-- /.qd-right --}}

</div>{{-- /.qd-card --}}
</div>{{-- /.qd-page --}}

<script>
var qdSelectedSlot = null;

/* ── STEP NAVIGATION ── */
function qdGoStep(step) {
    // validate before advancing
    if (step === 1) {
        var fn = document.getElementById('dFName').value.trim();
        var ln = document.getElementById('dLName').value.trim();
        var em = document.getElementById('dEmail').value.trim();
        var ph = document.getElementById('dPhone').value.trim();
        if (!fn || !ln) { alert('Please enter your full name.'); return; }
        if (!em || em.indexOf('@') < 0) { alert('Please enter a valid email address.'); return; }
        if (ph.length < 10) { alert('Please enter a valid 10-digit mobile number.'); return; }
    }
    if (step === 2) {
        var st = document.getElementById('dStyle').value;
        var ex = document.getElementById('dExp').value;
        if (!st) { alert('Please select your trading style.'); return; }
        if (!ex) { alert('Please select your experience level.'); return; }
    }

    // update step dots
    for (var i = 0; i < 3; i++) {
        var dot = document.getElementById('sdot' + i);
        if (i < step)       { dot.className = 'qd-step-dot done'; dot.innerHTML = '<i class="fas fa-check" style="font-size:11px;"></i>'; }
        else if (i === step){ dot.className = 'qd-step-dot active'; dot.textContent = i + 1; }
        else                { dot.className = 'qd-step-dot pending'; dot.textContent = i + 1; }
        if (i < 2) {
            var ln = document.getElementById('sline' + i);
            ln.className = 'qd-step-line' + (i < step ? ' done' : '');
        }
    }

    document.querySelectorAll('.qd-step-panel').forEach(function(p, i) {
        p.classList.toggle('on', i === step);
    });
}

/* ── SLOT LOADER ── */
function qdLoadSlots(dateStr) {
    if (!dateStr) return;
    var day = new Date(dateStr).getDay(); // 0 Sun, 6 Sat
    var allSlots = [
        { time: '09:30 AM', avail: true },
        { time: '10:30 AM', avail: day !== 0 },
        { time: '11:30 AM', avail: true },
        { time: '12:30 PM', avail: day === 6 || day === 0 ? false : true },
        { time: '02:00 PM', avail: true },
        { time: '03:30 PM', avail: day !== 6 },
        { time: '05:00 PM', avail: true },
        { time: '06:30 PM', avail: true },
        { time: '07:30 PM', avail: day !== 0 },
    ];
    var grid = document.getElementById('qdSlotsGrid');
    grid.innerHTML = '';
    qdSelectedSlot = null;
    allSlots.forEach(function(slot) {
        var btn = document.createElement('button');
        btn.className = 'qd-slot-btn' + (slot.avail ? '' : ' full');
        btn.textContent = slot.time;
        if (!slot.avail) {
            btn.title = 'Fully booked';
            btn.disabled = true;
            btn.textContent += '\nFull';
        } else {
            btn.onclick = function() {
                document.querySelectorAll('.qd-slot-btn').forEach(function(b) { b.classList.remove('on'); });
                btn.classList.add('on');
                qdSelectedSlot = slot.time;
                qdShowSummary(dateStr, slot.time);
            };
        }
        grid.appendChild(btn);
    });
}

/* ── SUMMARY ── */
function qdShowSummary(dateStr, time) {
    var fn   = document.getElementById('dFName').value.trim();
    var ln   = document.getElementById('dLName').value.trim();
    var em   = document.getElementById('dEmail').value.trim();
    var st   = document.getElementById('dStyle').value;
    var d    = new Date(dateStr);
    var opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    var dateFormatted = d.toLocaleDateString('en-IN', opts);
    var html = '<b>' + fn + ' ' + ln + '</b> &lt;' + em + '&gt;<br>' +
               '<i class="fas fa-calendar-alt" style="color:#F5A623;margin-right:5px;"></i>' + dateFormatted + ' &nbsp;·&nbsp; ' + time + ' IST<br>' +
               '<i class="fas fa-chart-line" style="color:#F5A623;margin-right:5px;"></i>' + (st || 'Not specified');
    document.getElementById('qdSumContent').innerHTML = html;
    document.getElementById('qdSummary').style.display = 'block';
}

/* ── SUBMIT ── */
function qdSubmit() {
    var date = document.getElementById('dDate').value;
    if (!date)           { alert('Please select a date.'); return; }
    if (!qdSelectedSlot) { alert('Please select a time slot.'); return; }

    var btn  = document.getElementById('qdSubmitBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Confirming...';
    btn.disabled  = true;

    setTimeout(function() {
        // generate ref
        var ref = 'CQ-DEMO-' + Math.random().toString(36).substr(2,6).toUpperCase();
        document.getElementById('qdRefId').textContent = ref;

        // hide steps row, show success
        document.getElementById('qdStepsRow').style.display = 'none';
        document.querySelectorAll('.qd-step-panel').forEach(function(p) { p.classList.remove('on'); });
        document.getElementById('qdStep3').classList.add('on');
    }, 1200);
}
</script>

@endsection
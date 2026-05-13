{{--
    Shared styles + right-panel promo for all auth pages.
    Include at top of each auth blade: @include($activeTemplate.'partials.auth-shell')
--}}

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* ── Page ─────────────────────────────────── */
.ql-page {
    min-height:100vh; background:#FDECC8;
    display:flex; align-items:center; justify-content:center;
    padding:40px 20px; font-family:'Exo 2',sans-serif;
}
.ql-page * { box-sizing:border-box; }

/* ── Card ────────────────────────────────── */
.ql-card {
    background:#F0EFED; border-radius:24px;
    width:100%; max-width:1120px;
    display:grid; grid-template-columns:420px 1fr;
    overflow:hidden; box-shadow:0 24px 80px rgba(0,0,0,.14);
    min-height:600px;
}
@media(max-width:860px){
    .ql-card { grid-template-columns:1fr; max-width:480px; }
    .ql-right { display:none; }
}

/* ── Left panel ───────────────────────────── */
.ql-left {
    background:#F0EFED; padding:52px 48px 40px;
    display:flex; flex-direction:column;
    border-right:1px solid rgba(0,0,0,.08);
}
@media(max-width:860px){ .ql-left { padding:36px 28px; border-right:none; } }

/* Logo */
.ql-logo { display:flex; align-items:center; gap:10px; margin-bottom:32px; text-decoration:none; }
.ql-logo-icon {
    width:48px; height:48px; border-radius:10px; background:#F5A623;
    display:flex; align-items:center; justify-content:center;
    font-size:22px; font-weight:700; color:#fff;
    font-family:'Rajdhani',sans-serif; flex-shrink:0;
}
.ql-logo-name { font-family:'Rajdhani',sans-serif; font-size:19px; font-weight:700; color:#1a1a1a; }
.ql-logo-sub  { font-size:11px; color:#888; letter-spacing:.03em; }

/* Heading */
.ql-heading {
    font-family:'Rajdhani',sans-serif; font-size:32px; font-weight:700;
    color:#1a1a1a; margin:0 0 26px; line-height:1;
}

/* Divider */
.ql-divider {
    display:flex; align-items:center; gap:14px;
    margin-bottom:22px; color:#aaa; font-size:13px;
}
.ql-divider::before,.ql-divider::after { content:''; flex:1; height:1px; background:#ddd; }

/* Input */
.ql-input-group { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
.ql-input-label { font-size:11px; color:#999; font-weight:700; letter-spacing:.05em; }
.ql-input-field {
    height:52px; padding:0 16px; width:100%; border:1.5px solid #ddd;
    border-radius:10px; font-size:15px; color:#1a1a1a; background:#fff;
    font-family:'Exo 2',sans-serif; outline:none; transition:border-color .2s;
}
.ql-input-field:focus { border-color:#F5A623; }
.ql-input-field::placeholder { color:#bbb; }
.ql-input-field.is-error { border-color:#e53935; }
.ql-input-error { font-size:12px; color:#e53935; margin-top:3px; }

/* Password wrapper with toggle */
.ql-pw-wrap { position:relative; }
.ql-pw-wrap .ql-input-field { padding-right:44px; }
.ql-pw-toggle {
    position:absolute; right:14px; top:50%; transform:translateY(-50%);
    background:none; border:none; cursor:pointer; color:#bbb; padding:0;
    display:flex; align-items:center;
}
.ql-pw-toggle:hover { color:#F5A623; }

/* OTP boxes */
.ql-otp-row { display:flex; gap:10px; margin-bottom:16px; }
.ql-otp-box {
    flex:1; height:55px; width:55px; text-align:center; border:1.5px solid #ddd;
    border-radius:10px; font-size:26px; font-weight:700; color:#1a1a1a;
    background:#fff; outline:none; transition:border-color .2s;
    font-family:'Rajdhani',sans-serif;
}
.ql-otp-box:focus { border-color:#F5A623; box-shadow:0 0 0 3px rgba(245,166,35,.15); }

/* CTA button */
.ql-cta-btn {
    width:100%; height:52px; border:none; border-radius:10px;
    background:#F5A623; color:#fff; font-family:'Rajdhani',sans-serif;
    font-size:18px; font-weight:700; letter-spacing:.06em;
    cursor:pointer; transition:background .2s, transform .15s; margin-bottom:12px;
    display:flex; align-items:center; justify-content:center; gap:8px;
}
.ql-cta-btn:hover  { background:#d4890e; }
.ql-cta-btn:active { transform:scale(.98); }
.ql-cta-btn:disabled { background:#e0c080; cursor:not-allowed; }

/* Secondary / outline button */
.ql-outline-btn {
    width:100%; height:52px; border:1.5px solid #F5A623; border-radius:10px;
    background:transparent; color:#F5A623; font-family:'Rajdhani',sans-serif;
    font-size:16px; font-weight:700; letter-spacing:.04em;
    cursor:pointer; transition:all .2s; margin-bottom:12px;
}
.ql-outline-btn:hover { background:#F5A623; color:#fff; }

/* Text links */
.ql-text-link { font-size:13px; color:#888; }
.ql-text-link a { color:#888; text-decoration:none; }
.ql-text-link a:hover { color:#F5A623; }
.ql-text-link a.accent { color:#F5A623; }

/* Back arrow */
.ql-back {
    display:inline-flex; align-items:center; gap:6px;
    font-size:13px; color:#888; cursor:pointer;
    margin-bottom:18px; transition:color .2s; background:none; border:none; padding:0;
}
.ql-back:hover { color:#F5A623; }

/* Separator */
.ql-sep { height:1px; background:#ddd; margin:18px 0; }

/* T&C */
.ql-tnc { display:flex; align-items:flex-start; gap:10px; font-size:12px; color:#666; line-height:1.55; }
.ql-tnc-cb {
    width:18px; height:18px; border-radius:4px; background:#F5A623; border:none;
    flex-shrink:0; margin-top:2px; cursor:pointer;
    display:flex; align-items:center; justify-content:center;
}
.ql-tnc-cb i { color:#fff; font-size:10px; }
.ql-tnc a { color:#F5A623; text-decoration:underline; }

/* Step visibility */
.ql-step { display:none; }
.ql-step.active { display:block; }

/* Toast */
.ql-toast {
    position:fixed; bottom:28px; right:28px; z-index:9999;
    padding:14px 22px; border-radius:10px; font-size:14px; font-weight:600;
    box-shadow:0 8px 32px rgba(0,0,0,.2); animation:toastIn .3s ease;
    max-width:360px;
}
.ql-toast.success { background:#2e7d32; color:#fff; }
.ql-toast.error   { background:#c62828; color:#fff; }
@keyframes toastIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:none; } }

/* ── Right panel ──────────────────────────── */
.ql-right {
    background:#F0EFED; padding:40px 36px 36px;
    display:flex; flex-direction:column; gap:0;width:35%;
}
.ql-right-title {
    font-family:'Rajdhani',sans-serif;
    font-size:clamp(20px,2.3vw,28px); font-weight:700;
    color:#1a1a1a; margin:0 0 20px; text-align:center; line-height:1.2;
}
.ql-right-title span { color:#F5A623; }
.ql-video-wrap {
    border-radius:14px; overflow:hidden; margin-bottom:18px;
    background:#000; position:relative; aspect-ratio:16/9;
    box-shadow:0 8px 32px rgba(0,0,0,.2);
}
.ql-video-wrap iframe { width:100%; height:100%; display:block; border:none; }
.ql-video-logo {
    position:absolute; top:12px; right:14px;
    width:32px; height:32px; border-radius:8px; background:#F5A623;
    display:flex; align-items:center; justify-content:center;
    font-size:14px; font-weight:700; color:#fff;
    font-family:'Rajdhani',sans-serif; pointer-events:none;
}
.ql-features { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; margin-bottom:20px; }
.ql-feat-pill {
    background:rgba(0,0,0,.08); border-radius:30px;
    padding:8px 16px; font-size:13px; font-weight:600; color:#333; white-space:nowrap;
}
.ql-trade-label { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
.ql-trade-line  { flex:1; height:1.5px; background:#F5A623; border-radius:2px; }
.ql-trade-text  { font-size:14px; font-weight:600; color:#333; white-space:nowrap; }
.ql-brokers-wrap { overflow:hidden; }
.ql-brokers-track {
    display:flex; gap:18px;
    animation:brokerScroll 24s linear infinite;
    width:max-content;
}
.ql-brokers-wrap:hover .ql-brokers-track { animation-play-state:paused; }
@keyframes brokerScroll {
    from { transform:translateX(0); }
    to   { transform:translateX(-50%); }
}
.ql-broker { display:flex; flex-direction:column; align-items:center; gap:5px; flex-shrink:0; }
.ql-broker-logo {
    width:50px; height:50px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:18px; font-weight:700; color:#fff;
    font-family:'Rajdhani',sans-serif;
    border:2px solid rgba(255,255,255,.3);
}
.ql-broker-name { font-size:11px; color:#555; font-weight:500; text-align:center; }

/* Spinner */
.ql-spinner {
    width:18px; height:18px; border:2.5px solid rgba(255,255,255,.4);
    border-top-color:#fff; border-radius:50%;
    animation:spin .7s linear infinite; display:inline-block;
}
@keyframes spin { to { transform:rotate(360deg); } }
</style>

{{-- Shared JS utilities --}}
<script>
function qlToast(msg, type) {
    var t = document.createElement('div');
    t.className = 'ql-toast ' + (type || 'success');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function(){ t.remove(); }, 4000);
}

function qlLoading(btn, on) {
    if (on) {
        btn.disabled = true;
        btn.dataset.orig = btn.innerHTML;
        btn.innerHTML = '<span class="ql-spinner"></span>';
    } else {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.orig;
    }
}

// OTP box keyboard handling — auto-advance + backspace
function initOtpBoxes(selector) {
    var boxes = document.querySelectorAll(selector);
    boxes.forEach(function(box, i) {
        box.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g,'').slice(-1);
            if (this.value && i < boxes.length - 1) boxes[i+1].focus();
        });
        box.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !this.value && i > 0) boxes[i-1].focus();
        });
        box.addEventListener('paste', function(e) {
            var paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
            boxes.forEach(function(b, j){ b.value = paste[j] || ''; });
            e.preventDefault();
            var last = Math.min(paste.length, boxes.length) - 1;
            if (last >= 0) boxes[last].focus();
        });
    });
}

function getOtpValue(selector) {
    return Array.from(document.querySelectorAll(selector)).map(function(b){ return b.value; }).join('');
}

function showStep(id) {
    document.querySelectorAll('.ql-step').forEach(function(s){ s.classList.remove('active'); });
    document.getElementById(id).classList.add('active');
}

function togglePassword(btn) {
    var input = btn.previousElementSibling;
    var icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}
</script>
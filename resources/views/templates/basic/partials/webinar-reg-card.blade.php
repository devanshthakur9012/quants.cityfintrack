{{-- FILE: resources/views/themes/{active_theme}/partials/webinar-reg-card.blade.php --}}

<div class="wd-reg-card">

    {{-- Price --}}
    <div class="wd-reg-price-wrap">
        <div class="wd-reg-price">
            @if($isEnrolled)
                <span style="color:#43a047;font-size:22px;">✓ Registered</span>
            @elseif($webinar->type === 'free')
                FREE
                @if($webinar->mrp > 0)
                    <span class="orig">₹{{ number_format($webinar->mrp) }}</span>
                    <span class="disc">100% off</span>
                @endif
            @else
                ₹{{ number_format($webinar->price) }}
                @if($webinar->mrp > $webinar->price)
                    <span class="orig">₹{{ number_format($webinar->mrp) }}</span>
                    @if($webinar->discount_label)
                        <span class="disc">{{ $webinar->discount_label }}</span>
                    @endif
                @endif
            @endif
        </div>
    </div>

    @if($webinar->webinar_date)
    <div class="wd-reg-date-label">
        <i class="fas fa-calendar-alt" style="color:#F5A623;"></i>
        {{ $webinar->webinar_date->format('d M Y, h:i A') }}
    </div>
    @endif

    <div class="wd-reg-body">

        {{-- Action Button --}}
        @if($isEnrolled)
            <button class="wd-reg-btn enrolled-btn" disabled>
                <i class="fas fa-check-circle"></i> You are Registered
            </button>
        @elseif($webinar->status === 'past')
            <button class="wd-reg-btn past-btn" disabled>
                <i class="fas fa-clock"></i> Webinar Ended
            </button>
        @elseif($webinar->total_seats && $webinar->seats_left <= 0)
            <button class="wd-reg-btn full-btn" disabled>
                <i class="fas fa-users-slash"></i> Seats Full
            </button>
        @elseif($webinar->type === 'free')
            <button class="wd-reg-btn free-btn" onclick="handleEnroll()" type="button">
                <i class="fas fa-check-circle"></i> Register FREE
            </button>
        @else
            <button class="wd-reg-btn paid-btn" onclick="handleEnroll()" type="button">
                <i class="fas fa-lock-open"></i> Register Now — ₹{{ number_format($webinar->price) }}
            </button>
        @endif

        {{-- Seats note --}}
        @if(!$isEnrolled && $webinar->total_seats && $webinar->seats_left > 0)
        <div class="wd-seats-note" style="color:{{ $webinar->seats_left < 20 ? '#e53935' : '#888' }};">
            <i class="fas fa-users"></i>
            Only {{ $webinar->seats_left }} seats left!
        </div>
        @endif

        {{-- Meta details --}}
        <div class="wd-reg-meta">
            <div class="wd-reg-meta-row">
                <i class="fas fa-signal"></i> {{ $webinar->level }}
            </div>
            <div class="wd-reg-meta-row">
                <i class="fas fa-language"></i> {{ $webinar->language }}
            </div>
            @if($webinar->duration)
            <div class="wd-reg-meta-row">
                <i class="fas fa-clock"></i> {{ $webinar->duration }}
            </div>
            @endif
            <div class="wd-reg-meta-row">
                <i class="fas fa-{{ $webinar->mode === 'online' ? 'laptop' : ($webinar->mode === 'offline' ? 'map-marker-alt' : 'broadcast-tower') }}"></i>
                {{ ucfirst($webinar->mode) }}
                @if(in_array($webinar->mode, ['offline','hybrid']) && $webinar->address)
                    — {{ Str::limit($webinar->address, 40) }}
                @endif
            </div>
            <div class="wd-reg-meta-row">
                <i class="fas fa-users"></i> {{ $webinar->total_enrolled }} registered
            </div>
        </div>

    </div>
</div>
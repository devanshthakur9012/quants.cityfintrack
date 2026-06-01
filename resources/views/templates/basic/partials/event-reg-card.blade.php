{{-- FILE: resources/views/themes/{active_theme}/partials/event-reg-card.blade.php --}}
<div class="ed-reg-card">
    <div class="ed-reg-price-wrap">
        <div class="ed-reg-price">
            @if($isBooked)
                <span style="color:#43a047;font-size:20px;">✓ Registered</span>
            @elseif($event->type === 'free')
                FREE @if($event->mrp > 0)<span class="orig">₹{{ number_format($event->mrp) }}</span>@endif
            @else
                ₹{{ number_format($event->price) }}
                @if($event->mrp > $event->price)
                    <span class="orig">₹{{ number_format($event->mrp) }}</span>
                    <span class="disc">{{ $event->discount_label_auto }}</span>
                @endif
            @endif
        </div>
    </div>

    @if($event->formatted_date !== '—')
    <div class="ed-reg-date-label">
        <i class="fas fa-calendar-alt" style="color:#7DFF00;"></i>
        {{ $event->formatted_date }}
        @if($event->formatted_time !== '—') · {{ $event->formatted_time }}@endif
    </div>
    @endif

    <div class="ed-reg-body">
        {{-- Button --}}
        @if($isBooked)
            <button class="ed-reg-btn booked-btn" disabled><i class="fas fa-check-circle"></i> You are Registered</button>
        @elseif($event->status === 'past')
            <button class="ed-reg-btn closed-btn" disabled>Event Ended</button>
        @elseif(!$event->booking_open)
            <button class="ed-reg-btn closed-btn" disabled>Bookings Closed</button>
        @elseif($event->total_seats && $event->seats_left <= 0)
            <button class="ed-reg-btn closed-btn" disabled>Event Full</button>
        @elseif($event->type === 'free')
            <button class="ed-reg-btn free-btn" onclick="openBookingPopup()" type="button">
                <i class="fas fa-check-circle"></i> Register FREE
            </button>
        @else
            <button class="ed-reg-btn paid-btn" onclick="openBookingPopup()" type="button">
                <i class="fas fa-ticket-alt"></i> Book Seat — ₹{{ number_format($event->price) }}
            </button>
        @endif

        @if(!$isBooked && $event->total_seats && $event->seats_left > 0)
        <div style="text-align:center;font-size:12px;margin-top:8px;color:{{ $event->seats_low ? '#e53935' : '#888' }};">
            <i class="fas fa-chair"></i> {{ $event->seats_left }} seats left
        </div>
        @endif

        <div class="ed-reg-meta">
            @if($event->formatted_duration !== '—')
            <div class="ed-reg-meta-row"><i class="fas fa-hourglass-half"></i> {{ $event->formatted_duration }}</div>
            @endif
            @if($event->location)
            <div class="ed-reg-meta-row"><i class="fas fa-map-marker-alt"></i> {{ Str::limit($event->location, 40) }}</div>
            @endif
            @if($event->speakers->isNotEmpty())
            <div class="ed-reg-meta-row"><i class="fas fa-user-tie"></i> {{ $event->speakers->count() }} Speaker{{ $event->speakers->count() > 1 ? 's' : '' }}</div>
            @endif
            <div class="ed-reg-meta-row"><i class="fas fa-users"></i> {{ $event->total_booked }} Registered</div>
        </div>
    </div>
</div>

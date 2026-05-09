@extends($activeTemplate.'layouts.frontend')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
.qev-page * { box-sizing: border-box; }
.qev-page {
    font-family: 'Exo 2', sans-serif;
    background: #f5f5f7;
}

/* ── EMPTY STATE ── */
.qev-empty {
    min-height: 82vh;
    display: flex; align-items: center; justify-content: center;
    flex-direction: column;
    padding: 60px 20px;
}
.qev-empty-img {
    width: 100%; max-width: 400px;
    margin-bottom: 30px;
}
.qev-empty-img img { width: 100%; object-fit: contain; display: block; }
.qev-empty-label {
    font-size: 16px; color: #444; font-weight: 500;
    margin-bottom: 8px; text-align: center;
}
.qev-empty-msg {
    font-size: clamp(20px, 2.8vw, 28px);
    color: #e53935; font-weight: 700; text-align: center; margin: 0;
}

/* ── EVENTS GRID (shown when events exist) ── */
.qev-wrap {
    max-width: 1160px; margin: 0 auto;
    padding: 48px 24px 72px; width: 100%;
}
.qev-page-title {
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(24px, 3vw, 36px); font-weight: 700;
    color: #1a1a2e; margin: 0 0 30px;
}
.qev-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;
}
@media(max-width:900px) { .qev-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:560px) { .qev-grid { grid-template-columns: 1fr; } }

.qev-card {
    background: #fff; border-radius: 14px; overflow: hidden;
    border: 1px solid #e8e8e8;
    transition: transform .25s, box-shadow .25s;
    display: flex; flex-direction: column;
}
.qev-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.1); }

.qev-card-thumb { aspect-ratio: 16/9; overflow: hidden; background: #1a1a2e; }
.qev-card-thumb img {
    width:100%; height:100%; object-fit:cover; display:block; transition:transform .4s;
}
.qev-card:hover .qev-card-thumb img { transform: scale(1.05); }

.qev-card-body { padding: 18px 20px; flex:1; display:flex; flex-direction:column; }
.qev-card-date {
    font-size: 12px; color: #F5A623; font-weight: 700; letter-spacing:.05em;
    text-transform: uppercase; margin-bottom: 8px;
}
.qev-card-title {
    font-family: 'Rajdhani', sans-serif; font-size: 18px; font-weight: 700;
    color: #1a1a2e; margin-bottom: 10px; line-height: 1.3; flex:1;
}
.qev-card-meta { font-size: 13px; color: #888; display:flex; gap:14px; flex-wrap:wrap; }
.qev-card-meta span { display:flex; align-items:center; gap:5px; }
.qev-card-meta i { color: #F5A623; font-size: 11px; }

.qev-card-footer {
    padding: 12px 20px; border-top: 1px solid #f0f0f0; background: #fafafa;
    display: flex; align-items: center; justify-content: space-between;
}
.qev-card-price {
    font-family:'Rajdhani',sans-serif; font-size:17px; font-weight:700; color:#1a1a2e;
}
.qev-card-btn {
    display: inline-flex; align-items: center; gap: 6px;
    background: #F5A623; color: #fff; font-weight: 700; font-size: 13px;
    padding: 8px 18px; border-radius: 8px; transition: background .2s;
    font-family: 'Exo 2', sans-serif; text-decoration: none;
}
.qev-card-btn:hover { background: #d4890e; }
</style>

<div class="qev-page">

@if(empty($events) || count($events) === 0)

    {{-- EMPTY STATE — matching screenshot exactly --}}
    <div class="qev-empty">
        <div class="qev-empty-img">
            <img src="https://img.freepik.com/free-vector/calendar-schedule-concept-illustration_114360-6404.jpg?w=500"
                 alt="No Events Scheduled">
        </div>
        <div class="qev-empty-label">Events</div>
        <p class="qev-empty-msg">No upcoming events scheduled.</p>
    </div>

@else

    {{-- EVENTS GRID --}}
    <div class="qev-wrap">
        <h1 class="qev-page-title">Upcoming Events</h1>
        <div class="qev-grid">
            @foreach($events as $ev)
            <div class="qev-card">
                <div class="qev-card-thumb">
                    <img src="{{ $ev['thumbnail'] }}" alt="{{ $ev['title'] }}" loading="lazy">
                </div>
                <div class="qev-card-body">
                    <div class="qev-card-date">{{ $ev['date'] }}</div>
                    <div class="qev-card-title">{{ $ev['title'] }}</div>
                    <div class="qev-card-meta">
                        <span><i class="fas fa-map-marker-alt"></i>{{ $ev['location'] }}</span>
                        <span><i class="fas fa-clock"></i>{{ $ev['time'] }}</span>
                    </div>
                </div>
                <div class="qev-card-footer">
                    <div class="qev-card-price">
                        @if($ev['price'] == 0) FREE @else ₹{{ number_format($ev['price']) }}/- @endif
                    </div>
                    <a href="{{ $ev['url'] }}" class="qev-card-btn">
                        Register <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>

@endif

</div>

@endsection
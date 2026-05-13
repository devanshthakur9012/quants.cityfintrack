{{-- Right promo panel — included in every auth page --}}
<div class="ql-right">
    <h2 class="ql-right-title">
        <span>Options Trading</span> Analytical Platform
    </h2>

    <div class="ql-video-wrap">
        <iframe src="{{ $promoVideo }}"
                allow="autoplay; encrypted-media"
                allowfullscreen></iframe>
        <div class="ql-video-logo">Q</div>
    </div>

    <div class="ql-features">
        @foreach($features as $feat)
            <div class="ql-feat-pill">{{ $feat }}</div>
        @endforeach
    </div>

    <div class="ql-trade-label">
        <div class="ql-trade-line"></div>
        <div class="ql-trade-text">Trade With</div>
        <div class="ql-trade-line"></div>
    </div>

    <div class="ql-brokers-wrap">
        <div class="ql-brokers-track">
            @php $allBrokers = array_merge($brokers, $brokers); @endphp
            @foreach($allBrokers as $b)
            <div class="ql-broker">
                <div class="ql-broker-logo" style="background:{{ $b['bg'] }};">{{ $b['letter'] }}</div>
                <div class="ql-broker-name">{{ $b['name'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</div>
{{--
    Partial: resources/views/{activeTemplate}/partials/media-card.blade.php
    Usage:   @include($activeTemplate.'partials.media-card', ['item' => $item])
--}}
<div class="qmd-card {{ $item['category'] === 'award' ? 'award-card' : '' }}"
     data-cat="{{ $item['category'] }}"
     data-title="{{ strtolower($item['title']) }}"
     data-tags="{{ strtolower(implode(' ', $item['tags'])) }}"
     data-year="{{ \Carbon\Carbon::createFromFormat('d M Y', $item['date'])->year }}">

    <div class="qmd-card-thumb">
        <img src="{{ $item['thumbnail'] }}" alt="{{ $item['title'] }}" loading="lazy">
        <span class="qmd-cat-badge {{ $item['category'] }}">{{ $item['cat_label'] }}</span>
        @if(in_array($item['category'], ['tv','webinar','podcast']))
            <div class="qmd-play-ov"><i class="fas fa-play-circle"></i></div>
        @endif
        <div class="qmd-dur-badge">
            <i class="fas fa-clock"></i> {{ $item['duration'] }}
        </div>
    </div>

    <div class="qmd-card-body">
        <div class="qmd-card-meta-top">
            <span class="qmd-channel-pill">{{ $item['channel'] }}</span>
            <span class="qmd-card-date">
                <i class="fas fa-calendar-alt"></i> {{ $item['date'] }}
            </span>
        </div>
        <div class="qmd-card-title">{{ $item['title'] }}</div>
        <div class="qmd-card-desc">{{ $item['desc'] }}</div>
        <div class="qmd-card-tags">
            @foreach($item['tags'] as $tag)
                <span class="qmd-card-tag">{{ $tag }}</span>
            @endforeach
        </div>
    </div>

    <div class="qmd-card-footer">
        <span class="qmd-source-txt">
            <i class="fas fa-external-link-alt"></i> {{ $item['channel'] }}
        </span>
        <a href="{{ $item['url'] }}" class="qmd-watch-btn">
            @if($item['category'] === 'press')
                Read <i class="fas fa-arrow-right"></i>
            @elseif($item['category'] === 'award')
                View <i class="fas fa-trophy"></i>
            @else
                Watch <i class="fas fa-play"></i>
            @endif
        </a>
    </div>

</div>
<?php
// FILE: app/Models/Event.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title','slug','badge',
        'event_date','event_time_start','event_time_end','duration_hours',
        'location','city',
        'type','price','mrp','discount_percent','discount_label',
        'total_seats','total_booked','booking_open',
        'video_type','video_url',
        'description','thumbnail','tags',
        'gallery_section_title',
        'status','is_featured','sort_order',
    ];

    protected $casts = [
        'tags'            => 'array',
        'is_featured'     => 'boolean',
        'booking_open'    => 'boolean',
        'event_date'      => 'date',
        'price'           => 'integer',
        'mrp'             => 'integer',
        'total_seats'     => 'integer',
        'total_booked'    => 'integer',
        'discount_percent'=> 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────
    public function speakers()
    {
        return $this->belongsToMany(User::class, 'event_speaker_pivot', 'event_id', 'user_id')
                    ->withPivot('sort_order')
                    ->orderBy('event_speaker_pivot.sort_order')
                    ->with('employeeProfile');
    }

    public function galleryItems()
    {
        return $this->hasMany(EventGalleryItem::class)->orderBy('sort_order');
    }

    public function faqs()
    {
        return $this->hasMany(EventFaq::class)->orderBy('sort_order');
    }

    public function bookings()
    {
        return $this->hasMany(EventBooking::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────
    public function scopeUpcoming($q) { return $q->where('status','upcoming'); }
    public function scopeOngoing($q)  { return $q->where('status','ongoing'); }
    public function scopePast($q)     { return $q->where('status','past'); }

    // ── Accessors ─────────────────────────────────────────────────────────
    public function getThumbnailUrlAttribute(): string
    {
        if (!$this->thumbnail) return asset('assets/images/events/placeholder.jpg');
        if (filter_var($this->thumbnail, FILTER_VALIDATE_URL)) return $this->thumbnail;
        return asset('assets/images/events/'.$this->thumbnail);
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->event_date ? $this->event_date->format('d M Y') : '—';
    }

    public function getFormattedTimeAttribute(): string
    {
        if ($this->event_time_start && $this->event_time_end) {
            return date('h:i A', strtotime($this->event_time_start)).' – '.date('h:i A', strtotime($this->event_time_end));
        }
        return $this->event_time_start ? date('h:i A', strtotime($this->event_time_start)) : '—';
    }

    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration_hours) return '—';
        return $this->duration_hours.($this->duration_hours == 1 ? ' Hour' : ' Hours');
    }

    public function getSeatsLeftAttribute(): ?int
    {
        if ($this->total_seats === null) return null;
        return max(0, $this->total_seats - $this->total_booked);
    }

    public function getSeatsLowAttribute(): bool
    {
        $left = $this->seats_left;
        return $left !== null && $left <= 20;
    }

    public function getDiscountLabelAutoAttribute(): string
    {
        if ($this->discount_percent > 0) return round($this->discount_percent).'% off';
        return $this->discount_label ?? '';
    }

    public function getTagsArrayAttribute(): array { return $this->tags ?? []; }

    public function getVideoEmbedUrlAttribute(): ?string
    {
        if (!$this->video_url) return null;
        if ($this->video_type === 'youtube') {
            preg_match('/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_\-]{11})/', $this->video_url, $m);
            return isset($m[1]) ? 'https://www.youtube.com/embed/'.$m[1].'?rel=0' : null;
        }
        return asset('assets/videos/events/'.$this->video_url);
    }

    // Countdown seconds remaining (positive = future, negative/0 = started)
    public function getCountdownSecondsAttribute(): int
    {
        if (!$this->event_date) return 0;
        $dateStr = $this->event_date->format('Y-m-d');
        $timeStr = $this->event_time_start ?? '00:00:00';
        $target  = strtotime($dateStr.' '.$timeStr);
        return max(0, $target - time());
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    public function isBookedBy(string $email): bool
    {
        return $this->bookings()->where('email',$email)->where('status','confirmed')->exists();
    }

    public function canBook(): bool
    {
        if (!$this->booking_open) return false;
        if ($this->status === 'past') return false;
        if ($this->total_seats !== null && $this->seats_left <= 0) return false;
        return true;
    }

    public static function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = $base = Str::slug($title);
        $i = 1;
        while (static::where('slug',$slug)->when($ignoreId, fn($q) => $q->where('id','!=',$ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }
        return $slug;
    }
}

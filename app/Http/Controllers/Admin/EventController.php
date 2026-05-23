<?php
// FILE: app/Http/Controllers/Admin/EventController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventFaq;
use App\Models\EventGalleryItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends Controller
{
    const CITIES = [
        'bangalore'       => 'Bangalore',
        'koramangala'     => 'Koramangala, Bangalore',
        'indiranagar'     => 'Indiranagar, Bangalore',
        'whitefield'      => 'Whitefield, Bangalore',
        'hsr_layout'      => 'HSR Layout, Bangalore',
        'electronic_city' => 'Electronic City, Bangalore',
        'jp_nagar'        => 'JP Nagar, Bangalore',
        'delhi'           => 'Delhi',
        'noida'           => 'Noida',
        'gurgaon'         => 'Gurgaon',
        'mumbai'          => 'Mumbai',
        'pune'            => 'Pune',
        'hyderabad'       => 'Hyderabad',
        'chennai'         => 'Chennai',
        'kolkata'         => 'Kolkata',
        'ahmedabad'       => 'Ahmedabad',
        'belgaum'         => 'Belgaum',
    ];

    // ── Index ─────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $pageTitle = 'Events';

        $events = Event::withCount(['speakers','bookings'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('title', 'like', '%'.$request->search.'%'))
            ->orderBy('sort_order')->orderByDesc('created_at')
            ->paginate(getPaginate());

        $counts = [
            'all'      => Event::count(),
            'upcoming' => Event::upcoming()->count(),
            'ongoing'  => Event::ongoing()->count(),
            'past'     => Event::past()->count(),
        ];

        return view('admin.events.index', compact('pageTitle','events','counts'));
    }

    // ── Create ────────────────────────────────────────────────────────────
    public function create()
    {
        return view('admin.events.form', $this->viewData());
    }

    // ── Store ─────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Event::generateSlug($request->title);
        $this->handleThumbnail($request, $data);
        $this->handleVideo($request, $data);

        $event = Event::create($data);

        $this->syncSpeakers($request, $event);
        $this->saveGallery($request, $event);
        $this->saveNewFaqs($request, $event);

        $notify[] = ['success', 'Event created successfully'];
        return redirect()->route('admin.events.index')->withNotify($notify);
    }

    // ── Edit ──────────────────────────────────────────────────────────────
    public function edit(Event $event)
    {
        return view('admin.events.form', $this->viewData($event));
    }

    // ── Update ────────────────────────────────────────────────────────────
    public function update(Request $request, Event $event)
    {
        $data = $this->validated($request);
        $data['slug'] = Event::generateSlug($request->title, $event->id);
        $this->handleThumbnail($request, $data, $event);
        $this->handleVideo($request, $data, $event);

        $event->update($data);

        $this->syncSpeakers($request, $event);
        $this->saveGallery($request, $event);
        $this->saveNewFaqs($request, $event);

        $notify[] = ['success', 'Event updated successfully'];
        return redirect()->route('admin.events.index')->withNotify($notify);
    }

    // ── Destroy ───────────────────────────────────────────────────────────
    public function destroy(Event $event)
    {
        $this->deleteFile($event->thumbnail, 'assets/images/events/');
        foreach ($event->galleryItems as $item) {
            $this->deleteFile($item->image, 'assets/images/events/gallery/');
        }
        if ($event->video_type === 'upload') {
            $this->deleteFile($event->video_url, 'assets/videos/events/');
        }
        $event->delete();
        $notify[] = ['success', 'Event deleted'];
        return back()->withNotify($notify);
    }

    // ── Status Cycle ──────────────────────────────────────────────────────
    public function statusToggle(Event $event)
    {
        $cycle = ['upcoming'=>'ongoing','ongoing'=>'past','past'=>'upcoming','draft'=>'upcoming'];
        $event->update(['status' => $cycle[$event->status] ?? 'upcoming']);
        $notify[] = ['success', 'Status updated to '.$event->fresh()->status];
        return back()->withNotify($notify);
    }

    // ── Featured ──────────────────────────────────────────────────────────
    public function featuredToggle(Event $event)
    {
        $event->update(['is_featured' => !$event->is_featured]);
        $notify[] = ['success', 'Featured updated'];
        return back()->withNotify($notify);
    }

    // ── Booking Toggle ────────────────────────────────────────────────────
    public function bookingToggle(Event $event)
    {
        $event->update(['booking_open' => !$event->booking_open]);
        $notify[] = ['success', 'Booking '.($event->fresh()->booking_open ? 'opened' : 'closed')];
        return back()->withNotify($notify);
    }

    // ── Bookings List ─────────────────────────────────────────────────────
    public function bookings(Request $request)
    {
        $pageTitle = 'Event Bookings';
        $bookings  = EventBooking::with(['event','user'])
            ->when($request->event_id,       fn($q) => $q->where('event_id', $request->event_id))
            ->when($request->payment_status, fn($q) => $q->where('payment_status', $request->payment_status))
            ->when($request->search,         fn($q) => $q->where('email','like','%'.$request->search.'%')
                                                          ->orWhere('name','like','%'.$request->search.'%'))
            ->orderByDesc('created_at')
            ->paginate(getPaginate());

        $events  = Event::orderBy('title')->get(['id','title']);
        $summary = [
            'total'   => EventBooking::count(),
            'paid'    => EventBooking::where('payment_status','paid')->count(),
            'free'    => EventBooking::where('payment_type','free')->count(),
            'revenue' => EventBooking::where('payment_status','paid')->sum('amount'),
        ];

        return view('admin.events.bookings', compact('pageTitle','bookings','events','summary'));
    }

    // ── FAQ AJAX ──────────────────────────────────────────────────────────
    public function faqUpdate(Request $request, EventFaq $faq)
    {
        $request->validate(['question'=>'required|string','answer'=>'required|string']);
        $faq->update(['question'=>$request->question,'answer'=>$request->answer]);
        return response()->json(['success'=>true]);
    }
    public function faqDestroy(EventFaq $faq)
    {
        $faq->delete();
        return response()->json(['success'=>true]);
    }
    public function faqReorder(Request $request)
    {
        foreach (($request->order ?? []) as $i => $id) {
            EventFaq::where('id',$id)->update(['sort_order'=>$i]);
        }
        return response()->json(['success'=>true]);
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function viewData(?Event $event = null): array
    {
        return [
            'pageTitle'        => $event ? 'Edit: '.$event->title : 'Add Event',
            'event'            => $event,
            'employees'        => User::role('employee')->with('employeeProfile')->orderBy('firstname')->get(),
            'selectedSpeakers' => $event ? $event->speakers->pluck('id')->toArray() : [],
            'galleryItems'     => $event ? $event->galleryItems : collect(),
            'faqs'             => $event ? $event->faqs : collect(),
            'cities'           => self::CITIES,
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'                 => 'required|string|max:255',
            'badge'                 => 'required|in:symposium,workshop,seminar,bootcamp,conference,other',
            'status'                => 'required|in:upcoming,ongoing,past,draft',
            'type'                  => 'required|in:free,paid',
            'event_date'            => 'nullable|date',
            'event_time_start'      => 'nullable|string|max:8',
            'event_time_end'        => 'nullable|string|max:8',
            'duration_hours'        => 'nullable|integer|min:1|max:72',
            'location'              => 'nullable|string|max:255',
            'city'                  => 'nullable|string|max:50',
            'price'                 => 'nullable|integer|min:0',
            'mrp'                   => 'nullable|integer|min:0',
            'discount_percent'      => 'nullable|numeric|min:0|max:100',
            'total_seats'           => 'nullable|integer|min:1',
            'description'           => 'nullable|string',
            'tags'                  => 'nullable|string',
            'gallery_section_title' => 'nullable|string|max:100',
            'sort_order'            => 'nullable|integer|min:0',
            'is_featured'           => 'nullable|boolean',
        ]);

        // Tags
        $data['tags'] = !empty($data['tags'])
            ? array_values(array_filter(array_map('trim', explode(',', $data['tags']))))
            : [];

        // Pricing
        if ($request->type === 'free') {
            $data['price']            = 0;
            $data['discount_percent'] = 100;
            $data['discount_label']   = '100% off';
        } else {
            $mrp  = (int) ($request->mrp ?? 0);
            $disc = (float) ($request->discount_percent ?? 0);
            if ($mrp > 0 && $disc > 0) {
                $data['price']          = (int) round($mrp * (1 - $disc / 100));
                $data['discount_label'] = round($disc).'% off';
            } else {
                $data['price'] = (int) ($request->price ?? 0);
            }
        }

        $data['booking_open'] = $request->boolean('booking_open', true);
        $data['is_featured']  = $request->boolean('is_featured');
        $data['sort_order']   = $request->sort_order ?? 0;

        return $data;
    }

    private function handleThumbnail(Request $request, array &$data, ?Event $event = null): void
    {
        if ($request->hasFile('thumbnail')) {
            $this->deleteFile($event?->thumbnail, 'assets/images/events/');
            $file = $request->file('thumbnail');
            $name = 'event_'.time().'_'.Str::random(5).'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/images/events'), $name);
            $data['thumbnail'] = $name;
        } elseif ($event) {
            unset($data['thumbnail']);
        }
    }

    private function handleVideo(Request $request, array &$data, ?Event $event = null): void
    {
        if ($request->video_type === 'youtube') {
            $data['video_type'] = 'youtube';
            $data['video_url']  = $request->video_url_yt ?? null;
        } elseif ($request->video_type === 'upload' && $request->hasFile('video_file')) {
            if ($event && $event->video_type === 'upload') {
                $this->deleteFile($event->video_url, 'assets/videos/events/');
            }
            $file = $request->file('video_file');
            $name = 'evvid_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/videos/events'), $name);
            $data['video_type'] = 'upload';
            $data['video_url']  = $name;
        } elseif ($event) {
            unset($data['video_type'], $data['video_url']);
        }
    }

    private function syncSpeakers(Request $request, Event $event): void
    {
        $ids  = array_filter((array) $request->input('speaker_ids', []));
        $sync = [];
        foreach ($ids as $i => $id) { $sync[$id] = ['sort_order' => $i]; }
        $event->speakers()->sync($sync);
    }

    private function saveGallery(Request $request, Event $event): void
    {
        // Remove gallery items that were deleted
        $keptIds = array_filter((array) $request->input('gallery_keep_ids', []));
        $event->galleryItems()->whereNotIn('id', $keptIds)->each(function ($item) {
            $this->deleteFile($item->image, 'assets/images/events/gallery/');
            $item->delete();
        });

        // Update titles of kept items
        $keptTitles = (array) $request->input('gallery_kept_titles', []);
        foreach ($keptIds as $idx => $id) {
            EventGalleryItem::where('id', $id)->update([
                'title'      => $keptTitles[$idx] ?? null,
                'sort_order' => $idx,
            ]);
        }

        // Upload new images
        $newImages = $request->file('gallery_new_images', []);
        $newTitles = (array) $request->input('gallery_new_titles', []);
        $baseOrder = count($keptIds);

        foreach ($newImages as $idx => $file) {
            if (!$file || !$file->isValid()) continue;
            $name = 'gal_'.time().'_'.Str::random(4).'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/images/events/gallery'), $name);
            $event->galleryItems()->create([
                'image'      => $name,
                'title'      => $newTitles[$idx] ?? null,
                'sort_order' => $baseOrder + $idx,
            ]);
        }
    }

    private function saveNewFaqs(Request $request, Event $event): void
    {
        if (!$request->has('new_faqs')) return;
        $maxOrder = $event->faqs()->max('sort_order') ?? 0;
        foreach ($request->input('new_faqs', []) as $faq) {
            $q = trim($faq['question'] ?? '');
            $a = trim($faq['answer']   ?? '');
            if ($q && $a) {
                $event->faqs()->create(['question'=>$q,'answer'=>$a,'sort_order'=>++$maxOrder]);
            }
        }
    }

    private function deleteFile(?string $filename, string $basePath): void
    {
        if (!$filename || filter_var($filename, FILTER_VALIDATE_URL)) return;
        $path = public_path($basePath.$filename);
        if (file_exists($path)) unlink($path);
    }
}

<?php
// FILE: app/Http/Controllers/Admin/WebinarController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Webinar;
use App\Models\WebinarEnrollment;
use App\Models\WebinarFaq;
use App\Models\WebinarOrder;
use App\Models\WebinarTool;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebinarController extends Controller
{
    // ── Index ────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $pageTitle = 'Webinars';

        $webinars = Webinar::withCount('enrollments')
            ->when($request->status,   fn($q) => $q->where('status', $request->status))
            ->when($request->type,     fn($q) => $q->where('type', $request->type))
            ->when($request->language, fn($q) => $q->where('language', $request->language))
            ->when($request->search,   fn($q) => $q->where('title', 'like', '%' . $request->search . '%'))
            ->orderBy('sort_order')
            ->orderByDesc('webinar_date')
            ->paginate(getPaginate());

        $counts = [
            'all'      => Webinar::count(),
            'upcoming' => Webinar::upcoming()->count(),
            'live'     => Webinar::live()->count(),
            'past'     => Webinar::past()->count(),
        ];

        return view('admin.webinars.index', compact('pageTitle', 'webinars', 'counts'));
    }

    // ── Create ───────────────────────────────────────────────────────────────
    public function create()
    {
        $pageTitle        = 'Add Webinar';
        $employees        = $this->getEmployees();
        $selectedSpeakers = [];
        $faqs             = collect();
        $tools            = collect();
        return view('admin.webinars.form', compact('pageTitle', 'employees', 'selectedSpeakers', 'faqs', 'tools'));
    }

    // ── Store ────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Webinar::generateSlug($request->title);
        $this->handleThumbnail($request, $data);

        $webinar = Webinar::create($data);

        // Speakers
        $this->syncSpeakers($request, $webinar);

        // FAQs
        $this->saveNewFaqs($request, $webinar);

        // Tools
        $this->saveTools($request, $webinar);

        $notify[] = ['success', 'Webinar created successfully'];
        return redirect()->route('admin.webinars.index')->withNotify($notify);
    }

    // ── Edit ─────────────────────────────────────────────────────────────────
    public function edit(Webinar $webinar)
    {
        $pageTitle        = 'Edit Webinar: ' . $webinar->title;
        $employees        = $this->getEmployees();
        $selectedSpeakers = $webinar->speakers->pluck('id')->toArray();
        $faqs             = $webinar->faqs;
        $tools            = $webinar->tools;
        return view('admin.webinars.form', compact(
            'pageTitle', 'webinar', 'employees', 'selectedSpeakers', 'faqs', 'tools'
        ));
    }

    // ── Update ───────────────────────────────────────────────────────────────
    public function update(Request $request, Webinar $webinar)
    {
        $data = $this->validated($request);
        $data['slug'] = Webinar::generateSlug($request->title, $webinar->id);
        $this->handleThumbnail($request, $data, $webinar);

        $webinar->update($data);

        // Speakers
        $this->syncSpeakers($request, $webinar);

        // FAQs
        $this->saveNewFaqs($request, $webinar);

        // Tools (rebuild)
        $this->saveTools($request, $webinar);

        $notify[] = ['success', 'Webinar updated successfully'];
        return redirect()->route('admin.webinars.index')->withNotify($notify);
    }

    // ── Destroy ──────────────────────────────────────────────────────────────
    public function destroy(Webinar $webinar)
    {
        // Delete tool images
        foreach ($webinar->tools as $tool) {
            if ($tool->image && !filter_var($tool->image, FILTER_VALIDATE_URL)) {
                $path = public_path('assets/images/webinar/tools/' . $tool->image);
                if (file_exists($path)) unlink($path);
            }
        }
        $webinar->delete();
        $notify[] = ['success', 'Webinar deleted'];
        return back()->withNotify($notify);
    }

    // ── Status Cycle ─────────────────────────────────────────────────────────
    public function statusToggle(Webinar $webinar)
    {
        $cycle = ['upcoming' => 'live', 'live' => 'past', 'past' => 'upcoming'];
        $webinar->update(['status' => $cycle[$webinar->status]]);
        $notify[] = ['success', 'Status updated to ' . $webinar->fresh()->status];
        return back()->withNotify($notify);
    }

    // ── Featured Toggle ──────────────────────────────────────────────────────
    public function featuredToggle(Webinar $webinar)
    {
        $webinar->update(['is_featured' => !$webinar->is_featured]);
        $notify[] = ['success', 'Featured status updated'];
        return back()->withNotify($notify);
    }

    // ── FAQ AJAX endpoints ───────────────────────────────────────────────────
    public function faqUpdate(Request $request, WebinarFaq $faq)
    {
        $request->validate(['question' => 'required|string', 'answer' => 'required|string']);
        $faq->update(['question' => $request->question, 'answer' => $request->answer]);
        return response()->json(['success' => true]);
    }

    public function faqDestroy(WebinarFaq $faq)
    {
        $faq->delete();
        return response()->json(['success' => true]);
    }

    public function faqReorder(Request $request)
    {
        foreach (($request->order ?? []) as $i => $id) {
            WebinarFaq::where('id', $id)->update(['sort_order' => $i]);
        }
        return response()->json(['success' => true]);
    }

    // ── Enrollments list ─────────────────────────────────────────────────────
    public function enrollments(Request $request)
    {
        $pageTitle   = 'Webinar Enrollments';
        $enrollments = WebinarEnrollment::with(['webinar', 'user', 'order'])
            ->when($request->webinar_id, fn($q) => $q->where('webinar_id', $request->webinar_id))
            ->when($request->search,     fn($q) => $q->whereHas('user', fn($u) => $u->where('email', 'like', '%' . $request->search . '%')))
            ->orderByDesc('enrolled_at')
            ->paginate(getPaginate());
        $webinars = Webinar::orderBy('title')->get(['id', 'title']);
        return view('admin.webinars.enrollments', compact('pageTitle', 'enrollments', 'webinars'));
    }

    // ── Orders list ──────────────────────────────────────────────────────────
    public function orders(Request $request)
    {
        $pageTitle = 'Webinar Orders';
        $orders = WebinarOrder::with(['user', 'webinar'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('order_number', 'like', '%' . $request->search . '%'))
            ->orderByDesc('created_at')
            ->paginate(getPaginate());

        $summary = [
            'revenue'  => WebinarOrder::where('status', 'paid')->sum('amount'),
            'total'    => WebinarOrder::count(),
            'paid'     => WebinarOrder::where('status', 'paid')->count(),
            'pending'  => WebinarOrder::where('status', 'pending')->count(),
        ];
        return view('admin.webinars.orders', compact('pageTitle', 'orders', 'summary'));
    }

    // ── Manual Enroll ────────────────────────────────────────────────────────
    public function manualEnroll(WebinarOrder $order)
    {
        if ($order->isPaid()) {
            $notify[] = ['error', 'Order is already paid and enrolled.'];
            return back()->withNotify($notify);
        }
        $order->update(['status' => 'paid', 'paid_at' => now()]);
        WebinarEnrollment::updateOrCreate(
            ['user_id' => $order->user_id, 'webinar_id' => $order->webinar_id],
            ['webinar_order_id' => $order->id, 'access_type' => 'manual', 'enrolled_at' => now(), 'status' => 1]
        );
        $order->webinar->increment('total_enrolled');
        $notify[] = ['success', 'User enrolled manually.'];
        return back()->withNotify($notify);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        $rules = [
            'title'            => 'required|string|max:255',
            'status'           => 'required|in:upcoming,live,past',
            'type'             => 'required|in:free,paid',
            'mode'             => 'required|in:online,offline,hybrid',
            'address'          => 'nullable|string|max:500',
            'language'         => 'required|string|max:50',
            'level'            => 'required|string|max:50',
            'webinar_date'     => 'nullable|date',
            'duration'         => 'nullable|string|max:50',
            'total_seats'      => 'nullable|integer|min:1',
            'price'            => 'nullable|integer|min:0',
            'mrp'              => 'nullable|integer|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_label'   => 'nullable|string|max:50',
            'youtube_url'      => 'nullable|string|max:500',
            'sort_order'       => 'nullable|integer|min:0',
            'is_featured'      => 'nullable|boolean',
        ];

        $data = $request->validate($rules);

        // Auto-compute price for paid type
        if ($request->type === 'paid') {
            $mrp  = (int) ($request->mrp ?? 0);
            $disc = (float) ($request->discount_percent ?? 0);
            if ($mrp > 0 && $disc > 0) {
                $data['price'] = (int) round($mrp * (1 - $disc / 100));
                $data['discount_label'] = round($disc) . '% off';
            } else {
                $data['price'] = (int) ($request->price ?? 0);
            }
        } else {
            // Free webinar
            $data['price']            = 0;
            $data['discount_percent'] = 100;
            $data['discount_label']   = '100% off';
        }

        $data['sort_order'] = $request->sort_order ?? 0;

        return $data;
    }

    private function handleThumbnail(Request $request, array &$data, ?Webinar $webinar = null): void
    {
        if ($request->hasFile('thumbnail')) {
            if ($webinar && $webinar->thumbnail && !filter_var($webinar->thumbnail, FILTER_VALIDATE_URL)) {
                $old = public_path('assets/images/webinar/' . $webinar->thumbnail);
                if (file_exists($old)) unlink($old);
            }
            $file = $request->file('thumbnail');
            $name = 'webinar_' . time() . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('assets/images/webinar'), $name);
            $data['thumbnail'] = $name;
        } elseif ($webinar) {
            unset($data['thumbnail']);
        }
    }

    private function syncSpeakers(Request $request, Webinar $webinar): void
    {
        $ids  = array_filter((array) $request->input('speaker_ids', []));
        $sync = [];
        foreach ($ids as $i => $id) {
            $sync[$id] = ['sort_order' => $i];
        }
        $webinar->speakers()->sync($sync);
    }

    private function saveNewFaqs(Request $request, Webinar $webinar): void
    {
        if (!$request->has('new_faqs')) return;
        $maxOrder = $webinar->faqs()->max('sort_order') ?? 0;
        foreach ($request->input('new_faqs', []) as $faq) {
            $q = trim($faq['question'] ?? '');
            $a = trim($faq['answer']   ?? '');
            if ($q && $a) {
                $webinar->faqs()->create(['question' => $q, 'answer' => $a, 'sort_order' => ++$maxOrder]);
            }
        }
    }

    private function saveTools(Request $request, Webinar $webinar): void
    {
        // We rebuild tools on save. First delete old local images if replacing.
        $existingTools = $webinar->tools()->get()->keyBy('id');

        $submittedIds = $request->input('tools_ids', []);
        $titles       = $request->input('tools_title', []);
        $descriptions = $request->input('tools_description', []);
        $toolImages   = $request->file('tools_image', []);

        // Delete tools that were removed
        $removedIds = array_diff($existingTools->keys()->toArray(), array_filter($submittedIds));
        foreach ($removedIds as $rid) {
            $tool = $existingTools[$rid] ?? null;
            if ($tool) {
                if ($tool->image && !filter_var($tool->image, FILTER_VALIDATE_URL)) {
                    $path = public_path('assets/images/webinar/tools/' . $tool->image);
                    if (file_exists($path)) unlink($path);
                }
                $tool->delete();
            }
        }

        foreach ($titles as $i => $title) {
            $title = trim($title);
            if (!$title) continue;

            $toolId  = $submittedIds[$i] ?? null;
            $toolImg = null;

            if (isset($toolImages[$i])) {
                $file    = $toolImages[$i];
                $imgName = 'tool_' . time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/images/webinar/tools'), $imgName);
                $toolImg = $imgName;
            }

            $toolData = [
                'title'       => $title,
                'description' => trim($descriptions[$i] ?? ''),
                'sort_order'  => $i,
            ];
            if ($toolImg) $toolData['image'] = $toolImg;

            if ($toolId && isset($existingTools[$toolId])) {
                $existingTools[$toolId]->update($toolData);
            } else {
                $webinar->tools()->create($toolData);
            }
        }
    }

    private function getEmployees()
    {
        return User::role('employee')
            ->with('employeeProfile')
            ->orderBy('firstname')
            ->get();
    }
}

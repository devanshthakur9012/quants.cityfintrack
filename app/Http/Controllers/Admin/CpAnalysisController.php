<?php
// FILE: app/Http/Controllers/Admin/CpAnalysisController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CpAnalysis;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CpAnalysisController extends Controller
{
    public function index()
    {
        $pageTitle = 'Analysis Tools';
        $analyses  = CpAnalysis::orderBy('sort_order')->orderBy('name')->paginate(25);

        return view('admin.cp.analyses.index', compact('pageTitle', 'analyses'));
    }

    // AJAX — load full data for edit modal
    public function getData(CpAnalysis $analysis)
    {
        return response()->json([
            'description' => $analysis->description,
            'faqs'        => $analysis->faqs ?? [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:200',
            'route_name'        => 'nullable|string|max:200',
            'short_description' => 'nullable|string|max:500',
            'description'       => 'nullable|string',
            'plan_tier'         => 'required|in:free,pro,pro_plus',
            'data_source'       => 'required|in:option,fut,stock,mixed',
            'is_active'         => 'nullable',
            'is_featured'       => 'nullable',
            'sort_order'        => 'nullable|integer|min:0',
            'tags'              => 'nullable|string',
        ]);

        if ($request->hasFile('thumbnail')) {
            $file     = $request->file('thumbnail');
            $filename = 'analysis_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/analyses'), $filename);
            $data['thumbnail'] = $filename;
        }

        $data['faqs']       = $this->parseFaqs($request);
        $data['tags']       = $this->parseTags($request->tags ?? '');
        $data['slug']       = $this->uniqueSlug($data['name']);
        $data['is_active']  = $request->boolean('is_active', true);
        $data['is_featured']= $request->boolean('is_featured', false);
        $data['sort_order'] = (int) ($request->sort_order ?? 0);

        try {
            CpAnalysis::create($data);
            $notify[] = ['success', 'Analysis created!'];
            return redirect()->route('admin.cp.analyses.index')->withNotify($notify);
        } catch (\Exception $e) {
            Log::error('CpAnalysis Store: ' . $e->getMessage());
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function update(Request $request, CpAnalysis $analysis)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:200',
            'route_name'        => 'nullable|string|max:200',
            'short_description' => 'nullable|string|max:500',
            'description'       => 'nullable|string',
            'plan_tier'         => 'required|in:free,pro,pro_plus',
            'data_source'       => 'required|in:option,fut,stock,mixed',
            'is_active'         => 'nullable',
            'is_featured'       => 'nullable',
            'sort_order'        => 'nullable|integer|min:0',
            'tags'              => 'nullable|string',
        ]);

        if ($request->hasFile('thumbnail')) {
            if ($analysis->thumbnail) {
                @unlink(public_path('assets/images/cms/analyses/' . $analysis->thumbnail));
            }
            $file     = $request->file('thumbnail');
            $filename = 'analysis_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/analyses'), $filename);
            $data['thumbnail'] = $filename;
        }

        $data['faqs']       = $this->parseFaqs($request);
        $data['tags']       = $this->parseTags($request->tags ?? '');
        $data['slug']       = $this->uniqueSlug($data['name'], $analysis->id);
        $data['is_active']  = $request->boolean('is_active', true);
        $data['is_featured']= $request->boolean('is_featured', false);
        $data['sort_order'] = (int) ($request->sort_order ?? 0);

        try {
            $analysis->update($data);
            $notify[] = ['success', 'Analysis updated!'];
            return redirect()->route('admin.cp.analyses.index')->withNotify($notify);
        } catch (\Exception $e) {
            Log::error('CpAnalysis Update: ' . $e->getMessage());
            $notify[] = ['error', $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function destroy(CpAnalysis $analysis)
    {
        if ($analysis->thumbnail) {
            @unlink(public_path('assets/images/cms/analyses/' . $analysis->thumbnail));
        }
        $analysis->plans()->detach();
        $analysis->delete();

        $notify[] = ['success', 'Analysis deleted.'];
        return redirect()->route('admin.cp.analyses.index')->withNotify($notify);
    }

    public function toggleStatus(CpAnalysis $analysis)
    {
        $analysis->update(['is_active' => !$analysis->is_active]);
        $notify[] = ['success', 'Status updated.'];
        return back()->withNotify($notify);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function parseFaqs(Request $request): array
    {
        $questions = $request->input('faq_question', []);
        $answers   = $request->input('faq_answer',   []);
        $faqs      = [];
        foreach ($questions as $i => $q) {
            $q = trim($q);
            $a = trim($answers[$i] ?? '');
            if ($q && $a) $faqs[] = ['question' => $q, 'answer' => $a];
        }
        return $faqs;
    }

    private function parseTags(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $q    = CpAnalysis::where('slug', $slug);
        if ($excludeId) $q->where('id', '!=', $excludeId);
        if (!$q->exists()) return $slug;
        return $slug . '-' . time();
    }
}
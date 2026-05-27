<?php
// FILE: app/Http/Controllers/Admin/PagesCmsController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaPageCms;
use App\Models\WebinarPageCms;
use App\Models\CoursePageCms;
use App\Models\EventPageCms;
use App\Models\AuthPageCms;
use Illuminate\Http\Request;

class PagesCmsController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    // INDEX — Navigation hub
    // ══════════════════════════════════════════════════════════════════════

    public function index()
    {
        $pageTitle = 'Pages CMS';
        return view('admin.cms.pages.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════
    // MEDIA PAGE
    // ══════════════════════════════════════════════════════════════════════

    public function mediaIndex()
    {
        $pageTitle = 'Media Page CMS';
        $cms       = MediaPageCms::getData();
        return view('admin.cms.pages.media', compact('pageTitle', 'cms'));
    }

    public function mediaUpdate(Request $request)
    {
        $data = $request->validate([
            'hero_eyebrow'         => 'nullable|string|max:100',
            'hero_title'           => 'nullable|string|max:200',
            'hero_title_highlight' => 'nullable|string|max:100',
            'hero_subtitle'        => 'nullable|string',
            'cta_title'            => 'nullable|string|max:200',
            'cta_description'      => 'nullable|string',
            'cta_email'            => 'nullable|email|max:150',
            'cta_btn_label'        => 'nullable|string|max:80',
        ]);
        MediaPageCms::updateOrCreate(['id' => 1], $data);
        $notify[] = ['success', 'Media page updated'];
        return back()->withNotify($notify);
    }

    // ══════════════════════════════════════════════════════════════════════
    // WEBINAR PAGE
    // ══════════════════════════════════════════════════════════════════════

    public function webinarIndex()
    {
        $pageTitle = 'Webinar Page CMS';
        $cms       = WebinarPageCms::getData();
        return view('admin.cms.pages.webinar', compact('pageTitle', 'cms'));
    }

    public function webinarUpdate(Request $request)
    {
        $data = $request->validate([
            'hero_title'            => 'nullable|string|max:200',
            'hero_description'      => 'nullable|string',
            'hero_illustration_url' => 'nullable|string|max:500',
        ]);

        $data['languages']          = $this->parseLines($request->input('languages_text', ''));
        $data['proficiency_levels'] = $this->parseLines($request->input('proficiency_text', ''));

        if ($request->hasFile('hero_illustration_file')) {
            $file = $request->file('hero_illustration_file');
            $name = 'webinar_illus_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/pages'), $name);
            $data['hero_illustration_url'] = asset('assets/images/cms/pages/' . $name);
        }

        WebinarPageCms::updateOrCreate(['id' => 1], $data);
        $notify[] = ['success', 'Webinar page updated'];
        return back()->withNotify($notify);
    }

    // ══════════════════════════════════════════════════════════════════════
    // COURSES PAGE
    // ══════════════════════════════════════════════════════════════════════

    public function courseIndex()
    {
        $pageTitle = 'Courses Page CMS';
        $cms       = CoursePageCms::getData();
        return view('admin.cms.pages.course', compact('pageTitle', 'cms'));
    }

    public function courseUpdate(Request $request)
    {
        $data = $request->validate([
            'hero_title'       => 'nullable|string|max:200',
            'hero_description' => 'nullable|string',
        ]);

        $data['languages'] = $this->parseLines($request->input('languages_text', ''));
        $data['levels']    = $this->parseLines($request->input('levels_text', ''));
        $data['modes']     = $this->parseLines($request->input('modes_text', ''));

        $existingBanners = CoursePageCms::getData()->hero_banners ?? [];
        $newBanners      = $existingBanners;

        foreach ([1, 2, 3, 4] as $i) {
            if ($request->hasFile('banner_' . $i)) {
                $file = $request->file('banner_' . $i);
                $name = 'course_banner_' . $i . '_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/images/cms/course_banners'), $name);
                $newBanners[$i - 1] = $name;
            }
        }

        $newBanners = array_values(array_filter($newBanners));
        $data['hero_banners'] = !empty($newBanners) ? $newBanners : null;

        CoursePageCms::updateOrCreate(['id' => 1], $data);
        $notify[] = ['success', 'Courses page updated'];
        return back()->withNotify($notify);
    }

    // ══════════════════════════════════════════════════════════════════════
    // EVENTS PAGE
    // ══════════════════════════════════════════════════════════════════════

    public function eventIndex()
    {
        $pageTitle = 'Events Page CMS';
        $cms       = EventPageCms::getData();
        return view('admin.cms.pages.event', compact('pageTitle', 'cms'));
    }

    public function eventUpdate(Request $request)
    {
        $data = $request->validate([
            'hero_eyebrow'         => 'nullable|string|max:100',
            'hero_title'           => 'nullable|string|max:200',
            'hero_title_highlight' => 'nullable|string|max:100',
            'hero_subtitle'        => 'nullable|string',
            'cta_title'            => 'nullable|string|max:200',
            'cta_description'      => 'nullable|string',
            'cta_btn_label'        => 'nullable|string|max:80',
            'cta_btn_url'          => 'nullable|string|max:300',
        ]);

        $citiesRaw = $request->input('cities_text', '');
        $cities    = [];
        foreach (explode("\n", $citiesRaw) as $line) {
            $line = trim($line);
            if (!$line) continue;
            if (str_contains($line, '|')) {
                [$key, $label] = explode('|', $line, 2);
                $cities[] = ['key' => trim($key), 'label' => trim($label)];
            } else {
                $slug     = strtolower(str_replace(' ', '_', $line));
                $cities[] = ['key' => $slug, 'label' => trim($line)];
            }
        }
        $data['cities'] = !empty($cities) ? $cities : null;

        EventPageCms::updateOrCreate(['id' => 1], $data);
        $notify[] = ['success', 'Events page updated'];
        return back()->withNotify($notify);
    }

    // ══════════════════════════════════════════════════════════════════════
    // AUTH PAGE (Login / Register)
    // ══════════════════════════════════════════════════════════════════════

    public function authIndex()
    {
        $pageTitle = 'Login & Register Page CMS';
        $cms       = AuthPageCms::getData();
        return view('admin.cms.pages.auth', compact('pageTitle', 'cms'));
    }

    public function authUpdate(Request $request)
    {
        $data = $request->validate([
            'promo_video_url'     => 'nullable|string|max:500',
            'login_heading'       => 'nullable|string|max:150',
            'login_subheading'    => 'nullable|string|max:300',
            'register_heading'    => 'nullable|string|max:150',
            'register_subheading' => 'nullable|string|max:300',
        ]);

        $data['features'] = $this->parseLines($request->input('features_text', ''));

        $brokerNames   = $request->input('broker_name',   []);
        $brokerLetters = $request->input('broker_letter', []);
        $brokerBgs     = $request->input('broker_bg',     []);
        $brokers       = [];
        foreach ($brokerNames as $i => $name) {
            $name = trim($name);
            if (!$name) continue;
            $brokers[] = [
                'name'   => $name,
                'letter' => strtoupper(trim($brokerLetters[$i] ?? $name[0])),
                'bg'     => trim($brokerBgs[$i] ?? '#455a64'),
            ];
        }
        $data['brokers'] = !empty($brokers) ? $brokers : null;

        AuthPageCms::updateOrCreate(['id' => 1], $data);
        $notify[] = ['success', 'Auth pages updated'];
        return back()->withNotify($notify);
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function parseLines(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $raw))));
    }
}
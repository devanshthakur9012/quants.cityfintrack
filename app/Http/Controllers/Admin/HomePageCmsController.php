<?php
// FILE: app/Http/Controllers/Admin/HomePageCmsController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeCertSlide;
use App\Models\HomeAbout;
use App\Models\HomeAboutStat;
use App\Models\HomeFeatureTool;
use App\Models\HomeFeatureUtility;
use App\Models\HomeHero;
use App\Models\HomeLearningTab;
use App\Models\HomePlatform;
use App\Models\HomeTestimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HomePageCmsController extends Controller
{
    // ── Dashboard / Nav ────────────────────────────────────────────────────
    public function index()
    {
        $pageTitle = 'Home Page Management';
        return view('admin.cms.home.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════
    // HERO
    // ══════════════════════════════════════════════════════════════════════
    public function hero()
    {
        $pageTitle = 'Hero Section';
        $hero = HomeHero::first() ?? new HomeHero();
        return view('admin.cms.home.hero', compact('pageTitle', 'hero'));
    }

    public function heroUpdate(Request $request)
    {
        $data = $request->validate([
            'heading_line1'     => 'nullable|string|max:100',
            'heading_highlight' => 'nullable|string|max:100',
            'heading_line2'     => 'nullable|string|max:100',
        ]);

        if ($request->hasFile('video_file')) {
            $file = $request->file('video_file');
            $name = 'hero_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/video'), $name);
            $data['video_file'] = $name;
        }

        HomeHero::updateOrCreate(['id' => 1], $data);
        $notify[] = ['success', 'Hero section updated'];
        return back()->withNotify($notify);
    }

    // ══════════════════════════════════════════════════════════════════════
    // PLATFORM BANNER
    // ══════════════════════════════════════════════════════════════════════
    public function platform()
    {
        $pageTitle = 'Platform Banner';
        $platform  = HomePlatform::first() ?? new HomePlatform();
        return view('admin.cms.home.platform', compact('pageTitle', 'platform'));
    }

    public function platformUpdate(Request $request)
    {
        $data = $request->validate(['title' => 'required|string|max:255', 'subtitle' => 'nullable|string|max:255']);
        HomePlatform::updateOrCreate(['id' => 1], $data);
        $notify[] = ['success', 'Platform banner updated'];
        return back()->withNotify($notify);
    }

    // ══════════════════════════════════════════════════════════════════════
    // CERT SLIDES
    // ══════════════════════════════════════════════════════════════════════
    public function certSlides()
    {
        $pageTitle = 'Certification Slider';
        $slides    = HomeCertSlide::orderBy('sort_order')->get();
        return view('admin.cms.home.cert_slides', compact('pageTitle', 'slides'));
    }

    public function certSlideStore(Request $request)
    {
        $data = $request->validate([
            'badge_text' => 'nullable|string|max:100',
            'language'   => 'nullable|string|max:50',
        ]);
        $data['sort_order'] = HomeCertSlide::max('sort_order') + 1;

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $name = 'cert_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/cert'), $name);
            $data['image'] = $name;
        }

        HomeCertSlide::create($data);
        $notify[] = ['success', 'Slide added'];
        return back()->withNotify($notify);
    }

    public function certSlideUpdate(Request $request, HomeCertSlide $slide)
    {
        $data = $request->validate([
            'badge_text' => 'nullable|string|max:100',
            'language'   => 'nullable|string|max:50',
            'status'     => 'required|in:0,1',
        ]);

        if ($request->hasFile('image')) {
            if ($slide->image) @unlink(public_path('assets/images/cms/cert/'.$slide->image));
            $file = $request->file('image');
            $name = 'cert_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/cert'), $name);
            $data['image'] = $name;
        }

        $slide->update($data);
        $notify[] = ['success', 'Slide updated'];
        return back()->withNotify($notify);
    }

    public function certSlideDestroy(HomeCertSlide $slide)
    {
        if ($slide->image) @unlink(public_path('assets/images/cms/cert/'.$slide->image));
        $slide->delete();
        $notify[] = ['success', 'Slide deleted'];
        return back()->withNotify($notify);
    }

    // ══════════════════════════════════════════════════════════════════════
    // ABOUT SECTION
    // ══════════════════════════════════════════════════════════════════════
    public function aboutSection()
    {
        $pageTitle = 'About the App Section';
        $about = HomeAbout::first() ?? new HomeAbout();
        $stats = HomeAboutStat::orderBy('sort_order')->get();
        return view('admin.cms.home.about_section', compact('pageTitle', 'about', 'stats'));
    }

    public function aboutSectionUpdate(Request $request)
    {
        $data = $request->validate([
            'section_heading' => 'nullable|string|max:255',
            'video_type'      => 'required|in:youtube,upload',
            'video_url'       => 'nullable|string',
        ]);

        $about = HomeAbout::first() ?? new HomeAbout(['id' => 1]);

        if ($request->video_type === 'upload' && $request->hasFile('video_file')) {
            if ($about->video_url && $about->video_type === 'upload') {
                @unlink(public_path('assets/videos/cms/'.$about->video_url));
            }
            $file = $request->file('video_file');
            $name = 'about_vid_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/videos/cms'), $name);
            $data['video_url'] = $name;
        } elseif ($request->video_type === 'youtube') {
            $data['video_url'] = $request->video_url;
        }

        HomeAbout::updateOrCreate(['id' => 1], $data);

        // Stats — rebuild
        if ($request->has('stat_value')) {
            HomeAboutStat::truncate();
            foreach ($request->stat_value as $i => $val) {
                $val = trim($val);
                if (!$val) continue;
                HomeAboutStat::create([
                    'value'      => $val,
                    'label'      => $request->stat_label[$i] ?? '',
                    'sub'        => $request->stat_sub[$i] ?? '',
                    'sort_order' => $i,
                ]);
            }
        }

        $notify[] = ['success', 'About section updated'];
        return back()->withNotify($notify);
    }

    // ══════════════════════════════════════════════════════════════════════
    // FEATURE TOOLS
    // ══════════════════════════════════════════════════════════════════════
    public function features()
    {
        $pageTitle   = 'Feature Tools';
        $featureMeta = HomeFeatureTool::first() ?? new HomeFeatureTool();
        $utilities   = HomeFeatureUtility::orderBy('sort_order')->get();
        return view('admin.cms.home.features', compact('pageTitle', 'featureMeta', 'utilities'));
    }

    public function featuresMetaUpdate(Request $request)
    {
        $data = $request->validate(['section_title' => 'required|string|max:255']);
        HomeFeatureTool::updateOrCreate(['id' => 1], $data);
        $notify[] = ['success', 'Section title updated'];
        return back()->withNotify($notify);
    }

    public function utilityStore(Request $request)
    {
        $data = $request->validate([
            'count'      => 'required|string|max:20',
            'label'      => 'required|string|max:50',
            'tool_title' => 'required|string|max:100',
            'tool_icon'  => 'nullable|string|max:50',
        ]);
        $data['tool_points'] = $this->parsePoints($request->tool_points ?? '');
        $data['sort_order']  = HomeFeatureUtility::max('sort_order') + 1;
        HomeFeatureUtility::create($data);
        $notify[] = ['success', 'Utility added'];
        return back()->withNotify($notify);
    }

    public function utilityUpdate(Request $request, HomeFeatureUtility $utility)
    {
        $data = $request->validate([
            'count'      => 'required|string|max:20',
            'label'      => 'required|string|max:50',
            'tool_title' => 'required|string|max:100',
            'tool_icon'  => 'nullable|string|max:50',
            'status'     => 'required|in:0,1',
        ]);
        $data['tool_points'] = $this->parsePoints($request->tool_points ?? '');
        $utility->update($data);
        $notify[] = ['success', 'Utility updated'];
        return back()->withNotify($notify);
    }

    public function utilityDestroy(HomeFeatureUtility $utility)
    {
        $utility->delete();
        $notify[] = ['success', 'Utility deleted'];
        return back()->withNotify($notify);
    }

    // ══════════════════════════════════════════════════════════════════════
    // LEARNING TABS
    // ══════════════════════════════════════════════════════════════════════
    public function learning()
    {
        $pageTitle = 'Learning Section';
        $tabs      = HomeLearningTab::orderBy('sort_order')->get();
        return view('admin.cms.home.learning', compact('pageTitle', 'tabs'));
    }

    public function learningTabStore(Request $request)
    {
        $data = $request->validate([
            'tab_label'      => 'required|string|max:50',
            'highlight_text' => 'nullable|string|max:100',
            'description'    => 'nullable|string',
            'btn_label'      => 'nullable|string|max:50',
            'btn_url'        => 'nullable|string|max:255',
            'video_id'       => 'nullable|string|max:20',
            'video_title'    => 'nullable|string|max:200',
            'video_sub'      => 'nullable|string|max:50',
            'video_date'     => 'nullable|string|max:50',
            'video_time'     => 'nullable|string|max:20',
        ]);
        $data['sort_order'] = HomeLearningTab::max('sort_order') + 1;
        HomeLearningTab::create($data);
        $notify[] = ['success', 'Tab added'];
        return back()->withNotify($notify);
    }

    public function learningTabUpdate(Request $request, HomeLearningTab $tab)
    {
        $data = $request->validate([
            'tab_label'      => 'required|string|max:50',
            'highlight_text' => 'nullable|string|max:100',
            'description'    => 'nullable|string',
            'btn_label'      => 'nullable|string|max:50',
            'btn_url'        => 'nullable|string|max:255',
            'video_id'       => 'nullable|string|max:20',
            'video_title'    => 'nullable|string|max:200',
            'video_sub'      => 'nullable|string|max:50',
            'video_date'     => 'nullable|string|max:50',
            'video_time'     => 'nullable|string|max:20',
            'status'         => 'required|in:0,1',
        ]);
        $tab->update($data);
        $notify[] = ['success', 'Tab updated'];
        return back()->withNotify($notify);
    }

    public function learningTabDestroy(HomeLearningTab $tab)
    {
        $tab->delete();
        $notify[] = ['success', 'Tab deleted'];
        return back()->withNotify($notify);
    }

    // ══════════════════════════════════════════════════════════════════════
    // TESTIMONIALS
    // ══════════════════════════════════════════════════════════════════════
    public function testimonials()
    {
        $pageTitle    = 'Testimonials';
        $testimonials = HomeTestimonial::orderBy('sort_order')->get();
        return view('admin.cms.home.testimonials', compact('pageTitle', 'testimonials'));
    }

    public function testimonialStore(Request $request)
    {
        $data = $request->validate([
            'name'   => 'required|string|max:100',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'required|string',
        ]);
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $name = 'testi_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/testimonials'), $name);
            $data['avatar'] = $name;
        }
        $data['sort_order'] = HomeTestimonial::max('sort_order') + 1;
        HomeTestimonial::create($data);
        $notify[] = ['success', 'Testimonial added'];
        return back()->withNotify($notify);
    }

    public function testimonialUpdate(Request $request, HomeTestimonial $testimonial)
    {
        $data = $request->validate([
            'name'   => 'required|string|max:100',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'required|string',
            'status' => 'required|in:0,1',
        ]);
        if ($request->hasFile('avatar')) {
            if ($testimonial->avatar) @unlink(public_path('assets/images/cms/testimonials/'.$testimonial->avatar));
            $file = $request->file('avatar');
            $name = 'testi_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/testimonials'), $name);
            $data['avatar'] = $name;
        }
        $testimonial->update($data);
        $notify[] = ['success', 'Testimonial updated'];
        return back()->withNotify($notify);
    }

    public function testimonialDestroy(HomeTestimonial $testimonial)
    {
        if ($testimonial->avatar) @unlink(public_path('assets/images/cms/testimonials/'.$testimonial->avatar));
        $testimonial->delete();
        $notify[] = ['success', 'Testimonial deleted'];
        return back()->withNotify($notify);
    }

    // ── Private ────────────────────────────────────────────────────────────
    private function parsePoints(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $raw))));
    }
}

<?php
// FILE: app/Http/Controllers/Admin/AboutPageCmsController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AboutCta;
use App\Models\AboutFounder;
use App\Models\AboutFounderVision;
use App\Models\AboutHero;
use App\Models\AboutMission;
use App\Models\AboutOffice;
use App\Models\AboutWhoweare;
use App\Models\AboutWorkspace;
use App\Models\AboutWorkspaceSlide;
use Illuminate\Http\Request;

class AboutPageCmsController extends Controller
{
    public function index()
    {
        $pageTitle = 'About Page Management';
        return view('admin.cms.about.index', compact('pageTitle'));
    }

    // ── Hero ──────────────────────────────────────────────────────────────
    public function hero()
    {
        $pageTitle = 'About Hero';
        $hero = AboutHero::first() ?? new AboutHero();
        return view('admin.cms.about.hero', compact('pageTitle', 'hero'));
    }

    public function heroUpdate(Request $request)
    {
        $data = $request->validate([
            'tagline'    => 'nullable|string|max:255',
            'subtitle'   => 'nullable|string|max:255',
            'founded'    => 'nullable|string|max:20',
            'hq'         => 'nullable|string|max:100',
            'users'      => 'nullable|string|max:50',
            'experience' => 'nullable|string|max:50',
            'stat1_value'=> 'nullable|string|max:50', 'stat1_label'=> 'nullable|string|max:100',
            'stat2_value'=> 'nullable|string|max:50', 'stat2_label'=> 'nullable|string|max:100',
            'stat3_value'=> 'nullable|string|max:50', 'stat3_label'=> 'nullable|string|max:100',
            'stat4_value'=> 'nullable|string|max:50', 'stat4_label'=> 'nullable|string|max:100',
        ]);
        AboutHero::updateOrCreate(['id' => 1], $data);
        $notify[] = ['success', 'About hero updated'];
        return back()->withNotify($notify);
    }

    // ── Who We Are ───────────────────────────────────────────────────────
    public function whoWeAre()
    {
        $pageTitle = 'Who We Are';
        $data = AboutWhoweare::first() ?? new AboutWhoweare();
        return view('admin.cms.about.who_we_are', compact('pageTitle', 'data'));
    }

    public function whoWeAreUpdate(Request $request)
    {
        $validated = $request->validate(['heading' => 'nullable|string|max:255', 'body' => 'nullable|string']);
        // Parse pillars
        $pillars = [];
        foreach (($request->pillar_icon ?? []) as $i => $icon) {
            $label = trim($request->pillar_label[$i] ?? '');
            if ($label) $pillars[] = ['icon' => trim($icon), 'label' => $label];
        }
        $validated['pillars'] = $pillars;
        AboutWhoweare::updateOrCreate(['id' => 1], $validated);
        $notify[] = ['success', 'Who We Are updated'];
        return back()->withNotify($notify);
    }

    // ── Mission ───────────────────────────────────────────────────────────
    public function mission()
    {
        $pageTitle = 'Mission & Vision';
        $data = AboutMission::first() ?? new AboutMission();
        return view('admin.cms.about.mission', compact('pageTitle', 'data'));
    }

    public function missionUpdate(Request $request)
    {
        $validated = $request->validate(['heading' => 'nullable|string|max:255', 'body' => 'nullable|string']);
        $values = [];
        foreach (($request->value_icon ?? []) as $i => $icon) {
            $label = trim($request->value_label[$i] ?? '');
            $desc  = trim($request->value_desc[$i]  ?? '');
            if ($label) $values[] = ['icon' => trim($icon), 'label' => $label, 'desc' => $desc];
        }
        $validated['values'] = $values;
        AboutMission::updateOrCreate(['id' => 1], $validated);
        $notify[] = ['success', 'Mission updated'];
        return back()->withNotify($notify);
    }

    // ── Founders ──────────────────────────────────────────────────────────
    public function founders()
    {
        $pageTitle = 'Founding Members';
        $founders  = AboutFounder::orderBy('sort_order')->get();
        return view('admin.cms.about.founders', compact('pageTitle', 'founders'));
    }

    public function founderStore(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'role'        => 'nullable|string|max:100',
            'credentials' => 'nullable|string|max:100',
            'bio'         => 'nullable|string',
            'linkedin'    => 'nullable|string|max:255',
            'twitter'     => 'nullable|string|max:255',
        ]);
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $name = 'founder_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/founders'), $name);
            $data['avatar'] = $name;
        }
        $data['sort_order'] = AboutFounder::max('sort_order') + 1;
        AboutFounder::create($data);
        $notify[] = ['success', 'Founder added'];
        return back()->withNotify($notify);
    }

    public function founderUpdate(Request $request, AboutFounder $founder)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'role'        => 'nullable|string|max:100',
            'credentials' => 'nullable|string|max:100',
            'bio'         => 'nullable|string',
            'linkedin'    => 'nullable|string|max:255',
            'twitter'     => 'nullable|string|max:255',
            'status'      => 'required|in:0,1',
        ]);
        if ($request->hasFile('avatar')) {
            if ($founder->avatar) @unlink(public_path('assets/images/cms/founders/'.$founder->avatar));
            $file = $request->file('avatar');
            $name = 'founder_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/founders'), $name);
            $data['avatar'] = $name;
        }
        $founder->update($data);
        $notify[] = ['success', 'Founder updated'];
        return back()->withNotify($notify);
    }

    public function founderDestroy(AboutFounder $founder)
    {
        if ($founder->avatar) @unlink(public_path('assets/images/cms/founders/'.$founder->avatar));
        $founder->delete();
        $notify[] = ['success', 'Founder deleted'];
        return back()->withNotify($notify);
    }

    // ── Workspace ─────────────────────────────────────────────────────────
    public function workspace()
    {
        $pageTitle = 'Workspace';
        $workspace = AboutWorkspace::first() ?? new AboutWorkspace();
        $slides    = AboutWorkspaceSlide::orderBy('sort_order')->get();
        $offices   = AboutOffice::orderBy('sort_order')->get();
        return view('admin.cms.about.workspace', compact('pageTitle', 'workspace', 'slides', 'offices'));
    }

    public function workspaceUpdate(Request $request)
    {
        $data = $request->validate(['heading' => 'nullable|string|max:100', 'sub' => 'nullable|string|max:255']);
        AboutWorkspace::updateOrCreate(['id' => 1], $data);
        $notify[] = ['success', 'Workspace heading updated'];
        return back()->withNotify($notify);
    }

    public function workspaceSlideStore(Request $request)
    {
        $data = $request->validate(['caption' => 'required|string|max:100', 'sub_caption' => 'nullable|string|max:100', 'tag' => 'nullable|string|max:50']);
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $name = 'ws_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/workspace'), $name);
            $data['image'] = $name;
        }
        $data['sort_order'] = AboutWorkspaceSlide::max('sort_order') + 1;
        AboutWorkspaceSlide::create($data);
        $notify[] = ['success', 'Slide added'];
        return back()->withNotify($notify);
    }

    public function workspaceSlideUpdate(Request $request, AboutWorkspaceSlide $slide)
    {
        $data = $request->validate(['caption' => 'required|string|max:100', 'sub_caption' => 'nullable|string|max:100', 'tag' => 'nullable|string|max:50', 'status' => 'required|in:0,1']);
        if ($request->hasFile('image')) {
            if ($slide->image) @unlink(public_path('assets/images/cms/workspace/'.$slide->image));
            $file = $request->file('image');
            $name = 'ws_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/workspace'), $name);
            $data['image'] = $name;
        }
        $slide->update($data);
        $notify[] = ['success', 'Slide updated'];
        return back()->withNotify($notify);
    }

    public function workspaceSlideDestroy(AboutWorkspaceSlide $slide)
    {
        if ($slide->image) @unlink(public_path('assets/images/cms/workspace/'.$slide->image));
        $slide->delete();
        $notify[] = ['success', 'Slide deleted'];
        return back()->withNotify($notify);
    }

    // ── Offices ───────────────────────────────────────────────────────────
    public function officeStore(Request $request)
    {
        $data = $request->validate([
            'city'    => 'required|string|max:100',
            'flag'    => 'nullable|string|max:10',
            'tag'     => 'nullable|string|max:50',
            'desc'    => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'team'    => 'nullable|string|max:100',
            'hours'   => 'nullable|string|max:100',
        ]);
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $name = 'office_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/offices'), $name);
            $data['photo'] = $name;
        }
        $data['sort_order'] = AboutOffice::max('sort_order') + 1;
        AboutOffice::create($data);
        $notify[] = ['success', 'Office added'];
        return back()->withNotify($notify);
    }

    public function officeUpdate(Request $request, AboutOffice $office)
    {
        $data = $request->validate([
            'city'    => 'required|string|max:100',
            'flag'    => 'nullable|string|max:10',
            'tag'     => 'nullable|string|max:50',
            'desc'    => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'team'    => 'nullable|string|max:100',
            'hours'   => 'nullable|string|max:100',
            'status'  => 'required|in:0,1',
        ]);
        if ($request->hasFile('photo')) {
            if ($office->photo) @unlink(public_path('assets/images/cms/offices/'.$office->photo));
            $file = $request->file('photo');
            $name = 'office_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/offices'), $name);
            $data['photo'] = $name;
        }
        $office->update($data);
        $notify[] = ['success', 'Office updated'];
        return back()->withNotify($notify);
    }

    public function officeDestroy(AboutOffice $office)
    {
        if ($office->photo) @unlink(public_path('assets/images/cms/offices/'.$office->photo));
        $office->delete();
        $notify[] = ['success', 'Office deleted'];
        return back()->withNotify($notify);
    }

    // ── Founder Vision ────────────────────────────────────────────────────
    public function founderVision()
    {
        $pageTitle = 'Founder Vision';
        $vision    = AboutFounderVision::first() ?? new AboutFounderVision();
        return view('admin.cms.about.founder_vision', compact('pageTitle', 'vision'));
    }

    public function founderVisionUpdate(Request $request)
    {
        $data = $request->validate([
            'name'      => 'nullable|string|max:100',
            'title'     => 'nullable|string|max:150',
            'signature' => 'nullable|string|max:100',
            'linkedin'  => 'nullable|string|max:255',
            'twitter'   => 'nullable|string|max:255',
            'youtube'   => 'nullable|string|max:255',
        ]);

        $vision = AboutFounderVision::first() ?? new AboutFounderVision(['id'=>1]);

        if ($request->hasFile('avatar')) {
            if ($vision->avatar) @unlink(public_path('assets/images/cms/founders/'.$vision->avatar));
            $file = $request->file('avatar');
            $name = 'vision_'.time().'.'.$file->getClientOriginalExtension();
            $file->move(public_path('assets/images/cms/founders'), $name);
            $data['avatar'] = $name;
        }

        // Parse paragraphs — one per textarea row
        $paras = array_values(array_filter(array_map('trim', $request->input('paragraphs', []))));
        $data['paragraphs'] = $paras;

        AboutFounderVision::updateOrCreate(['id' => 1], $data);
        $notify[] = ['success', 'Founder vision updated'];
        return back()->withNotify($notify);
    }

    // ── CTA ───────────────────────────────────────────────────────────────
    public function cta()
    {
        $pageTitle = 'About CTA';
        $cta       = AboutCta::first() ?? new AboutCta();
        return view('admin.cms.about.cta', compact('pageTitle', 'cta'));
    }

    public function ctaUpdate(Request $request)
    {
        $data = $request->validate([
            'heading'   => 'nullable|string|max:100',
            'appstore'  => 'nullable|string|max:255',
            'playstore' => 'nullable|string|max:255',
            'webapp'    => 'nullable|string|max:255',
        ]);
        AboutCta::updateOrCreate(['id' => 1], $data);
        $notify[] = ['success', 'CTA updated'];
        return back()->withNotify($notify);
    }
}

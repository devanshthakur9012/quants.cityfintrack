<?php
// FILE: app/Http/Controllers/HomePageController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
// ── Home Models ──────────────────────────────────────────────────────────────
use App\Models\HomeHero;
use App\Models\HomePlatform;
use App\Models\HomeCertSlide;
use App\Models\HomeAbout;
use App\Models\HomeAboutStat;
use App\Models\HomeFeatureTool;
use App\Models\HomeFeatureUtility;
use App\Models\HomeLearningTab;
use App\Models\HomeTestimonial;
// ── About Models ─────────────────────────────────────────────────────────────
use App\Models\AboutHero;
use App\Models\AboutWhoweare;
use App\Models\AboutMission;
use App\Models\AboutFounder;
use App\Models\AboutWorkspace;
use App\Models\AboutWorkspaceSlide;
use App\Models\AboutOffice;
use App\Models\AboutFounderVision;
use App\Models\AboutCta;
// ── Page CMS Models ───────────────────────────────────────────────────────────
use App\Models\MediaPageCms;
use App\Models\AuthPageCms;

class HomePageController extends Controller
{
    public $activeTemplate;

    public function __construct()
    {
        $this->activeTemplate = activeTemplate();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HOME
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $pageTitle = 'Home';

        $reference = $request->get('reference');
        if ($reference) {
            session()->put('reference', $reference);
        }

        // ── 1. HERO ─────────────────────────────────────
        $heroDb = HomeHero::first();
        $hero = [
            'video_url'        => $heroDb && $heroDb->video_file
                ? asset('assets/video/' . $heroDb->video_file)
                : asset('assets/video/hero-main.mp4'),
            'heading_line1'    => $heroDb->heading_line1     ?? 'Complex',
            'heading_highlight'=> $heroDb->heading_highlight ?? 'Option',
            'heading_line2'    => $heroDb->heading_line2     ?? 'Simplified',
            'app_url'          => 'cityquants.com',
            'appstore'         => '#',
            'playstore'        => '#',
            'webapp'           => '#',
        ];

        // ── 2. PLATFORM BANNER ──────────────────────────
        $platformDb = HomePlatform::first();
        $platform = [
            'title'    => $platformDb->title    ?? "India's Largest Options Trading Analytics Platform",
            'subtitle' => $platformDb->subtitle ?? 'Build an option strategy with our options trading analytical tools.',
        ];

        // ── 3. CERT SLIDER ──────────────────────────────
        $certRows = HomeCertSlide::orderBy('sort_order')->get();
        if ($certRows->isNotEmpty()) {
            $certBanners = $certRows->map(function ($slide) {
                $trainers = [];
                if (!empty($slide->trainers)) {
                    $decoded = is_array($slide->trainers) ? $slide->trainers : json_decode($slide->trainers, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $t) {
                            $trainers[] = [
                                'name'   => $t['name']   ?? '',
                                'role'   => $t['role']   ?? '',
                                'avatar' => !empty($t['avatar'])
                                    ? asset('assets/images/cms/cert/' . $t['avatar'])
                                    : '',
                            ];
                        }
                    }
                }
                return [
                    'title'    => $slide->title      ?? 'Option <span>Certification</span>',
                    'badge'    => $slide->badge_text  ?? '',
                    'lang'     => $slide->language    ?? 'In Hindi',
                    'image'    => $slide->image
                        ? asset('assets/images/cms/cert/' . $slide->image)
                        : '',
                    'trainers' => $trainers,
                ];
            })->toArray();
        } else {
            $certBanners = [
                [
                    'title'    => 'Option <span>Certification</span><br>Level 2',
                    'badge'    => 'Intermediate >> Advance Course',
                    'lang'     => 'In Hindi',
                    'image'    => '',
                    'trainers' => [
                        ['name' => 'Bhavin Desai', 'role' => '(President, CityQuants)', 'avatar' => ''],
                        ['name' => 'Varun Shetty',  'role' => '(Trainer)',              'avatar' => ''],
                    ],
                ],
                [
                    'title'    => 'Option <span>Certification</span><br>Level 1',
                    'badge'    => 'Beginner >> Intermediate Course',
                    'lang'     => 'In Hindi',
                    'image'    => '',
                    'trainers' => [
                        ['name' => 'Bhavin Desai', 'role' => '(President, CityQuants)', 'avatar' => ''],
                    ],
                ],
            ];
        }

        // ── 4. ABOUT THE APP ────────────────────────────
        $aboutDb = HomeAbout::first();
        $statsDb = HomeAboutStat::orderBy('sort_order')->get();
        $stats   = $statsDb->isNotEmpty()
            ? $statsDb->map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label,
                'sub'   => $s->sub ?? '',
              ])->toArray()
            : [
                ['value' => '580+',  'label' => 'Happy Families',     'sub' => 'trusted trading community across India'],
                ['value' => '6500+', 'label' => 'Happy Clients',      'sub' => 'active option traders & market participants'],
                ['value' => '5+',    'label' => 'Presence In Cities', 'sub' => 'offline trading workshops & seminars conducted'],
                ['value' => '78.3%', 'label' => 'Retention Rate',     'sub' => 'traders continuing with our learning ecosystem'],
            ];
        $about = [
            'video_type' => $aboutDb->video_type ?? 'youtube',
            'video_url'  => ($aboutDb && $aboutDb->video_type === 'upload' && $aboutDb->video_url)
                ? asset('assets/videos/cms/' . $aboutDb->video_url)
                : ($aboutDb->video_url ?? 'https://www.youtube.com/embed/htLQVsJWwN4?si=YWGvfiv1Y_Aw8FqC?rel=0'),
            'title'      => $aboutDb->section_heading ?? 'Be a " Data Driven " Option Trader!',
            'stats'      => $stats,
        ];

        // ── 5. FEATURE TOOLS ────────────────────────────
        $featureMetaDb = HomeFeatureTool::first();
        $utilitiesDb   = HomeFeatureUtility::where('status', 1)->orderBy('sort_order')->get();
        if ($utilitiesDb->isNotEmpty()) {
            $utilities = $utilitiesDb->map(function ($u) {
                $points = is_array($u->tool_points)
                    ? $u->tool_points
                    : (json_decode($u->tool_points, true) ?? []);
                return [
                    'count'       => $u->count,
                    'label'       => $u->label,
                    'tool_title'  => $u->tool_title,
                    'tool_icon'   => $u->tool_icon ?? 'fa-chart-bar',
                    'tool_points' => $points,
                ];
            })->toArray();
        } else {
            $utilities = [
                ['count'=>'4',  'label'=>'Charts',         'tool_title'=>'Charts',         'tool_icon'=>'fa-chart-bar', 'tool_points'=>['View multiple chart types for deep technical analysis.','Real-time data with customisable indicators and overlays.']],
                ['count'=>'14', 'label'=>'Intraday',       'tool_title'=>'Intraday',       'tool_icon'=>'fa-bolt',      'tool_points'=>['Track intraday OI changes, PCR, and premium movement.','Identify intraday trends with 14 dedicated analytical tools.']],
                ['count'=>'22', 'label'=>'Positional',     'tool_title'=>'Chain',          'tool_icon'=>'fa-link',      'tool_points'=>['Gauge impact of defining variables on entire series of options within milliseconds.','Easiest way to track your greeks.']],
                ['count'=>'1',  'label'=>'Algorithm',      'tool_title'=>'Algorithm',      'tool_icon'=>'fa-robot',     'tool_points'=>['Run algorithmic strategies with our built-in engine.','Backtest and optimise before deploying live.']],
                ['count'=>'6',  'label'=>'Essential Tools','tool_title'=>'Essential Tools','tool_icon'=>'fa-toolbox',   'tool_points'=>['Access 6 must-have tools every options trader needs daily.','From IV calculator to Max Pain — all in one place.']],
            ];
        }
        $features = [
            'title'     => $featureMetaDb->section_title ?? 'CityQuants App Feature Tools',
            'tagline'   => 'Analyze | Backtest | Optimize | Manage your Option Trades',
            'utilities' => $utilities,
        ];

        // ── 6. LEARNING ─────────────────────────────────
        $tabsDb = HomeLearningTab::where('status', 1)->orderBy('sort_order')->get();
        if ($tabsDb->isNotEmpty()) {
            $learningTabs = $tabsDb->map(fn($t) => [
                'tab'         => $t->tab_label,
                'highlight'   => $t->highlight_text ?? '',
                'description' => $t->description    ?? '',
                'btn_label'   => $t->btn_label       ?? 'View Now',
                'btn_url'     => $t->btn_url          ?? '#',
                'video_id'    => $t->video_id         ?? '',
                'video_title' => $t->video_title      ?? '',
                'video_sub'   => $t->video_sub        ?? '',
                'video_date'  => $t->video_date       ?? '',
                'video_time'  => $t->video_time       ?? '',
            ])->toArray();
        } else {
            $learningTabs = [
                ['tab'=>'Webinars',    'highlight'=>'200Hr of FREE videos',  'description'=>'Over 200 hours of recorded webinars...','btn_label'=>'View Now','btn_url'=>'#','video_id'=>'VvwjHncyQ88','video_title'=>'Global Finance to Local Impact','video_sub'=>'IN HINDI','video_date'=>'20 MARCH 2024','video_time'=>'6:00 PM'],
                ['tab'=>'Demo Videos','highlight'=>'Platform walkthroughs', 'description'=>'Watch our demo videos...','btn_label'=>'Watch Now','btn_url'=>'#','video_id'=>'bPTPzZzal-0','video_title'=>'Real insights. Real conversations','video_sub'=>'IN HINDI','video_date'=>'15 JAN 2024','video_time'=>'5:00 PM'],
            ];
        }
        $learning = [
            'title' => 'Learning',
            'tabs'  => $learningTabs,
        ];

        // ── 7. TESTIMONIALS ─────────────────────────────
        $testiDb = HomeTestimonial::where('status', 1)->orderBy('sort_order')->get();
        if ($testiDb->isNotEmpty()) {
            $testimonials = $testiDb->map(fn($t) => [
                'name'   => $t->name,
                'avatar' => $t->avatar
                    ? asset('assets/images/cms/testimonials/' . $t->avatar)
                    : '',
                'rating' => $t->rating ?? 5,
                'review' => $t->review,
            ])->toArray();
        } else {
            $testimonials = [
                ['name'=>'Ravi Bhatt','avatar'=>'','rating'=>5,'review'=>'App is flexible & easy to use.'],
            ];
        }

        return view($this->activeTemplate . 'home', compact(
            'pageTitle', 'hero', 'platform', 'certBanners',
            'about', 'features', 'learning', 'testimonials'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ABOUT
    // ─────────────────────────────────────────────────────────────────────────
    public function about()
    {
        $pageTitle = 'About Us';

        $heroDb = AboutHero::first();
        $heroBanner = [
            'tagline'     => $heroDb->tagline    ?? 'Experts in Providing Investment Consulting Services',
            'subtitle'    => $heroDb->subtitle   ?? "India's most trusted options analytics platform.",
            'founded'     => $heroDb->founded    ?? '2017',
            'hq'          => $heroDb->hq         ?? 'Belgaum, India',
            'users'       => $heroDb->users      ?? '17 Lakh+',
            'experience'  => $heroDb->experience ?? '23+ Years',
            'stat1_value' => $heroDb->stat1_value ?? '17 Lakh+',
            'stat1_label' => $heroDb->stat1_label ?? 'Active Traders',
            'stat2_value' => $heroDb->stat2_value ?? '23+ Years',
            'stat2_label' => $heroDb->stat2_label ?? 'Team Experience',
            'stat3_value' => $heroDb->stat3_value ?? '100+',
            'stat3_label' => $heroDb->stat3_label ?? 'Analytics Tools',
            'stat4_value' => $heroDb->stat4_value ?? '50K+',
            'stat4_label' => $heroDb->stat4_label ?? 'Students Trained',
        ];

        $whoDb   = AboutWhoweare::first() ?? "";
        $pillars = [];
        if ($whoDb && !empty($whoDb->pillars)) {
            $decoded = is_array($whoDb->pillars) ? $whoDb->pillars : json_decode($whoDb->pillars, true);
            if (is_array($decoded)) $pillars = $decoded;
        }
        if (empty($pillars)) {
            $pillars = [
                ['icon'=>'fa-chart-line',    'label'=>'Capital Risk Frameworks'],
                ['icon'=>'fa-cogs',          'label'=>'Proprietary Trading Systems'],
                ['icon'=>'fa-microchip',     'label'=>'Technology & Infrastructure'],
                ['icon'=>'fa-graduation-cap','label'=>'Training & Capability Building'],
            ];
        }
        $whoWeAre = [
            'heading' => $whoDb->heading ?? 'Who Are We?',
            'body'    => $whoDb->body    ?? 'CityQuants is a dedicated Options Analytics Platform made for Traders by Traders.',
            'pillars' => $pillars,
        ];

        $missionDb = AboutMission::first();
        $values    = [];
        if ($missionDb && !empty($missionDb->values)) {
            $decoded = is_array($missionDb->values) ? $missionDb->values : json_decode($missionDb->values, true);
            if (is_array($decoded)) $values = $decoded;
        }
        if (empty($values)) {
            $values = [
                ['icon'=>'fa-balance-scale',    'label'=>'Discipline over Speculation',   'desc'=>'Focused, systematic investing with strict process adherence.'],
                ['icon'=>'fa-eye',              'label'=>'Transparency & Accountability', 'desc'=>'Open, honest platform with no hidden costs or conflicts.'],
                ['icon'=>'fa-shield-halved',    'label'=>'Risk-First Thinking',           'desc'=>'Robust risk management at the core of every decision.'],
                ['icon'=>'fa-sitemap',          'label'=>'Systems & Process Integrity',   'desc'=>'Scalable, technology-driven systems that ensure consistency.'],
                ['icon'=>'fa-clock-rotate-left','label'=>'Long-Term Orientation',         'desc'=>'Building sustainable wealth through patient, long-term strategies.'],
            ];
        }
        $mission = [
            'heading' => $missionDb->heading ?? 'Our Mission & Vision',
            'body'    => $missionDb->body    ?? 'Our Vision: To build a globally impactful financial services platform.',
            'values'  => $values,
        ];

        $foundersDb = AboutFounder::where('status', 1)->orderBy('sort_order')->get();
        if ($foundersDb->isNotEmpty()) {
            $ideators = $foundersDb->map(fn($f) => [
                'name'     => $f->name,
                'role'     => $f->role        ?? '',
                'creds'    => $f->credentials ?? '',
                'bio'      => $f->bio         ?? '',
                'avatar'   => $f->avatar ? asset('assets/images/cms/founders/' . $f->avatar) : '',
                'linkedin' => $f->linkedin    ?? '#',
                'twitter'  => $f->twitter     ?? '#',
            ])->toArray();
        } else {
            $ideators = [
                ['name'=>'Vitthal Tallur', 'role'=>'Founder & CTO, CityQuants','creds'=>'CMT, CFA, CQF, CFTe','bio'=>'Vitthal Tallur is the Founder & CTO of CityQuants.','avatar'=>'','linkedin'=>'#','twitter'=>'#'],
                ['name'=>'Rahul Karakulle','role'=>'Co-Founder & CTO, CityQuants','creds'=>'MMS – Finance','bio'=>'Rahul Karakulle is the Co-Founder & CTO of CityQuants.','avatar'=>'','linkedin'=>'#','twitter'=>'#'],
            ];
        }

        $wsDb      = AboutWorkspace::first();
        $slidesDb  = AboutWorkspaceSlide::where('status', 1)->orderBy('sort_order')->get();
        $officesDb = AboutOffice::where('status', 1)->orderBy('sort_order')->get();

        $slides = $slidesDb->isNotEmpty()
            ? $slidesDb->map(fn($s) => [
                'src'     => $s->image ? asset('assets/images/cms/workspace/' . $s->image) : '',
                'caption' => $s->caption     ?? '',
                'sub'     => $s->sub_caption ?? '',
                'tag'     => $s->tag         ?? 'OFFICE',
              ])->toArray()
            : [['src'=>'','caption'=>'Belgaum HQ — Main Trading Floor','sub'=>'Lower Parel, Belgaum','tag'=>'HEADQUARTERS']];

        $offices = $officesDb->isNotEmpty()
            ? $officesDb->map(fn($o) => [
                'city'    => $o->city,
                'flag'    => $o->flag    ?? '🏙️',
                'tag'     => $o->tag     ?? 'OFFICE',
                'photo'   => $o->photo ? asset('assets/images/cms/offices/' . $o->photo) : '',
                'desc'    => $o->desc    ?? '',
                'address' => $o->address ?? '',
                'team'    => $o->team    ?? '',
                'hours'   => $o->hours   ?? '',
              ])->toArray()
            : [['city'=>'Belgaum','flag'=>'🏙️','tag'=>'HEADQUARTERS','photo'=>'','desc'=>'Our main HQ.','address'=>'Lower Parel, Belgaum','team'=>'80+ members','hours'=>'Mon–Sat, 9–7 PM']];

        $workspace = [
            'heading' => $wsDb->heading ?? 'Our Workspace',
            'sub'     => $wsDb->sub     ?? 'Where ideas meet execution.',
            'slides'  => $slides,
            'offices' => $offices,
        ];

        $visionDb = AboutFounderVision::first();
        $paras    = [];
        if ($visionDb && !empty($visionDb->paragraphs)) {
            $decoded = is_array($visionDb->paragraphs) ? $visionDb->paragraphs : json_decode($visionDb->paragraphs, true);
            if (is_array($decoded)) $paras = $decoded;
        }
        if (empty($paras)) {
            $paras = ['I have a firm belief that Start-ups are not meant to start a diversified business.'];
        }
        $ceoVision = [
            'name'      => $visionDb->name      ?? 'Vitthal Tallur',
            'title'     => $visionDb->title     ?? 'Founder & CTO, CityQuants',
            'signature' => $visionDb->signature ?? 'Vitthal Tallur',
            'avatar'    => ($visionDb && $visionDb->avatar)
                ? asset('assets/images/cms/founders/' . $visionDb->avatar)
                : '',
            'linkedin'  => $visionDb->linkedin ?? '#',
            'twitter'   => $visionDb->twitter  ?? '#',
            'youtube'   => $visionDb->youtube  ?? '#',
            'paras'     => $paras,
        ];

        $ctaDb = AboutCta::first();
        $cta   = [
            'heading'   => $ctaDb->heading   ?? 'Get The App Here!',
            'appstore'  => $ctaDb->appstore  ?? '#',
            'playstore' => $ctaDb->playstore ?? '#',
            'webapp'    => $ctaDb->webapp    ?? '#',
        ];

        return view($this->activeTemplate . 'about', compact(
            'pageTitle', 'heroBanner', 'whoWeAre', 'mission',
            'ideators', 'workspace', 'ceoVision', 'cta'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LOGIN  (now reads from AuthPageCms)
    // ─────────────────────────────────────────────────────────────────────────
    public function login()
    {
        $pageTitle = 'Login';
        $cms       = AuthPageCms::getData();
        $features  = $cms->features_list;
        $brokers   = $cms->brokers_list;
        $promoVideo = $cms->promo_video_url
            ?? 'https://www.youtube.com/embed/MxpeY6j-_XE?si=7BILhTJxdUhdBP5O?autoplay=1&mute=1&rel=0&modestbranding=1&controls=1';
        $loginHeading    = $cms->login_heading    ?? 'Welcome Back';
        $loginSubheading = $cms->login_subheading ?? '';

        return view($this->activeTemplate . 'login', compact(
            'pageTitle', 'features', 'brokers', 'promoVideo',
            'loginHeading', 'loginSubheading'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MEDIA
    // ─────────────────────────────────────────────────────────────────────────
    public function media()
    {
        $pageTitle = 'Media';

        // CMS data for the media page
        $mediaCms = MediaPageCms::getData();

        $categories = \App\Models\MediaCategory::where('is_active', true)
            ->withCount(['mediaItems' => fn($q) => $q->where('is_active', true)])
            ->having('media_items_count', '>', 0)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories->each(function ($cat) {
            $cat->setRelation('mediaItems',
                $cat->mediaItems()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderByDesc('created_at')
                    ->get()
            );
        });

        return view($this->activeTemplate . 'media', compact('pageTitle', 'categories', 'mediaCms'));
    }

    public function mediaLoadMore(\App\Models\MediaCategory $category, \Illuminate\Http\Request $request)
    {
        $offset = (int) $request->get('offset', 20);
        $items  = $category->mediaItems()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->skip($offset)
            ->take(20)
            ->get()
            ->map(fn($item) => [
                'id'          => $item->id,
                'title'       => $item->title,
                'description' => $item->description,
                'file_url'    => $item->file_url,
                'file_type'   => $item->file_type,
            ]);

        return response()->json(['items' => $items]);
    }
}
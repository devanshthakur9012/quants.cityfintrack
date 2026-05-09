<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomePageController extends Controller
{
    public $activeTemplate;

    public function __construct()
    {
        $this->activeTemplate = activeTemplate();
    }

    public function index(Request $request)
    {
        $pageTitle = 'Home';

        $reference = $request->get('reference');
        if ($reference) {
            session()->put('reference', $reference);
        }

        // ── 1. HERO ─────────────────────────────────────
        // Replace video_url with your real hosted .mp4
        $hero = [
            'video_url' => asset('assets/video/hero.mp4'),
            'app_url'   => 'cityfintrack.com',
            'appstore'  => '#',
            'playstore' => '#',
            'webapp'    => '#',
        ];

        // ── 2. PLATFORM BANNER ──────────────────────────
        $platform = [
            'title'    => "India's Largest Options Trading Analytics Platform",
            'subtitle' => 'Build an option strategy with our options trading analytical tools.',
        ];

        // ── 3. CERT SLIDER ──────────────────────────────
        $certBanners = [
            [
                'title'    => 'Option <span>Certification</span><br>Level 2',
                'badge'    => 'Intermediate >> Advance Course',
                'lang'     => 'In Hindi',
                'trainers' => [
                    ['name' => 'Bhavin Desai', 'role' => '(President, Quantsapp)', 'avatar' => ''],
                    ['name' => 'Varun Shetty',  'role' => '(Trainer)',              'avatar' => ''],
                ],
            ],
            [
                'title'    => 'Option <span>Certification</span><br>Level 1',
                'badge'    => 'Beginner >> Intermediate Course',
                'lang'     => 'In Hindi',
                'trainers' => [
                    ['name' => 'Bhavin Desai', 'role' => '(President, Quantsapp)', 'avatar' => ''],
                ],
            ],
        ];

        // ── 4. ABOUT THE APP ────────────────────────────
        $about = [
            'video_url'   => 'https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0',
            'title'       => 'Be a " Data Driven " Option Trader!',
            'stats'       => [
                ['value' => '100+',     'label' => 'Options Analytics', 'sub' => 'largest in India'],
                ['value' => '17 Lakh+', 'label' => 'Traders',           'sub' => 'Institutions, Brokers, HNI, Traders'],
                ['value' => '50,000+',  'label' => 'Students',          'sub' => 'many options experts have been our students'],
                ['value' => '23+',      'label' => 'Experience',        'sub' => 'of Leadership team'],
            ],
        ];

        // ── 5. FEATURE TOOLS ────────────────────────────
        $features = [
            'title'   => 'Quantsapp App Feature Tools',
            'tagline' => 'Analyze | Backtest | Optimize | Manage your Option Trades',
            'utilities' => [
                [
                    'count'       => '4',
                    'label'       => 'Charts',
                    'tool_title'  => 'Charts',
                    'tool_icon'   => 'fa-chart-bar',
                    'tool_points' => [
                        'View multiple chart types for deep technical analysis.',
                        'Real-time data with customisable indicators and overlays.',
                    ],
                ],
                [
                    'count'       => '14',
                    'label'       => 'Intraday',
                    'tool_title'  => 'Intraday',
                    'tool_icon'   => 'fa-bolt',
                    'tool_points' => [
                        'Track intraday OI changes, PCR, and premium movement.',
                        'Identify intraday trends with 14 dedicated analytical tools.',
                    ],
                ],
                [
                    'count'       => '22',
                    'label'       => 'Positional',
                    'tool_title'  => 'Chain',
                    'tool_icon'   => 'fa-link',
                    'tool_points' => [
                        'Gauge impact of defining variables on entire series of options within milliseconds.',
                        'Easiest way to track your greeks.',
                    ],
                ],
                [
                    'count'       => '1',
                    'label'       => 'Algorithm',
                    'tool_title'  => 'Algorithm',
                    'tool_icon'   => 'fa-robot',
                    'tool_points' => [
                        'Run algorithmic strategies with our built-in engine.',
                        'Backtest and optimise before deploying live.',
                    ],
                ],
                [
                    'count'       => '6',
                    'label'       => 'Essential Tools',
                    'tool_title'  => 'Essential Tools',
                    'tool_icon'   => 'fa-toolbox',
                    'tool_points' => [
                        'Access 6 must-have tools every options trader needs daily.',
                        'From IV calculator to Max Pain — all in one place.',
                    ],
                ],
            ],
        ];

        // ── 6. LEARNING ─────────────────────────────────
        $learning = [
            'title' => 'Learning',
            'tabs'  => [
                [
                    'tab'         => 'Webinars',
                    'highlight'   => 'More than 200 hours of FREE videos',
                    'description' => 'Over 200 hours of recorded webinars to bring you up to speed on the markets & to help you get familiarized with our options trading & our tools.',
                    'btn_label'   => 'View Now',
                    'btn_url'     => '#',
                    'video_id'    => 'dQw4w9WgXcQ',
                    'video_title' => 'Positional Option Trading Strategy',
                    'video_sub'   => 'IN HINDI',
                    'video_date'  => '20 MARCH 2024',
                    'video_time'  => '6:00 PM',
                ],
                [
                    'tab'         => 'Demo Videos',
                    'highlight'   => 'Step-by-step platform walkthroughs',
                    'description' => 'Watch our demo videos to quickly learn how to use each tool on the Quantsapp platform — perfect for beginners and advanced traders alike.',
                    'btn_label'   => 'Watch Now',
                    'btn_url'     => '#',
                    'video_id'    => 'dQw4w9WgXcQ',
                    'video_title' => 'Quantsapp Platform Demo',
                    'video_sub'   => 'IN HINDI',
                    'video_date'  => '15 JAN 2024',
                    'video_time'  => '5:00 PM',
                ],
            ],
        ];

        // ── 7. TESTIMONIALS ─────────────────────────────
        $testimonials = [
            ['name' => 'Ravi Bhatt',       'avatar' => '', 'rating' => 5,
             'review' => 'App is flexible & easy to use. Analysis of the market is great, it helps me to understand the exact wave of market. Great work by team. Good app & Great analysis.'],
            ['name' => 'Robin Ghoshal',    'avatar' => '', 'rating' => 5,
             'review' => 'Very good app. All necessary features available for an option trader to decide for an option trade. Visually appealing and easy to use. Right from open interest to, strategy builder, optimization, greeks etc. A must try for all option traders.'],
            ['name' => 'Aashish Rajgaria', 'avatar' => '', 'rating' => 5,
             'review' => "Amazing app. I've been struggling to find option data organized and sorted in one place but this app is the ultimate answer. The parallel web login is just a cherry on the cake. I love their optimizer and tabulated chain data. The support is very clear too. 5 stars keep the good work up guys."],
            ['name' => 'Dhananjay Deo',    'avatar' => '', 'rating' => 5,
             'review' => 'It is an excellent app for options traders. A must app to build your option trade namaste and test it. Features like maxpain max gain help in devising expiry trade. Kudos to quantsapp team.'],
            ['name' => 'Priya Sharma',     'avatar' => '', 'rating' => 5,
             'review' => 'Best options analytics tool in India. The UI is clean and data is real-time. Highly recommended for serious traders who want an edge in the market.'],
            ['name' => 'Kiran Mehta',      'avatar' => '', 'rating' => 5,
             'review' => 'Quantsapp has completely changed how I trade options. The backtesting feature alone is worth the subscription. Excellent platform overall!'],
        ];

        return view($this->activeTemplate . 'home', compact(
            'pageTitle', 'hero', 'platform', 'certBanners',
            'about', 'features', 'learning', 'testimonials'
        ));
    }

    public function about()
      {
        $pageTitle = 'About Us';
 
        // ── HERO / TOP BANNER ───────────────────────────
        $heroBanner = [
            'tagline'     => 'Empowering Traders with Intelligence',
            'subtitle'    => 'India\'s most trusted options analytics platform — built by traders, for traders.',
            'founded'     => '2017',
            'hq'          => 'Mumbai, India',
            'users'       => '17 Lakh+',
            'experience'  => '23+ Years',
        ];
 
        // ── WHO ARE WE + MISSION ────────────────────────
        $whoWeAre = [
            'heading'  => 'Who Are We?',
            'body'     => 'Quantsapp is a dedicated Options Analytics Platform made for Traders by Traders, with the widest range of FREE option trading tools in the industry. We combine deep domain expertise with cutting-edge technology to bring institutional-grade analytics directly to retail traders — at a fraction of the cost.',
            'pillars'  => [
                ['icon' => 'fa-chart-line',   'label' => 'Analytics First'],
                ['icon' => 'fa-shield-halved','label' => 'Trusted Platform'],
                ['icon' => 'fa-graduation-cap','label' => 'Trader Education'],
                ['icon' => 'fa-bolt',         'label' => 'Real-Time Data'],
            ],
        ];
 
        $mission = [
            'heading' => 'Our Mission',
            'body'    => 'Our goal is to equip Retail Traders with intelligent Algorithms via tools at much affordable cost & without putting much of knowledge — so they can be placed well in the battle of Futures & Options. We believe that access to powerful analytics should not be a privilege limited to institutional players.',
            'values'  => [
                ['icon' => 'fa-eye',          'label' => 'Transparency',   'desc' => 'Open, honest platform with no hidden costs.'],
                ['icon' => 'fa-people-group', 'label' => 'Inclusivity',    'desc' => 'Democratising institutional tools for all traders.'],
                ['icon' => 'fa-lightbulb',    'label' => 'Innovation',     'desc' => 'Constant iteration driven by real trader needs.'],
                ['icon' => 'fa-handshake',    'label' => 'Community',      'desc' => 'A thriving ecosystem of 17 Lakh+ traders.'],
            ],
        ];
 
        // ── IDEATORS ────────────────────────────────────
        $ideators = [
            [
                'name'    => 'Shubham Agarwal',
                'role'    => 'CEO, Quantsapp',
                'creds'   => 'CMT, CFA, CQF, CFTe',
                'bio'     => 'A Chartered Market Technician (MTA, USA) & Chartered Financial Analyst (CFA Institute, USA), Shubham brings over a decade of expertise in Derivatives & Algorithmic trading. His vision drives Quantsapp\'s mission to bring institutional-grade analytics to every retail trader in India.',
                'avatar'  => '',
                'linkedin' => '#',
                'twitter'  => '#',
            ],
            [
                'name'    => 'Tina Gadodia',
                'role'    => 'President, Quantsapp',
                'creds'   => 'MMS – Finance',
                'bio'     => 'With 15+ years of professional experience in Futures & Options Research, Tina leads Quantsapp\'s product and research divisions. Her expertise in market dynamics and deep understanding of trader psychology shapes the platform\'s analytical approach.',
                'avatar'  => '',
                'linkedin' => '#',
                'twitter'  => '#',
            ],
            [
                'name'    => 'Bhavin Desai',
                'role'    => 'President, Quantsapp',
                'creds'   => 'MMS – Finance',
                'bio'     => 'Bhavin brings 20+ years of professional experience in Futures & Options Research. As co-architect of Quantsapp\'s proprietary tools, he ensures the platform remains at the cutting edge of options analytics and algorithmic strategy.',
                'avatar'  => '',
                'linkedin' => '#',
                'twitter'  => '#',
            ],
        ];
 
        // ── WORKSPACE IMAGES + CITY OFFICES ────────────
        $workspace = [
            'heading' => 'Our Workspace',
            'sub'     => 'Where ideas meet execution — our offices across India are built for focused, high-performance work.',
 
            // Main slider — add real asset src paths when available
            'slides' => [
                ['src' => '', 'caption' => 'Mumbai HQ — Main Trading Floor',     'sub' => 'Lower Parel, Mumbai',       'tag' => 'HEADQUARTERS'],
                ['src' => '', 'caption' => 'Open Collaboration Zone',            'sub' => 'Mumbai HQ',                 'tag' => 'CREATIVE SPACE'],
                ['src' => '', 'caption' => 'Quantsapp Research Lab',             'sub' => 'Algo & Quant Division',     'tag' => 'R&D'],
                ['src' => '', 'caption' => 'Webinar & Training Studio',          'sub' => 'Live Sessions & Recordings','tag' => 'STUDIO'],
                ['src' => '', 'caption' => 'Executive Boardroom',                'sub' => 'Strategy & Leadership',     'tag' => 'BOARDROOM'],
            ],
 
            // City offices shown as tabs below slider
            'offices' => [
                [
                    'city'    => 'Mumbai',
                    'flag'    => '🏙️',
                    'tag'     => 'HEADQUARTERS',
                    'photo'   => '',
                    'desc'    => 'Our main headquarters and nerve centre. Home to our core trading analytics, research, product, and leadership teams. The Mumbai office drives Quantsapp\'s vision and day-to-day operations.',
                    'address' => 'Lower Parel, Mumbai, Maharashtra — 400013',
                    'team'    => '80+ team members across product, research & ops',
                    'hours'   => 'Mon – Sat, 9:00 AM – 7:00 PM IST',
                ],
                [
                    'city'    => 'Bangalore',
                    'flag'    => '🌿',
                    'tag'     => 'TECH HUB',
                    'photo'   => '',
                    'desc'    => 'Our Bangalore office powers the engineering and platform development behind Quantsapp. The tech hub drives innovation on our algorithmic engine, real-time data infrastructure, and mobile products.',
                    'address' => 'Koramangala, Bengaluru, Karnataka — 560034',
                    'team'    => '35+ engineers, DevOps & QA professionals',
                    'hours'   => 'Mon – Fri, 9:00 AM – 6:30 PM IST',
                ],
                [
                    'city'    => 'Delhi',
                    'flag'    => '🏛️',
                    'tag'     => 'NORTH INDIA OFFICE',
                    'photo'   => '',
                    'desc'    => 'Serving traders across North India, our Delhi office focuses on institutional partnerships, sales, and regional trader education programmes. A growing hub for HNI and institutional client servicing.',
                    'address' => 'Connaught Place, New Delhi — 110001',
                    'team'    => '20+ sales, partnerships & support members',
                    'hours'   => 'Mon – Sat, 9:30 AM – 6:30 PM IST',
                ],
                [
                    'city'    => 'Hyderabad',
                    'flag'    => '💎',
                    'tag'     => 'SOUTH OFFICE',
                    'photo'   => '',
                    'desc'    => 'Our Hyderabad office manages south India trader communities and education initiatives. The team runs regional webinars, onboarding programmes, and works closely with local brokers and institutions.',
                    'address' => 'Banjara Hills, Hyderabad, Telangana — 500034',
                    'team'    => '15+ community, education & support members',
                    'hours'   => 'Mon – Fri, 9:30 AM – 6:00 PM IST',
                ],
                [
                    'city'    => 'Pune',
                    'flag'    => '🎓',
                    'tag'     => 'EDUCATION CENTRE',
                    'photo'   => '',
                    'desc'    => 'Pune hosts our dedicated Options Education Centre — the venue for our flagship certification programmes, in-person workshops, and trader bootcamps. Thousands of students have trained here.',
                    'address' => 'Shivajinagar, Pune, Maharashtra — 411005',
                    'team'    => '12+ trainers, curriculum & event coordinators',
                    'hours'   => 'Mon – Sat, 9:00 AM – 7:00 PM IST',
                ],
            ],
        ];
 
        // ── CEO VISION ──────────────────────────────────
        $ceoVision = [
            'name'      => 'Shubham Agarwal',
            'title'     => 'CEO & Founder, Quantsapp',
            'signature' => 'Shubham Agarwal',
            'avatar'    => '',
            'paras'     => [
                'I have a firm belief that Start-ups are not meant to start a diversified business — it starts with a unique idea where opportunity is visible and it walks faster than the world due to a sharp focus. At Quantsapp, we are backed by Industry Veterans who work without limits because we love what we do.',
                'The idea behind Quantsapp was to look within our own interests and expertise. After being in this field for above a decade, combining Options Trading & Algorithms is ultimate and will change how people trade today. As researchers, we foresee a world tomorrow where we are confident we will make that a reality ourselves.',
                'After serving Retail / HNI Traders since ever, we surely have a bias towards Derivatives — it is a zero sum game. Institutions have an upper hand of advance research, but retail clients cannot afford the same. We are a team of researchers with this being our passion, having honed the art of keeping complexity low and offering it as a piece of cake for retail traders.',
                'We are confident of making our platform the most unique one globally in the Options Trading domain. We are currently live with our application in India and soon will be available in US, Europe & Canada. Options is the most traded and least understood instrument globally — and our mentors are enthusiasts to pass on the knowledge you need to trade options wisely.',
                'I welcome you to join us in the journey bringing equality in the game of Options Trading.',
            ],
        ];
 
        // ── CTA AT BOTTOM ───────────────────────────────
        $cta = [
            'heading'   => 'Get The App Here!',
            'appstore'  => '#',
            'playstore' => '#',
            'webapp'    => '#',
        ];
 
        return view($this->activeTemplate . 'about', compact(
            'pageTitle', 'heroBanner', 'whoWeAre', 'mission',
            'ideators', 'workspace', 'ceoVision', 'cta'
        ));
    }
}
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
        $hero = [
            'video_url' => asset('assets/video/hero.mp4'),
            'app_url'   => 'cityquants.com',
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
            'video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0',
            'title'     => 'Be a " Data Driven " Option Trader!',
            'stats'     => [
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

    // ─────────────────────────────────────────────────────────────────────────
    // ABOUT
    // ─────────────────────────────────────────────────────────────────────────
    public function about()
    {
        $pageTitle = 'About Us';

        // ── HERO / TOP BANNER ───────────────────────────
        $heroBanner = [
            'tagline'    => 'Empowering Traders with Intelligence',
            'subtitle'   => 'India\'s most trusted options analytics platform — built by traders, for traders.',
            'founded'    => '2017',
            'hq'         => 'Mumbai, India',
            'users'      => '17 Lakh+',
            'experience' => '23+ Years',
        ];

        // ── WHO ARE WE + MISSION ────────────────────────
        $whoWeAre = [
            'heading' => 'Who Are We?',
            'body'    => 'Quantsapp is a dedicated Options Analytics Platform made for Traders by Traders, with the widest range of FREE option trading tools in the industry. We combine deep domain expertise with cutting-edge technology to bring institutional-grade analytics directly to retail traders — at a fraction of the cost.',
            'pillars' => [
                ['icon' => 'fa-chart-line',    'label' => 'Analytics First'],
                ['icon' => 'fa-shield-halved', 'label' => 'Trusted Platform'],
                ['icon' => 'fa-graduation-cap','label' => 'Trader Education'],
                ['icon' => 'fa-bolt',          'label' => 'Real-Time Data'],
            ],
        ];

        $mission = [
            'heading' => 'Our Mission',
            'body'    => 'Our goal is to equip Retail Traders with intelligent Algorithms via tools at much affordable cost & without putting much of knowledge — so they can be placed well in the battle of Futures & Options. We believe that access to powerful analytics should not be a privilege limited to institutional players.',
            'values'  => [
                ['icon' => 'fa-eye',          'label' => 'Transparency', 'desc' => 'Open, honest platform with no hidden costs.'],
                ['icon' => 'fa-people-group', 'label' => 'Inclusivity',  'desc' => 'Democratising institutional tools for all traders.'],
                ['icon' => 'fa-lightbulb',    'label' => 'Innovation',   'desc' => 'Constant iteration driven by real trader needs.'],
                ['icon' => 'fa-handshake',    'label' => 'Community',    'desc' => 'A thriving ecosystem of 17 Lakh+ traders.'],
            ],
        ];

        // ── IDEATORS ────────────────────────────────────
        $ideators = [
            [
                'name'     => 'Shubham Agarwal',
                'role'     => 'CEO, Quantsapp',
                'creds'    => 'CMT, CFA, CQF, CFTe',
                'bio'      => 'A Chartered Market Technician (MTA, USA) & Chartered Financial Analyst (CFA Institute, USA), Shubham brings over a decade of expertise in Derivatives & Algorithmic trading. His vision drives Quantsapp\'s mission to bring institutional-grade analytics to every retail trader in India.',
                'avatar'   => '',
                'linkedin' => '#',
                'twitter'  => '#',
            ],
            [
                'name'     => 'Tina Gadodia',
                'role'     => 'President, Quantsapp',
                'creds'    => 'MMS – Finance',
                'bio'      => 'With 15+ years of professional experience in Futures & Options Research, Tina leads Quantsapp\'s product and research divisions. Her expertise in market dynamics and deep understanding of trader psychology shapes the platform\'s analytical approach.',
                'avatar'   => '',
                'linkedin' => '#',
                'twitter'  => '#',
            ],
            [
                'name'     => 'Bhavin Desai',
                'role'     => 'President, Quantsapp',
                'creds'    => 'MMS – Finance',
                'bio'      => 'Bhavin brings 20+ years of professional experience in Futures & Options Research. As co-architect of Quantsapp\'s proprietary tools, he ensures the platform remains at the cutting edge of options analytics and algorithmic strategy.',
                'avatar'   => '',
                'linkedin' => '#',
                'twitter'  => '#',
            ],
        ];

        // ── WORKSPACE IMAGES + CITY OFFICES ────────────
        $workspace = [
            'heading' => 'Our Workspace',
            'sub'     => 'Where ideas meet execution — our offices across India are built for focused, high-performance work.',
            'slides'  => [
                ['src' => '', 'caption' => 'Mumbai HQ — Main Trading Floor',     'sub' => 'Lower Parel, Mumbai',        'tag' => 'HEADQUARTERS'],
                ['src' => '', 'caption' => 'Open Collaboration Zone',            'sub' => 'Mumbai HQ',                  'tag' => 'CREATIVE SPACE'],
                ['src' => '', 'caption' => 'Quantsapp Research Lab',             'sub' => 'Algo & Quant Division',      'tag' => 'R&D'],
                ['src' => '', 'caption' => 'Webinar & Training Studio',          'sub' => 'Live Sessions & Recordings', 'tag' => 'STUDIO'],
                ['src' => '', 'caption' => 'Executive Boardroom',                'sub' => 'Strategy & Leadership',      'tag' => 'BOARDROOM'],
            ],
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

    // ─────────────────────────────────────────────────────────────────────────
    // WEBINARS
    // ─────────────────────────────────────────────────────────────────────────
    public function webinars()
    {
        $pageTitle = 'Webinars';

        // ── HERO BANNER ─────────────────────────────────
        $heroBanner = [
            'title'       => 'Webinar',
            'description' => 'Our webinar series is designed to bring you cutting-edge insights, expert perspectives, and actionable insights tips on a wide range of Futures & Options related topics. Whether you\'re a seasoned professional looking to stay ahead of industry trends or a curious learner eager to explore new subjects, our webinars offer something for everyone.',
            'illustration' => 'https://img.freepik.com/free-vector/webinar-concept-illustration_114360-4798.jpg?w=400',
        ];

        // ── FILTER OPTIONS ──────────────────────────────
        $languages   = ['Hindi', 'English', 'Gujarati'];
        $proficiency = ['Beginner', 'Intermediate', 'Advanced'];

        // ── CATEGORY PILLS ──────────────────────────────
        $categories = [
            'Option Basics', 'Directional Trading', 'Intraday Trading',
            'Expiry Day Trading', 'Options Buying', 'Options Selling',
            'Volatility Trading', 'Swing Trading', 'Breakout Trading',
            'Options Trading Strategies', 'Derivative Analytics',
            'Open Interest', 'Options Greeks', 'Option Chain',
            'Put Call Ratio', 'Order Book Analysis', 'Technical Analysis',
            'Pair Trading', 'Event Trading', 'Backtesting',
            'Live Market', 'Python', 'Algotrading', 'Superapi',
            'Quantsappsuperapi',
        ];

        // ── UPCOMING / LIVE WEBINARS ─────────────────────
        $upcomingWebinars = [
            [
                'id'          => 1,
                'title'       => 'Options Mastery Blueprint',
                'status'      => 'live',   // live | upcoming
                'price'       => 0,
                'mrp'         => 999,
                'discount'    => '100% off',
                'type'        => 'free',   // free | paid
                'level'       => 'Beginner Level',
                'date'        => '10-May-26 14:00:00',
                'duration'    => '2 hr',
                'language'    => 'Hindi',
                'thumbnail'   => 'https://img.freepik.com/free-vector/online-trading-concept-illustration_114360-4766.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 2,
                'title'       => "Learn India's Most Advanced Option Trading Platform",
                'status'      => 'upcoming',
                'price'       => 0,
                'mrp'         => 999,
                'discount'    => '100% off',
                'type'        => 'free',
                'level'       => 'Beginner Level',
                'date'        => '12-May-26 11:00:00',
                'duration'    => '1.5 hr',
                'language'    => 'Hindi',
                'thumbnail'   => 'https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 3,
                'title'       => 'Market Outlook by Akhil Rai',
                'status'      => 'upcoming',
                'price'       => 0,
                'mrp'         => 2999,
                'discount'    => '100% off',
                'type'        => 'free',
                'level'       => 'Intermediate Level',
                'date'        => '14-May-26 18:00:00',
                'duration'    => '1 hr',
                'language'    => 'Hindi',
                'thumbnail'   => 'https://img.freepik.com/free-vector/market-launch-concept-illustration_114360-5508.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 4,
                'title'       => 'Learn Alert Trigger Order for Options Trading',
                'status'      => 'upcoming',
                'price'       => 0,
                'mrp'         => 999,
                'discount'    => '100% off',
                'type'        => 'free',
                'level'       => 'Beginner Level',
                'date'        => '15-May-26 17:00:00',
                'duration'    => '2 hr',
                'language'    => 'Hindi',
                'thumbnail'   => 'https://img.freepik.com/free-vector/algorithmic-trading-concept-illustration_114360-5482.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 5,
                'title'       => 'Options Mastery — Strategy Workshop',
                'status'      => 'live',
                'price'       => 0,
                'mrp'         => 999,
                'discount'    => '100% off',
                'type'        => 'free',
                'level'       => 'Beginner Level',
                'date'        => '16-May-26 14:00:00',
                'duration'    => '2 hr',
                'language'    => 'Hindi',
                'thumbnail'   => 'https://img.freepik.com/free-vector/financial-analytics-concept-illustration_114360-6079.jpg?w=600',
                'url'         => '#',
            ],
        ];

        // ── PAST WEBINARS ───────────────────────────────
        $pastWebinars = [
            [
                'id'          => 101,
                'title'       => 'Trading Directional Market in High Volatile Market',
                'status'      => 'past',
                'price'       => 999,
                'mrp'         => 999,
                'discount'    => null,
                'type'        => 'paid',
                'level'       => 'Beginner Level',
                'date'        => '09-May-26 12:00:00',
                'duration'    => '60 min',
                'language'    => 'Hindi',
                'recording'   => 'buy',   // available | buy
                'thumbnail'   => 'https://img.freepik.com/free-vector/gradient-stock-market-concept-illustration_23-2149166705.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 102,
                'title'       => 'MODIFIED OI PCR ( Sentimental Indicator )',
                'status'      => 'past',
                'price'       => 999,
                'mrp'         => 999,
                'discount'    => null,
                'type'        => 'paid',
                'level'       => 'Beginner Level',
                'date'        => '02-May-26 12:00:00',
                'duration'    => '60 min',
                'language'    => 'Hindi',
                'recording'   => 'buy',
                'thumbnail'   => 'https://img.freepik.com/free-vector/business-data-analysis_23-2150175039.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 103,
                'title'       => 'Trading Breakout via Price OI Percentile!',
                'status'      => 'past',
                'price'       => 999,
                'mrp'         => 999,
                'discount'    => null,
                'type'        => 'paid',
                'level'       => 'Beginner Level',
                'date'        => '25-Apr-26 12:00:00',
                'duration'    => '1:30 hr',
                'language'    => 'Hindi',
                'recording'   => 'buy',
                'thumbnail'   => 'https://img.freepik.com/free-vector/candlestick-chart-concept-illustration_114360-9504.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 104,
                'title'       => 'Builtup Cycle Swing Trading!',
                'status'      => 'past',
                'price'       => 999,
                'mrp'         => 999,
                'discount'    => null,
                'type'        => 'paid',
                'level'       => 'Beginner Level',
                'date'        => '18-Apr-26 12:00:00',
                'duration'    => '60 min',
                'language'    => 'Hindi',
                'recording'   => 'buy',
                'thumbnail'   => 'https://img.freepik.com/free-vector/investment-concept-illustration_114360-3897.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 105,
                'title'       => 'Master Trade Execution with Quantsapp',
                'status'      => 'past',
                'price'       => 0,
                'mrp'         => 999,
                'discount'    => '100% off',
                'type'        => 'free',
                'level'       => 'Beginner Level',
                'date'        => '08-Oct-25 21:00:00',
                'duration'    => '60 min',
                'language'    => 'Hindi',
                'recording'   => 'available',
                'thumbnail'   => 'https://img.freepik.com/free-vector/trading-concept-illustration_114360-1998.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 106,
                'title'       => 'Webinar on Quantsapp Python Order API',
                'status'      => 'past',
                'price'       => 0,
                'mrp'         => 999,
                'discount'    => '100% off',
                'type'        => 'free',
                'level'       => 'Intermediate Level',
                'date'        => '18-Jul-25 12:30:00',
                'duration'    => '60 min',
                'language'    => 'Hindi',
                'recording'   => 'available',
                'thumbnail'   => 'https://img.freepik.com/free-vector/programmer-concept-illustration_114360-2284.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 107,
                'title'       => 'How to Trade Stock Options',
                'status'      => 'past',
                'price'       => 0,
                'mrp'         => 999,
                'discount'    => '100% off',
                'type'        => 'free',
                'level'       => 'Beginner Level',
                'date'        => '17-Nov-24 12:00:00',
                'duration'    => '60 min',
                'language'    => 'Hindi',
                'recording'   => 'available',
                'thumbnail'   => 'https://img.freepik.com/free-vector/finance-concept-illustration_114360-1623.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 108,
                'title'       => 'Positional Option Trading Strategy — In Hindi',
                'status'      => 'past',
                'price'       => 0,
                'mrp'         => 999,
                'discount'    => '100% off',
                'type'        => 'free',
                'level'       => 'Beginner Level',
                'date'        => '20-Mar-24 18:00:00',
                'duration'    => '90 min',
                'language'    => 'Hindi',
                'recording'   => 'available',
                'thumbnail'   => 'https://img.freepik.com/free-vector/stock-exchange-concept-illustration_114360-1457.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 109,
                'title'       => 'Intraday Options Trading w/Derivative Analytics — In Hindi',
                'status'      => 'past',
                'price'       => 0,
                'mrp'         => 999,
                'discount'    => '100% off',
                'type'        => 'free',
                'level'       => 'Beginner Level',
                'date'        => '13-Mar-24 18:00:00',
                'duration'    => '60 min',
                'language'    => 'Hindi',
                'recording'   => 'available',
                'thumbnail'   => 'https://img.freepik.com/free-vector/analytics-concept-illustration_114360-1438.jpg?w=600',
                'url'         => '#',
            ],
        ];

        return view($this->activeTemplate . 'webinars', compact(
            'pageTitle',
            'heroBanner',
            'languages',
            'proficiency',
            'categories',
            'upcomingWebinars',
            'pastWebinars'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COURSES  (add this method inside HomePageController)
    // ─────────────────────────────────────────────────────────────────────────
    public function courses()
    {
        $pageTitle = 'Option Courses';

        // ── HERO BANNER ─────────────────────────────────
        $heroBanner = [
            'title'       => 'Learn Option',
            'description' => 'Looking forward to enhancing your knowledge and practical insights, which will act as an enabler for your trading in the derivatives market. By enrolling in these option courses, you\'ll have the opportunity to enhance your options trading skills and with pragmatic insights gained from these, it should enhance your trading style. Understanding when and how to buy options or sell options (option writing) is a critical aspect of profitable trading. The group of option greeks should be strong for options trading to fructify into successful trading. Gain a deep understanding of how options work and the confidence to execute trades in a more judicious manner.',
            'banners'     => [
                'https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=400',
                'https://img.freepik.com/free-vector/online-trading-concept-illustration_114360-4766.jpg?w=400',
                'https://img.freepik.com/free-vector/investment-concept-illustration_114360-3897.jpg?w=400',
                'https://img.freepik.com/free-vector/finance-concept-illustration_114360-1623.jpg?w=400',
            ],
        ];

        // ── FILTER OPTIONS ──────────────────────────────
        $languages   = ['Hindi', 'English', 'Gujarati'];
        $proficiency = ['Beginner', 'Intermediate', 'Advanced'];

        // ── CATEGORY PILLS ──────────────────────────────
        $categories = [
            'Futures and Options Trading Course',
            'Options Trading for Beginners',
            'Options Trading Course',
            'Symposium',
            'Option Buying',
            'Option Selling',
            'Option Writer',
            'Long Volatility',
            'Short Volatility',
            'Positional Options',
            'Options Trading Strategies',
            'Volatility Strategies',
            'Future and Options Trading course',
            'Best Option Trading course in India',
            'Best Option Trading Course in India',
        ];

        // ── COURSES ─────────────────────────────────────
        // status: upcoming | ongoing | recorded
        // mode:   online | offline
        // type:   paid | free
        $courses = [
            [
                'id'         => 1,
                'title'      => 'Intraday Options Trading Course Batch 6',
                'mode'       => 'Online',
                'status'     => 'upcoming',
                'type'       => 'paid',
                'level'      => 'Intermediate',
                'language'   => 'Hindi',
                'duration'   => '1 Month (Mon, Wed)',
                'sessions'   => '8 Sessions, 2hrs /.',
                'date'       => '21-Jun-26 (19:00-20:00)',
                'price'      => 29999,
                'mrp'        => 49999,
                'discount'   => '40% off',
                'recording'  => null,
                'thumbnail'  => 'https://img.freepik.com/free-vector/gradient-stock-market-concept-illustration_23-2149166705.jpg?w=600',
                'url'        => '#',
                'tag_color'  => 'upcoming',
            ],
            [
                'id'         => 2,
                'title'      => 'Pro Options Program Batch 7',
                'mode'       => 'Online',
                'status'     => 'upcoming',
                'type'       => 'paid',
                'level'      => 'Intermediate',
                'language'   => 'Hindi',
                'duration'   => '3 Months (Tue, Thu)',
                'sessions'   => '24 Sessions, 2hrs /.',
                'date'       => '23-Jun-26 (19:00-20:00)',
                'price'      => 47999,
                'mrp'        => 79999,
                'discount'   => null,
                'recording'  => null,
                'thumbnail'  => 'https://img.freepik.com/free-vector/online-trading-concept-illustration_114360-4766.jpg?w=600',
                'url'        => '#',
                'tag_color'  => 'upcoming',
            ],
            [
                'id'         => 3,
                'title'      => 'Pro Options Program Batch 6',
                'mode'       => 'Online',
                'status'     => 'ongoing',
                'type'       => 'paid',
                'level'      => 'Intermediate',
                'language'   => 'Hindi',
                'duration'   => '3 Months (Sat, Sun)',
                'sessions'   => '24 Sessions, 2hrs /.',
                'date'       => '03-May-26 (11:00-13:00)',
                'price'      => 52999,
                'mrp'        => 79999,
                'discount'   => '25% off',
                'recording'  => null,
                'thumbnail'  => 'https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=600',
                'url'        => '#',
                'tag_color'  => 'ongoing',
            ],
            [
                'id'         => 4,
                'title'      => 'Option Writer Certification Batch 11',
                'mode'       => 'Online',
                'status'     => 'ongoing',
                'type'       => 'paid',
                'level'      => 'Intermediate',
                'language'   => 'Hindi',
                'duration'   => '1 Month (Mon, Wed)',
                'sessions'   => '8 Sessions, 2hrs /.',
                'date'       => '04-May-26 (19:00)',
                'price'      => 34999,
                'mrp'        => 49999,
                'discount'   => '30% off',
                'recording'  => null,
                'thumbnail'  => 'https://img.freepik.com/free-vector/investment-concept-illustration_114360-3897.jpg?w=600',
                'url'        => '#',
                'tag_color'  => 'ongoing',
            ],
            [
                'id'         => 5,
                'title'      => 'Market Foresight',
                'mode'       => 'Online',
                'status'     => 'ongoing',
                'type'       => 'paid',
                'level'      => 'Advanced',
                'language'   => 'English',
                'duration'   => '12 Months (fri)',
                'sessions'   => '224 Sessions, 1hrs /.',
                'date'       => '(11:00-20:00)',
                'price'      => 136490,
                'mrp'        => 180000,
                'discount'   => '24% off',
                'recording'  => null,
                'thumbnail'  => 'https://img.freepik.com/free-vector/business-data-analysis_23-2150175039.jpg?w=600',
                'url'        => '#',
                'tag_color'  => 'ongoing',
            ],
            [
                'id'         => 6,
                'title'      => 'Option Certification Level 2 Batch 17',
                'mode'       => 'Online',
                'status'     => 'ongoing',
                'type'       => 'paid',
                'level'      => 'Intermediate',
                'language'   => 'Hindi',
                'duration'   => '3 Months (Sat, Sun)',
                'sessions'   => '24 Sessions, 2hrs /.',
                'date'       => null,
                'price'      => 49999,
                'mrp'        => 70799,
                'discount'   => '29% off',
                'recording'  => null,
                'thumbnail'  => 'https://img.freepik.com/free-vector/candlestick-chart-concept-illustration_114360-9504.jpg?w=600',
                'url'        => '#',
                'tag_color'  => 'ongoing',
            ],
            [
                'id'         => 7,
                'title'      => 'Intraday Options Trading Course Batch 5',
                'mode'       => 'Online',
                'status'     => 'recorded',
                'type'       => 'paid',
                'level'      => 'Advanced',
                'language'   => 'Hindi',
                'duration'   => '1 Month (Mon, Wed)',
                'sessions'   => '8 Sessions, 2hrs /.',
                'date'       => null,
                'price'      => 34999,
                'mrp'        => 49999,
                'discount'   => '30% off',
                'recording'  => 'Available',
                'thumbnail'  => 'https://img.freepik.com/free-vector/trading-concept-illustration_114360-1998.jpg?w=600',
                'url'        => '#',
                'tag_color'  => null,
            ],
            [
                'id'         => 8,
                'title'      => 'Option Writer Certification Batch 10',
                'mode'       => 'Online',
                'status'     => 'recorded',
                'type'       => 'paid',
                'level'      => 'Advanced',
                'language'   => 'Hindi',
                'duration'   => '1 Month (Mon, Wed)',
                'sessions'   => '9 Sessions, 2hrs /.',
                'date'       => null,
                'price'      => 47999,
                'mrp'        => 49999,
                'discount'   => '4% off',
                'recording'  => 'Available',
                'thumbnail'  => 'https://img.freepik.com/free-vector/finance-concept-illustration_114360-1623.jpg?w=600',
                'url'        => '#',
                'tag_color'  => null,
            ],
            [
                'id'         => 9,
                'title'      => 'Pro Options Program Batch 4',
                'mode'       => 'Online',
                'status'     => 'recorded',
                'type'       => 'paid',
                'level'      => 'Intermediate',
                'language'   => 'Hindi',
                'duration'   => '3 Months (Sat, Sun)',
                'sessions'   => '24 Sessions, 2hrs /.',
                'date'       => null,
                'price'      => 52999,
                'mrp'        => 70799,
                'discount'   => '25% off',
                'recording'  => 'Available',
                'thumbnail'  => 'https://img.freepik.com/free-vector/algorithmic-trading-concept-illustration_114360-5482.jpg?w=600',
                'url'        => '#',
                'tag_color'  => null,
            ],
            [
                'id'         => 10,
                'title'      => 'Option Symposium 7.0',
                'mode'       => 'Online',
                'status'     => 'recorded',
                'type'       => 'paid',
                'level'      => 'Advanced',
                'language'   => 'English',
                'duration'   => '1 Month',
                'sessions'   => '9 Sessions, 2hrs /.',
                'date'       => null,
                'price'      => 13099,
                'mrp'        => 14000,
                'discount'   => '21% off',
                'recording'  => 'Available',
                'thumbnail'  => 'https://img.freepik.com/free-vector/financial-analytics-concept-illustration_114360-6079.jpg?w=600',
                'url'        => '#',
                'tag_color'  => null,
            ],
            [
                'id'         => 11,
                'title'      => 'Option Certification Level 1 Batch 8',
                'mode'       => 'Online',
                'status'     => 'recorded',
                'type'       => 'paid',
                'level'      => 'Beginner',
                'language'   => 'Hindi',
                'duration'   => '1 Month',
                'sessions'   => '6 Sessions, 2hrs /.',
                'date'       => null,
                'price'      => 10498,
                'mrp'        => 19800,
                'discount'   => '47% off',
                'recording'  => 'Available',
                'thumbnail'  => 'https://img.freepik.com/free-vector/programmer-concept-illustration_114360-2284.jpg?w=600',
                'url'        => '#',
                'tag_color'  => null,
            ],
            [
                'id'         => 12,
                'title'      => 'Option Symposium 6.0 (2024)',
                'mode'       => 'Online',
                'status'     => 'recorded',
                'type'       => 'paid',
                'level'      => 'Intermediate',
                'language'   => 'English',
                'duration'   => '1 Month',
                'sessions'   => '21 Sessions, 1hrs /.',
                'date'       => null,
                'price'      => 10498,
                'mrp'        => 14000,
                'discount'   => '50% off',
                'recording'  => 'Available',
                'thumbnail'  => 'https://img.freepik.com/free-vector/market-launch-concept-illustration_114360-5508.jpg?w=600',
                'url'        => '#',
                'tag_color'  => null,
            ],
            [
                'id'         => 13,
                'title'      => 'Option Symposium 5.0',
                'mode'       => 'Online',
                'status'     => 'recorded',
                'type'       => 'paid',
                'level'      => 'Intermediate',
                'language'   => 'English',
                'duration'   => '1 Month',
                'sessions'   => '14 Sessions, 3hrs /.',
                'date'       => null,
                'price'      => 10498,
                'mrp'        => 79999,
                'discount'   => '5% off',
                'recording'  => 'Available',
                'thumbnail'  => 'https://img.freepik.com/free-vector/stock-exchange-concept-illustration_114360-1457.jpg?w=600',
                'url'        => '#',
                'tag_color'  => null,
            ],
            [
                'id'         => 14,
                'title'      => 'Option Symposium 4.0',
                'mode'       => 'Online',
                'status'     => 'recorded',
                'type'       => 'paid',
                'level'      => 'Intermediate',
                'language'   => 'English',
                'duration'   => '1 Month',
                'sessions'   => '14 Sessions, 2hrs /.',
                'date'       => null,
                'price'      => 10498,
                'mrp'        => 11000,
                'discount'   => '5% off',
                'recording'  => 'Available',
                'thumbnail'  => 'https://img.freepik.com/free-vector/analytics-concept-illustration_114360-1438.jpg?w=600',
                'url'        => '#',
                'tag_color'  => null,
            ],
            [
                'id'         => 15,
                'title'      => 'Option Symposium 3.0',
                'mode'       => 'Online',
                'status'     => 'recorded',
                'type'       => 'paid',
                'level'      => 'Advanced',
                'language'   => 'English',
                'duration'   => '1 Month',
                'sessions'   => '11 Sessions, 2hrs /.',
                'date'       => null,
                'price'      => 10498,
                'mrp'        => 14900,
                'discount'   => '30% off',
                'recording'  => 'Available',
                'thumbnail'  => 'https://img.freepik.com/free-vector/webinar-concept-illustration_114360-4798.jpg?w=600',
                'url'        => '#',
                'tag_color'  => null,
            ],
            [
                'id'         => 16,
                'title'      => 'Trading Futures & Options using Jodi Bhav',
                'mode'       => 'Online',
                'status'     => 'recorded',
                'type'       => 'paid',
                'level'      => 'Intermediate',
                'language'   => 'English',
                'duration'   => '1 Month',
                'sessions'   => '6 Sessions, 1hrs /.',
                'date'       => null,
                'price'      => 10498,
                'mrp'        => 14900,
                'discount'   => '30% off',
                'recording'  => 'Available',
                'thumbnail'  => 'https://img.freepik.com/free-vector/business-data-analysis_23-2150175039.jpg?w=600',
                'url'        => '#',
                'tag_color'  => null,
            ],
            [
                'id'         => 17,
                'title'      => 'Easy Options Trading Course',
                'mode'       => 'Online',
                'status'     => 'recorded',
                'type'       => 'paid',
                'level'      => 'Beginner',
                'language'   => 'Hindi',
                'duration'   => '1 Month',
                'sessions'   => '20 Sessions, 1hrs /.',
                'date'       => null,
                'price'      => 3539,
                'mrp'        => null,
                'discount'   => null,
                'recording'  => 'Available',
                'thumbnail'  => 'https://img.freepik.com/free-vector/online-trading-concept-illustration_114360-4766.jpg?w=600',
                'url'        => '#',
                'tag_color'  => null,
            ],
            [
                'id'         => 18,
                'title'      => 'Build a successful Trading Strategy with OI PCR',
                'mode'       => 'Online',
                'status'     => 'recorded',
                'type'       => 'paid',
                'level'      => 'Intermediate',
                'language'   => 'English',
                'duration'   => '1 Month',
                'sessions'   => '6 Sessions, 1hrs /.',
                'date'       => null,
                'price'      => 10498,
                'mrp'        => 14900,
                'discount'   => '30% off',
                'recording'  => 'Available',
                'thumbnail'  => 'https://img.freepik.com/free-vector/investment-concept-illustration_114360-3897.jpg?w=600',
                'url'        => '#',
                'tag_color'  => null,
            ],
            [
                'id'         => 19,
                'title'      => 'Selling Options The High Strike Game',
                'mode'       => 'Online',
                'status'     => 'recorded',
                'type'       => 'paid',
                'level'      => 'Intermediate',
                'language'   => 'English',
                'duration'   => '1 Month',
                'sessions'   => '4 Sessions, 1hrs /.',
                'date'       => null,
                'price'      => 2099,
                'mrp'        => 2500,
                'discount'   => '16% off',
                'recording'  => 'Available',
                'thumbnail'  => 'https://img.freepik.com/free-vector/candlestick-chart-concept-illustration_114360-9504.jpg?w=600',
                'url'        => '#',
                'tag_color'  => null,
            ],
        ];

        return view($this->activeTemplate . 'courses', compact(
            'pageTitle',
            'heroBanner',
            'languages',
            'proficiency',
            'categories',
            'courses'
        ));
    }

     // ─────────────────────────────────────────────────────────────────────────
    // LOGIN / SIGN UP  (add this method inside HomePageController)
    // ─────────────────────────────────────────────────────────────────────────
    public function login()
    {
        $pageTitle = 'Login';
 
        // Feature pills shown on the right panel
        $features = [
            '25 Free Real Time Tools',
            '59 Premium Real Time Tools',
            '2 Option Algorithm',
        ];
 
        // Broker list for the auto-scrolling "Trade With" strip
        $brokers = [
            ['name' => 'Zerodha',      'letter' => 'Z', 'bg' => '#e53935'],
            ['name' => 'Upstox',       'letter' => 'U', 'bg' => '#7b1fa2'],
            ['name' => 'Dhan',         'letter' => 'D', 'bg' => '#00897b'],
            ['name' => '5Paisa',       'letter' => '5', 'bg' => '#455a64'],
            ['name' => 'Motilal Oswal','letter' => 'M', 'bg' => '#f57f17'],
            ['name' => 'Fyers',        'letter' => 'F', 'bg' => '#1565c0'],
            ['name' => 'Choice',       'letter' => 'C', 'bg' => '#6a1b9a'],
            ['name' => 'Aliceblue',    'letter' => 'A', 'bg' => '#00838f'],
            ['name' => 'Sharekhan',    'letter' => 'S', 'bg' => '#bf360c'],
            ['name' => 'Angel',        'letter' => 'A', 'bg' => '#2e7d32'],
            ['name' => 'Groww',        'letter' => 'G', 'bg' => '#00695c'],
            ['name' => 'ICICI',        'letter' => 'I', 'bg' => '#b71c1c'],
            ['name' => 'HDFC Sky',     'letter' => 'H', 'bg' => '#1a237e'],
            ['name' => 'Kotak',        'letter' => 'K', 'bg' => '#e65100'],
        ];
 
        // Promo video shown on right (YouTube embed or hosted video URL)
        $promoVideo = 'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1&mute=1&rel=0&modestbranding=1&controls=1';
 
        return view($this->activeTemplate . 'login', compact(
            'pageTitle',
            'features',
            'brokers',
            'promoVideo'
        ));
    }


    // ─────────────────────────────────────────────────────────────────────────
    // EVENTS  — add inside HomePageController
    // ─────────────────────────────────────────────────────────────────────────
    public function events()
    {
        $pageTitle = 'Events';

        // Empty array = shows "No upcoming events scheduled." (matches screenshot 1)
        // Populate when real events exist using the structure below.
        $events = [
            // [
            //     'id'        => 1,
            //     'title'     => 'Option Symposium 8.0',
            //     'date'      => '15 & 16 February 2026',
            //     'time'      => '9:00 AM – 6:00 PM',
            //     'location'  => 'Online',
            //     'price'     => 13099,
            //     'thumbnail' => 'https://img.freepik.com/...jpg',
            //     'url'       => route('optionsymposium'),
            // ],
        ];

        return view($this->activeTemplate . 'events', compact('pageTitle', 'events'));
    }


    // ─────────────────────────────────────────────────────────────────────────
    // OPTION SYMPOSIUM  — add inside HomePageController
    // ─────────────────────────────────────────────────────────────────────────
    public function optionSymposium()
    {
        $pageTitle = 'Option Symposium 7.0';

        $symposium = [

            // ── HERO ─────────────────────────────────────
            'title'       => 'Symposium 7.0',
            'date'        => '18th & 19th January 2025',
            'location'    => 'Online',
            'hero_image'  => 'https://img.freepik.com/free-vector/conference-concept-illustration_114360-1088.jpg?w=600',
            'about_image' => 'https://img.freepik.com/free-vector/business-conference-concept-illustration_114360-1537.jpg?w=400',

            // ── ABOUT ────────────────────────────────────
            'about' => [
                'Option Symposium 7.0 is Quantsapp\'s annual Conference on Options Trading that aims to bring together the country\'s top Option Fund Managers, Derivative Analysts, Option Trainers & Individual Option Traders to join hands & enlighten option enthusiasts with various Practical Options Trading styles and their winning strategies.',
                'The cornerstone of the conference is to discuss actionable strategies which can help traders enhance their trading tools for a higher probability of success.',
            ],

            // ── BENEFITS ─────────────────────────────────
            'benefits' => [
                ['icon' => 'fa-chart-line',    'value' => '10+',      'label' => 'Options Strategies'],
                ['icon' => 'fa-university',    'value' => 'SEBI',     'label' => 'Strategies for SEBI\'s New F&O Rules'],
                ['icon' => 'fa-user-graduate', 'value' => '15+',      'label' => 'Option Masters'],
                ['icon' => 'fa-laptop',        'value' => '2 DAY\'S', 'label' => 'ONLINE Event'],
            ],

            // ── SPEAKERS ─────────────────────────────────
            'speakers' => [
                ['name'=>'Shubham Agarwal',  'role'=>'CEO, Quantsapp',                     'creds'=>'CMT, CFA, CQF, CFTe', 'topic'=>'Intraday Options Buying: Strategies & Automation with API Bridge',       'avatar'=>'https://img.freepik.com/free-photo/young-businessman-white-shirt_144627-16510.jpg?w=200'],
                ['name'=>'Avani Bhatt',      'role'=>'Derivative Research Analyst Sr VP, JM Financial',    'creds'=>'',     'topic'=>'Stock Options: Which ones & Why?',                                       'avatar'=>'https://img.freepik.com/free-photo/businesswoman-posing_23-2148142978.jpg?w=200'],
                ['name'=>'Kushal Jain',      'role'=>'Co-founder of Algotix',               'creds'=>'',     'topic'=>'Index Options: Market Structure & Price Action',                                'avatar'=>'https://img.freepik.com/free-photo/portrait-man-laughing_23-2148859448.jpg?w=200'],
                ['name'=>'Subhadra Nandy',   'role'=>'Founder of Quantsapp',                'creds'=>'',     'topic'=>'Mastering Options Buying: Direction, Timing & Spread',                          'avatar'=>'https://img.freepik.com/free-photo/confident-businesswoman_23-2148152868.jpg?w=200'],
                ['name'=>'Tina Gadodia',     'role'=>'President, Quantsapp',                'creds'=>'',     'topic'=>'Derivative Analysis Using OI & Options Data',                                  'avatar'=>'https://img.freepik.com/free-photo/smiling-businesswoman-posing_23-2148142985.jpg?w=200'],
                ['name'=>'Bhavin Desai',     'role'=>'Full Time Trader',                    'creds'=>'',     'topic'=>'Trading Realized Volatility with Long Options',                                 'avatar'=>'https://img.freepik.com/free-photo/businessman-suit_1439-874.jpg?w=200'],
                ['name'=>'Jyoti Budhia',     'role'=>'Founder, B K Training',               'creds'=>'',     'topic'=>'Decoding the complexity of Option Chain',                                       'avatar'=>'https://img.freepik.com/free-photo/portrait-young-businesswoman_23-2149073960.jpg?w=200'],
                ['name'=>'Ronak Unadkat',    'role'=>'Full Time Trader',                    'creds'=>'',     'topic'=>'Index Trading Via Spreads & Rule based Adjustment',                             'avatar'=>'https://img.freepik.com/free-photo/man-business-suit_1439-879.jpg?w=200'],
                ['name'=>'Kapil Dhama',      'role'=>'Full Time Trader',                    'creds'=>'',     'topic'=>'Index Direction and Analysis of OI Data',                                      'avatar'=>'https://img.freepik.com/free-photo/portrait-smiling-man_23-2148859446.jpg?w=200'],
                ['name'=>'S Sandeep Rao',    'role'=>'SEBI Registered Research Analyst',   'creds'=>'',     'topic'=>'Intraday Range Prediction using Bank Nifty Options (SMF)',                     'avatar'=>'https://img.freepik.com/free-photo/close-up-portrait-young-business-man_171337-1094.jpg?w=200'],
                ['name'=>'Ankush Bajaj',     'role'=>'Trainer-Trader, Algo Ninja, Alfa Precision','creds'=>'','topic'=>'Mastering Intraday Options Buying: Strategies & Automation with API Bridge', 'avatar'=>'https://img.freepik.com/free-photo/handsome-young-businessman_23-2148176642.jpg?w=200'],
                ['name'=>'Tribhuvan Bisen',  'role'=>'Futures & Options Trainer',           'creds'=>'',     'topic'=>'Volatility Trading',                                                           'avatar'=>'https://img.freepik.com/free-photo/executive-man-smiling_23-2148348337.jpg?w=200'],
                ['name'=>'Apurv Pandey',     'role'=>'Full Time Trader',                    'creds'=>'',     'topic'=>'Using Synthetics to optimise Risk Management',                                 'avatar'=>'https://img.freepik.com/free-photo/young-handsome-man-business-suit_23-2148436386.jpg?w=200'],
                ['name'=>'Ashish Sahety',    'role'=>'Derivatives Expert, Investment Research Pv Ltd','creds'=>'','topic'=>'Implied Volatility & Bull Options',                                 'avatar'=>'https://img.freepik.com/free-photo/positive-young-man-office_23-2147661440.jpg?w=200'],
                ['name'=>'Ritesh Chavan',    'role'=>'Derivatives Expert',                  'creds'=>'',     'topic'=>'Vega-Driven Strategies for Result Season',                                     'avatar'=>'https://img.freepik.com/free-photo/businessman-sitting-by-table-cafe_1262-7011.jpg?w=200'],
            ],

            // ── SCHEDULE ─────────────────────────────────
            'schedule' => [
                [
                    'label'    => 'First Day',
                    'date'     => '18 Jan 26, Saturday',
                    'sessions' => [
                        ['time'=>'9:15 am – 10:00 am', 'duration'=>'15 min', 'speaker'=>'—',               'topic'=>'Event Initiation',                                                  'is_break'=>false],
                        ['time'=>'10:00 am – 11:00 am','duration'=>'1 hr',   'speaker'=>'Ritesh Chavan',   'topic'=>'Harnessing Volatility: Vega-Driven Strategies for Result Season',    'is_break'=>false],
                        ['time'=>'11:00 am – 12:00 pm','duration'=>'1 hr',   'speaker'=>'Ankush Bajaj',    'topic'=>'Mastering Intraday Options Buying: Strategies & Automation with API Bridge', 'is_break'=>false],
                        ['time'=>'12:00 pm – 1:00 pm', 'duration'=>'1 hr',   'speaker'=>'Tribhuvan Bisen', 'topic'=>'Volatility Trading',                                                'is_break'=>false],
                        ['time'=>'1:00 pm – 1:30 pm',  'duration'=>'30 mins','speaker'=>'—',               'topic'=>'Break',                                                             'is_break'=>true],
                        ['time'=>'1:30 pm – 2:30 pm',  'duration'=>'1 hr',   'speaker'=>'Jyoti Budhia',    'topic'=>'Decoding the complexity of Option Chain',                           'is_break'=>false],
                        ['time'=>'2:30 pm – 3:30 pm',  'duration'=>'1 hr',   'speaker'=>'Ronak Unadkat',   'topic'=>'Index Trading Via Spreads and Rule based Adjustment',               'is_break'=>false],
                        ['time'=>'3:30 pm – 4:30 pm',  'duration'=>'1 hr',   'speaker'=>'Shubham Agarwal', 'topic'=>'Keynote — Algo Driven Options Strategy',                           'is_break'=>false],
                        ['time'=>'4:30 pm – 5:00 pm',  'duration'=>'30 mins','speaker'=>'—',               'topic'=>'Break',                                                             'is_break'=>true],
                        ['time'=>'5:00 pm – 6:00 pm',  'duration'=>'1 hr',   'speaker'=>'Apurv Pandey',    'topic'=>'Using Synthetics to optimise Risk Management',                     'is_break'=>false],
                        ['time'=>'6:00 pm – 7:00 pm',  'duration'=>'1 hr',   'speaker'=>'Bhavin Desai',    'topic'=>'Trading Realized Volatility with Long Options',                    'is_break'=>false],
                    ],
                ],
                [
                    'label'    => 'Second Day',
                    'date'     => '19 Jan 26, Sunday',
                    'sessions' => [
                        ['time'=>'9:30 am – 10:30 am', 'duration'=>'1 hr',   'speaker'=>'Kapil Dhama',     'topic'=>'Index Direction and Analysis of OI Data',                          'is_break'=>false],
                        ['time'=>'10:30 am – 11:30 am','duration'=>'1 hr',   'speaker'=>'Kushal Jain',     'topic'=>'Index Options: Market Structure & Price Action',                    'is_break'=>false],
                        ['time'=>'11:30 am – 12:30 pm','duration'=>'1 hr',   'speaker'=>'S Sandeep Rao',   'topic'=>'Intraday Range Prediction using Bank Nifty Options (SMF)',         'is_break'=>false],
                        ['time'=>'12:30 pm – 1:00 pm', 'duration'=>'30 mins','speaker'=>'—',               'topic'=>'Break',                                                             'is_break'=>true],
                        ['time'=>'1:00 pm – 2:00 pm',  'duration'=>'1 hr',   'speaker'=>'Avani Bhatt',     'topic'=>'Stock Options: Which ones & Why?',                                 'is_break'=>false],
                        ['time'=>'2:00 pm – 3:00 pm',  'duration'=>'1 hr',   'speaker'=>'Ashish Sahety',   'topic'=>'Implied Volatility & Bull Options',                                'is_break'=>false],
                        ['time'=>'3:00 pm – 4:00 pm',  'duration'=>'1 hr',   'speaker'=>'Subhadra Nandy',  'topic'=>'Mastering Options Buying: Direction, Timing & Spread',             'is_break'=>false],
                        ['time'=>'4:00 pm – 4:30 pm',  'duration'=>'30 mins','speaker'=>'—',               'topic'=>'Break',                                                             'is_break'=>true],
                        ['time'=>'4:30 pm – 5:30 pm',  'duration'=>'1 hr',   'speaker'=>'Tina Gadodia',    'topic'=>'Derivative Analysis Using OI & Options Data',                      'is_break'=>false],
                        ['time'=>'5:30 pm – 6:30 pm',  'duration'=>'1 hr',   'speaker'=>'Tribhuvan Bisen', 'topic'=>'Volatility Trading — Advanced',                                    'is_break'=>false],
                    ],
                ],
            ],

            // ── PRICING ──────────────────────────────────
            'pricing_options' => [
                ['label' => 'E-Option Symposium 7.0', 'selected' => true],
                ['label' => 'None',                    'selected' => false],
            ],
            'pricing' => [
                'title'    => 'E-Option Symposium 7.0',
                'subtitle' => 'Online Event — 2 Days Full Access',
                'price'    => 13099,
                'mrp'      => 16000,
            ],

            // ── PAST EXPERTS ─────────────────────────────
            'past_experts' => [
                ['name'=>'Joanika Singh',    'role'=>'CFO, Quantsapp, Managing Director of Consulting LLP',     'avatar'=>'https://img.freepik.com/free-photo/businesswoman-posing_23-2148142978.jpg?w=150'],
                ['name'=>'Savrit Manjeet',   'role'=>'Trading Head, Derivative Consulting LLC',                  'avatar'=>'https://img.freepik.com/free-photo/portrait-man-laughing_23-2148859448.jpg?w=150'],
                ['name'=>'Pankaj Chiftlangia','role'=>'Chief Executive Officer, B Square Advisors',              'avatar'=>'https://img.freepik.com/free-photo/smiling-businesswoman-posing_23-2148142985.jpg?w=150'],
                ['name'=>'Amit Goel',         'role'=>'CEO, Finscope Pvt Ltd, IIT Delhi, Full Time Trader',     'avatar'=>'https://img.freepik.com/free-photo/young-businessman-white-shirt_144627-16510.jpg?w=150'],
                ['name'=>'Pran Katariya',     'role'=>'IT Sales, AIS & Full Time Trader',                       'avatar'=>'https://img.freepik.com/free-photo/confident-businesswoman_23-2148152868.jpg?w=150'],
                ['name'=>'Ashu Madan',        'role'=>'Director, Affiliate Group, Managing Director (MP)',       'avatar'=>'https://img.freepik.com/free-photo/smiling-businesswoman-posing_23-2148142985.jpg?w=150'],
                ['name'=>'Manoj M',           'role'=>'IT Syed',                                                 'avatar'=>'https://img.freepik.com/free-photo/executive-man-smiling_23-2148348337.jpg?w=150'],
                ['name'=>'Priya Shah',        'role'=>'Senior Derivatives Analyst, Fyers',                      'avatar'=>'https://img.freepik.com/free-photo/confident-businesswoman_23-2148152868.jpg?w=150'],
                ['name'=>'Rajan Mehta',       'role'=>'Options Educator, NSE Academy Certified',                'avatar'=>'https://img.freepik.com/free-photo/positive-young-man-office_23-2147661440.jpg?w=150'],
                ['name'=>'Kavita Verma',      'role'=>'Full Time Options Trader & Mentor',                      'avatar'=>'https://img.freepik.com/free-photo/portrait-young-businesswoman_23-2149073960.jpg?w=150'],
            ],

            // ── GALLERY ──────────────────────────────────
            'gallery' => [
                ['src'=>'https://img.freepik.com/free-photo/people-attending-business-conference_23-2148888868.jpg?w=600', 'year'=>'2023'],
                ['src'=>'https://img.freepik.com/free-photo/conference-event-audience_1048-1719.jpg?w=400',                'year'=>'2022'],
                ['src'=>'https://img.freepik.com/free-photo/audience-applauding-speaker-conference-hall_23-2148000015.jpg?w=400','year'=>'2022'],
                ['src'=>'https://img.freepik.com/free-photo/people-networking-professional-conference_23-2148888875.jpg?w=600','year'=>'2021'],
                ['src'=>'https://img.freepik.com/free-photo/medium-shot-man-speaking-crowd_23-2148888872.jpg?w=400',       'year'=>'2021'],
                ['src'=>'https://img.freepik.com/free-photo/close-up-group-people-sitting-conference_1048-3068.jpg?w=400', 'year'=>'2020'],
            ],
        ];

        return view($this->activeTemplate . 'optionsymposium', compact('pageTitle', 'symposium'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VIDEO LIBRARY  — add inside HomePageController
    // ─────────────────────────────────────────────────────────────────────────
    public function videoLibrary()
    {
        $pageTitle = 'Video Library';

        // ── FILTER OPTIONS ──────────────────────────────
        $languages   = ['Hindi', 'English', 'Gujarati'];
        $proficiency = ['Beginner', 'Intermediate', 'Advanced'];
        $speakers    = ['Shubham Agarwal', 'Varun Shetty', 'Bhavin Desai', 'Dijeet Uppal'];
        $sorts       = ['Latest', 'Most Viewed', 'Most Liked', 'Oldest'];

        // ── LEFT SIDEBAR CATEGORIES ──────────────────────
        $sidebar = [
            'Track' => [
                ['label' => 'Futures OI',     'count' => 12],
                ['label' => 'Options OI',     'count' => 18],
                ['label' => 'Price & Volume', 'count' => 9],
                ['label' => 'Alerts',         'count' => 5],
            ],
            'Analyze' => [
                ['label' => 'Charts',         'count' => 14],
                ['label' => 'IV',             'count' => 8],
                ['label' => 'PCR',            'count' => 6],
                ['label' => 'Expiry',         'count' => 11],
                ['label' => 'Price & Volume', 'count' => 7],
            ],
            'Scan' => [
                ['label' => 'Movers',         'count' => 4],
                ['label' => 'OI Buildup',     'count' => 6],
            ],
        ];

        // ── TOP PILLS ───────────────────────────────────
        $pills = [
            'Volume Analysis','Banknifty','Optiond Trading','Option Trading',
            'Pullback','Options Strategies','Nifty Prediction','Optionstrading',
            'Options GTrading','Future','Built-up','Option Buying','Option Selling',
            'Options Trading','Volume','Nifty Analysis','Orderbook','Intraday',
            'Indicator','Implied Volatility(IV)','Technical Chart','Intra Day',
            'Option Strategies','Technical Indicators','EMA','Moving Average',
        ];

        // ── VIDEOS ──────────────────────────────────────
        $videos = [
            [
                'id'          => 1,
                'title'       => 'Buy Low',
                'emoji'       => '⚡',
                'likes'       => 814,
                'views'       => 302822,
                'age'         => '24 months ago',
                'description' => 'Option traders are found diligently monitoring market movers such as Nifty, BankNifty, or specific stocks listed in NSE\'s F&O segment. This pursuit demands a combination of technical analysis skills and an understanding of options trading...',
                'level'       => 'Intermediate Level',
                'language'    => 'Hindi',
                'duration'    => '',
                'speaker'     => '',
                'lang_key'    => 'hindi',
                'level_key'   => 'intermediate',
                'thumbnail'   => 'https://img.freepik.com/free-vector/gradient-stock-market-concept-illustration_23-2149166705.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 2,
                'title'       => 'The Power of the New Orderbook Quant Indicator: Insights for Traders',
                'emoji'       => '📊',
                'likes'       => 553,
                'views'       => 24690,
                'age'         => '16 months ago',
                'description' => 'Quant Indicator which searches for securities or indices where the call writers or put writers are getting trapped, with prices of options...',
                'level'       => 'Beginner Level',
                'language'    => 'English',
                'duration'    => '04:25 hr',
                'speaker'     => 'Dijeet Uppal',
                'lang_key'    => 'english',
                'level_key'   => 'beginner',
                'thumbnail'   => 'https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 3,
                'title'       => 'Order Book Data',
                'emoji'       => '📈',
                'likes'       => 7909,
                'views'       => 63616,
                'age'         => '24 months ago',
                'description' => 'Every option trader in the Indian stock markets aspires to detect genuine price breakouts and steer clear of false signals, especially in indices such as Nifty or BankNifty...',
                'level'       => 'Intermediate Level',
                'language'    => 'Hindi',
                'duration'    => '',
                'speaker'     => '',
                'lang_key'    => 'hindi',
                'level_key'   => 'intermediate',
                'thumbnail'   => 'https://img.freepik.com/free-vector/candlestick-chart-concept-illustration_114360-9504.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 4,
                'title'       => 'Relative Performance Graph',
                'emoji'       => '📉',
                'likes'       => 3393,
                'views'       => 302822,
                'age'         => '24 months ago',
                'description' => 'Option traders and other derivative traders on NSE would like to pre-empt the large moves which are about to unfold in any particular underlying, especially stocks in F&O...',
                'level'       => 'Intermediate Level',
                'language'    => 'Hindi',
                'duration'    => '13:12 hr',
                'speaker'     => 'Shubham Agarwal',
                'lang_key'    => 'hindi',
                'level_key'   => 'intermediate',
                'thumbnail'   => 'https://img.freepik.com/free-vector/financial-analytics-concept-illustration_114360-6079.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 5,
                'title'       => 'Trap Indicator',
                'emoji'       => '↗',
                'likes'       => 746,
                'views'       => 33006,
                'age'         => '24 months ago',
                'description' => 'Trap Indicator is a proprietary algorithm of Quantsapp which searches for securities or indices where the call writers or put writers are getting trapped, with prices of options...',
                'level'       => 'Intermediate Level',
                'language'    => 'English',
                'duration'    => '01:11 hr',
                'speaker'     => 'Shubham Agarwal',
                'lang_key'    => 'english',
                'level_key'   => 'intermediate',
                'thumbnail'   => 'https://img.freepik.com/free-vector/business-data-analysis_23-2150175039.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 6,
                'title'       => 'Option Writer',
                'emoji'       => '✍',
                'likes'       => 1434,
                'views'       => 63616,
                'age'         => '24 months ago',
                'description' => 'Option writing involves predicting the ideal trading range for the underlying asset, which may include indices such as Nifty, BankNifty, or stocks listed in the F&O segment...',
                'level'       => 'Intermediate Level',
                'language'    => 'Hindi',
                'duration'    => '13 hr',
                'speaker'     => 'Shubham Agarwal',
                'lang_key'    => 'hindi',
                'level_key'   => 'intermediate',
                'thumbnail'   => 'https://img.freepik.com/free-vector/analytics-concept-illustration_114360-1438.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 7,
                'title'       => 'Put Call Ratio',
                'emoji'       => '⚖',
                'likes'       => 184,
                'views'       => 6885,
                'age'         => '24 months ago',
                'description' => 'When determining whether the put side or call side is dominant, the Put-Call Ratio (PCR) serves as a valuable tool for interpreting the sentiment within derivative markets...',
                'level'       => 'Intermediate Level',
                'language'    => 'Hindi',
                'duration'    => '05:38 hr',
                'speaker'     => 'Varun Shetty',
                'lang_key'    => 'hindi',
                'level_key'   => 'intermediate',
                'thumbnail'   => 'https://img.freepik.com/free-vector/investment-concept-illustration_114360-3897.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 8,
                'title'       => 'Volatility Trader',
                'emoji'       => '↗',
                'likes'       => 802,
                'views'       => 35253,
                'age'         => '24 months ago',
                'description' => 'Greed and fear are factors that contribute to markets\' overall investing mentality or sentiment, influenced by behavioral biases. Volatility in Options trading is...',
                'level'       => 'Intermediate Level',
                'language'    => 'Hindi',
                'duration'    => '04:27 hr',
                'speaker'     => 'Shubham Agarwal',
                'lang_key'    => 'hindi',
                'level_key'   => 'intermediate',
                'thumbnail'   => 'https://img.freepik.com/free-vector/trading-concept-illustration_114360-1998.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 9,
                'title'       => 'Sector Seasonality',
                'emoji'       => '🗓',
                'likes'       => 240,
                'views'       => 6815,
                'age'         => '23 months ago',
                'description' => 'Stock market seasonality pertains to the recurring patterns and trends observed in market behaviour, including fluctuations in performance, trading volume, and various other indicators...',
                'level'       => 'Beginner Level',
                'language'    => 'Hindi',
                'duration'    => '08:43 hr',
                'speaker'     => 'Varun Shetty',
                'lang_key'    => 'hindi',
                'level_key'   => 'beginner',
                'thumbnail'   => 'https://img.freepik.com/free-vector/stock-exchange-concept-illustration_114360-1457.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 10,
                'title'       => 'Intraday Movers',
                'emoji'       => '⏱',
                'likes'       => 365,
                'views'       => 14228,
                'age'         => '24 months ago',
                'description' => 'Intraday Movers tool within Quantsapp empowers option traders and derivative traders on the NSE to assess the leading performers in the derivatives segment...',
                'level'       => 'Beginner Level',
                'language'    => 'Hindi',
                'duration'    => '07:09 hr',
                'speaker'     => 'Varun Shetty',
                'lang_key'    => 'hindi',
                'level_key'   => 'beginner',
                'thumbnail'   => 'https://img.freepik.com/free-vector/market-launch-concept-illustration_114360-5508.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 11,
                'title'       => 'Optimizer: Find an Optimal Strategy for your Option Trading Forecast',
                'emoji'       => '🔍',
                'likes'       => 722,
                'views'       => 27017,
                'age'         => '22 months ago',
                'description' => 'Why is options trading different from shares trading or futures trading? Options are financial tools with non-linear payoffs, particularly influenced by movements in the...',
                'level'       => 'Beginner Level',
                'language'    => 'Hindi',
                'duration'    => '06:11 hr',
                'speaker'     => 'Shubham Agarwal',
                'lang_key'    => 'hindi',
                'level_key'   => 'beginner',
                'thumbnail'   => 'https://img.freepik.com/free-vector/online-trading-concept-illustration_114360-4766.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 12,
                'title'       => 'Trade Recap',
                'emoji'       => '🔄',
                'likes'       => 14,
                'views'       => 878,
                'age'         => '24 months ago',
                'description' => 'Option traders, both in the Indian stock markets and worldwide, dedicate a significant amount of time and effort to forecasting the direction of Nifty or the index fo...',
                'level'       => 'Intermediate Level',
                'language'    => 'Hindi',
                'duration'    => '01:02 hr',
                'speaker'     => 'Varun Shetty',
                'lang_key'    => 'hindi',
                'level_key'   => 'intermediate',
                'thumbnail'   => 'https://img.freepik.com/free-vector/finance-concept-illustration_114360-1623.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 13,
                'title'       => 'Open Interest',
                'emoji'       => '📋',
                'likes'       => 610,
                'views'       => 19061,
                'age'         => '24 months ago',
                'description' => 'Open interest represents the quantity of unsettled or outstanding contracts for a specific derivative instrument, serving as a metric to assess trader involvement in an...',
                'level'       => 'Intermediate Level',
                'language'    => 'Hindi',
                'duration'    => '15:19 hr',
                'speaker'     => 'Varun Shetty',
                'lang_key'    => 'hindi',
                'level_key'   => 'intermediate',
                'thumbnail'   => 'https://img.freepik.com/free-vector/programmer-concept-illustration_114360-2284.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 14,
                'title'       => 'Options Architect',
                'emoji'       => '⚙',
                'likes'       => 809,
                'views'       => 31715,
                'age'         => '24 months ago',
                'description' => 'Options are non-linear payoff products, where the payoffs of an options trading strategy on NSE is not just dependent on price, but also on time to expiration of the...',
                'level'       => 'Intermediate Level',
                'language'    => 'Hindi',
                'duration'    => '08:23 hr',
                'speaker'     => 'Varun Shetty',
                'lang_key'    => 'hindi',
                'level_key'   => 'intermediate',
                'thumbnail'   => 'https://img.freepik.com/free-vector/algorithmic-trading-concept-illustration_114360-5482.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 15,
                'title'       => 'Order & Trade Watchlist',
                'emoji'       => '📋',
                'likes'       => 458,
                'views'       => 17273,
                'age'         => '24 months ago',
                'description' => 'O&T Watch List. Options are non-linear payoff products, where the payoffs of an options trading strategy...',
                'level'       => 'Intermediate Level',
                'language'    => 'Hindi',
                'duration'    => '07:48 hr',
                'speaker'     => 'Varun Shetty',
                'lang_key'    => 'hindi',
                'level_key'   => 'intermediate',
                'thumbnail'   => 'https://img.freepik.com/free-vector/webinar-concept-illustration_114360-4798.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 16,
                'title'       => 'Advance Decline Chart',
                'emoji'       => '↗',
                'likes'       => 73,
                'views'       => 2771,
                'age'         => '24 months ago',
                'description' => 'The Advance Decline chart, a tool within Quantsapp utilized in the Indian stock market, gauges the breadth of a market\'s movement. It aids investors and traders in...',
                'level'       => 'Intermediate Level',
                'language'    => 'Hindi',
                'duration'    => '01:55 hr',
                'speaker'     => 'Varun Shetty',
                'lang_key'    => 'hindi',
                'level_key'   => 'intermediate',
                'thumbnail'   => 'https://img.freepik.com/free-vector/gradient-stock-market-concept-illustration_23-2149166705.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 17,
                'title'       => 'Ready for Breakouts? | Track Participation to Validate Moves',
                'emoji'       => '📊',
                'likes'       => 675,
                'views'       => 23084,
                'age'         => '19 months ago',
                'description' => 'Options trading entails risk, necessitating traders to develop a comprehensive strategy, risk management plan, and thorough understanding of market conditions...',
                'level'       => 'Beginner Level',
                'language'    => 'Gujarati',
                'duration'    => '13:14 hr',
                'speaker'     => 'Bhavin Desai',
                'lang_key'    => 'gujarati',
                'level_key'   => 'beginner',
                'thumbnail'   => 'https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=600',
                'url'         => '#',
            ],
            [
                'id'          => 18,
                'title'       => 'Participant Data',
                'emoji'       => '🛒',
                'likes'       => 135,
                'views'       => 4645,
                'age'         => '24 months ago',
                'description' => 'Participant data plays a crucial role in comprehending and analyzing the operations of Indian stock and derivative markets. It serves as a fundamental aspect for making...',
                'level'       => 'Beginner Level',
                'language'    => 'Hindi',
                'duration'    => '02:11 hr',
                'speaker'     => 'Varun Shetty',
                'lang_key'    => 'hindi',
                'level_key'   => 'beginner',
                'thumbnail'   => 'https://img.freepik.com/free-vector/business-data-analysis_23-2150175039.jpg?w=600',
                'url'         => '#',
            ],
        ];

        return view($this->activeTemplate . 'video-library', compact(
            'pageTitle',
            'languages',
            'proficiency',
            'speakers',
            'sorts',
            'sidebar',
            'pills',
            'videos'
        ));
    }
}
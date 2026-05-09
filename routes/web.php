<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\ZerodhaAuthController;
use App\Http\Controllers\User\FuturesController;
use App\Http\Controllers\User\IndicatorConfigController;
use App\Http\Controllers\User\BacktestProfitController;
use App\Http\Controllers\User\ExpiryController;
use App\Http\Controllers\User\ExpiryConfigController;
use App\Http\Controllers\User\ExpiryAutoConfigController;
use App\Http\Controllers\User\ZerodhaBrokerController;
use App\Http\Controllers\User\SymbolController;
use App\Http\Controllers\User\SymbolBacktestProfitController;
use App\Http\Controllers\User\ZerodhaPortfolioController;
use App\Http\Controllers\User\CustomBehaviorController;
use App\Http\Controllers\User\AutoTargetOrderController;
use App\Http\Controllers\User\SymbolControllerNew;
use App\Http\Controllers\User\SymbolOnePercentController;
use App\Http\Controllers\User\OptionOIAnalysisController;
use App\Http\Controllers\User\ManualOrderController;
use App\Http\Controllers\User\OnePercentAutoController;
use App\Http\Controllers\User\SymbolCamarillaController;
use App\Http\Controllers\User\SymbolPivotPointController;
use App\Http\Controllers\User\OptionIVController;
use App\Http\Controllers\User\OIIVAutoController;
use App\Http\Controllers\User\OIChangeAnalysisController;
use App\Http\Controllers\User\OIIVIndividualStrikeController;
use App\Http\Controllers\User\PortfolioConfigController;
use App\Http\Controllers\User\PortfolioSellOrderConfigController;
use App\Http\Controllers\User\BrokerAmoConfigController;
use App\Http\Controllers\User\BrokerSellOrderConfigController;
use App\Http\Controllers\User\BrokerLiveLtpConfigController;
use App\Http\Controllers\User\OIIVAuto9to12Controller;
use App\Http\Controllers\User\IndexOIController;
use App\Http\Controllers\User\OiMaCrossoverController;
use App\Http\Controllers\User\TradeBookController;
use App\Http\Controllers\User\OptionSignalController;
use App\Http\Controllers\User\OIStrikeAnalysisController;
use App\Http\Controllers\User\OhlcDataViewerController;
use App\Http\Controllers\User\PivotPointsController;
use App\Http\Controllers\User\PivotOrderConfigController;
use App\Http\Controllers\User\PivotNormalOrderConfigController;
use App\Http\Controllers\User\FutOpenHighLowController;
use App\Http\Controllers\User\McxOiivAutoController;
use App\Http\Controllers\User\FutOhlAutoController;
use App\Http\Controllers\User\OIIVAutoOHLCController;
use App\Http\Controllers\User\PivotSignalController;
use App\Http\Controllers\User\NewPivotOrderConfigController;
use App\Http\Controllers\User\McxPivotController;
use App\Http\Controllers\User\DailyOiSentimentController;
use App\Http\Controllers\User\AtmOiSentimentController;
use App\Http\Controllers\User\ExpiryOiController;
use App\Http\Controllers\User\ExpiryOiScanController;
use App\Http\Controllers\User\ExitPlanController;
use App\Http\Controllers\User\ExitPlanConfigController;
use App\Http\Controllers\User\PivotSignal15Controller;
use App\Http\Controllers\User\OptionFairValueController;
use App\Http\Controllers\User\SmartMoneyAnalysisController;
use App\Http\Controllers\User\StraddleStrangleController;
use App\Http\Controllers\User\StraddleStrangle15Controller;
use App\Http\Controllers\User\OIStrategyController;
use App\Http\Controllers\User\NextSeriesDailyAnalysisController;
use App\Http\Controllers\User\VolumeSpike15Controller;
use App\Http\Controllers\User\SmartVolumeSpike15Controller;
use App\Http\Controllers\User\SignalIntelligence5minController;
use App\Http\Controllers\User\SmartVolumeDailyEodController;
use App\Http\Controllers\User\EodSignalController;
use App\Http\Controllers\User\EodBacktestController;
use App\Http\Controllers\User\StraddleExitController;
use App\Http\Controllers\User\TradeBacktestController;
use App\Http\Controllers\User\BrokerStopLossConfigController;
use App\Http\Controllers\User\OIIVTriSentimentController;
use App\Http\Controllers\User\NextSeriesOIIVAutoController;
use App\Http\Controllers\User\NiftyDrivenAllSymbolsController;
use App\Http\Controllers\User\NiftyDrivenBreakoutConfigController;
use App\Http\Controllers\User\Nifty50SectorTrendController;
use App\Http\Controllers\User\BankNiftySectorTrendController;
use App\Http\Controllers\User\SensexExpiryAnalysisController;
use App\Http\Controllers\User\SensexBacktestController;
use App\Http\Controllers\User\SensexIntradayController;
use App\Http\Controllers\User\VolatileIndexScalpingController;
use App\Http\Controllers\User\AccountWiseAnalysisController;
use App\Http\Controllers\User\UniversalBTSTController;
use App\Http\Controllers\User\UniversalBTSTReverseController;
use App\Http\Controllers\User\StockSignalController;
use App\Http\Controllers\User\GannOctaveController;
use App\Http\Controllers\User\OIEngineController;
use App\Http\Controllers\User\NearStrikeOIController;
use App\Http\Controllers\User\MultiDayOIController;
use App\Http\Controllers\User\OIDominanceV2Controller;
use App\Http\Controllers\User\AuroOIAnalysisController;
use App\Http\Controllers\User\AllSymbolOIAnalysisController;
use App\Http\Controllers\User\OptionAnalysisController;
use App\Http\Controllers\User\MFSignalScannerController;
use App\Http\Controllers\User\MFBacktestController;
use App\Http\Controllers\User\MfMultiAssetController;
use App\Http\Controllers\User\FutOptionStrategyController;
use App\Http\Controllers\User\IntradayOIController;
use App\Http\Controllers\User\TrendPredictorController;
use App\Http\Controllers\User\FutContrarianController;
use App\Http\Controllers\User\FutContrarianMonthlyController;
use App\Http\Controllers\User\FutContrarianConfigController;

// NEW
use App\Http\Controllers\User\PivotAnalysisController;
use App\Http\Controllers\User\OpenHighLowController;
use App\Http\Controllers\User\MomentumBreakoutController;
use App\Http\Controllers\User\OIFlowSentimentController;
use App\Http\Controllers\User\IntradayOISnapshotController;
use App\Http\Controllers\User\IndexDrivenSignalController;
use App\Http\Controllers\User\NiftyDrivenBreakoutAnalysisController;
use App\Http\Controllers\User\StrataOptionsFairValueController;
use App\Http\Controllers\User\QuantEdgeSmartMoneyController;
use App\Http\Controllers\User\PrimeFlowScannerController;
use App\Http\Controllers\User\StraddleStrategyController;
use App\Http\Controllers\HomePageController;
use App\Http\Controllers\User\Auth\EmailOtpController;

Route::get('/import-data','SiteController@importExcelData')->name('importExcelData');
// Route::get('/', 'SiteController@index')->name('home');
Route::get('/', [HomePageController::class, 'index'])->name('home');
Route::get('/about', [HomePageController::class, 'about'])->name('about');
Route::get('/webinars', [HomePageController::class, 'webinars'])->name('webinars');
Route::get('/courses', [HomePageController::class, 'courses'])->name('courses');
Route::get('/login',   [HomePageController::class, 'login'])->name('user.login');
Route::get('/sign-up', [HomePageController::class, 'login'])->name('user.register');
Route::get('/events',  [HomePageController::class, 'events'])->name('events');
Route::get('/option-symposium', [HomePageController::class, 'optionSymposium'])->name('optionsymposium');
Route::get('/video-library', [HomePageController::class, 'videoLibrary'])->name('video.library');
Route::get('/events',    [HomePageController::class, 'events'])->name('events');
Route::get('/book-demo', [HomePageController::class, 'bookDemo'])->name('book.demo');
Route::get('/media', [HomePageController::class, 'media'])->name('media');

Route::middleware('guest')->group(function () {
    Route::post('/send-email-otp',   [EmailOtpController::class, 'sendOtp'])->name('send.email.otp');
    Route::post('/verify-email-otp', [EmailOtpController::class, 'verifyOtp'])->name('verify.email.otp');
});

Route::get('/index-ajax', 'SiteController@indexAjax')->name('home-ajax');

Route::get('get-market-data', 'SiteController@getMarketData')->name('get-market-data');
Route::get('get-top-loser-api-data', 'SiteController@getTopLoserData')->name('get-top-loser-api-data');
Route::get('get-top-gainer-api-data', 'SiteController@getTopGainerApiData')->name('get-top-gainer-api-data');
Route::get('get-pcr-api-data', 'SiteController@getPcrApiData')->name('get-pcr-api-data');
Route::get('get-long-build-api-data', 'SiteController@getLongBuildApiData')->name('get-long-build-api-data');
Route::get('get-short-build-api-data', 'SiteController@getShortBuildApiData')->name('get-short-build-api-data');
Route::get('get-short-covering-api-data', 'SiteController@getShortCoveringApiData')->name('get-short-covering-api-data');
Route::get('get-long-unwilling-api-data', 'SiteController@getLongUnwillingApiData')->name('get-long-unwilling-api-data');

// Store Instruments Data
Route::get('store-token-data', 'SiteController@storeTokenData')->name('storeTokenData');

// Store Historical Data
Route::get('store-api-fetch-data', 'SiteController@storeApiFetchData');

Route::get('fetch-option-greek-data','SiteController@fetchGreeksApiData')->name('fetch-option-greek-data');

Route::get('/clear', function(){
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
});

Route::controller('CronController')->prefix('cron')->group(function () {
    Route::get('/', 'cron')->name('cron');
    Route::get('/all', 'all')->name('cron.all');
});


// User Support Ticket
Route::controller('TicketController')->prefix('ticket')->name('ticket.')->group(function () {
    Route::get('/', 'supportTicket')->name('index');
    Route::get('new', 'openSupportTicket')->name('open');
    Route::post('create', 'storeSupportTicket')->name('store');
    Route::get('view/{ticket}', 'viewTicket')->name('view');
    Route::post('reply/{ticket}', 'replyTicket')->name('reply');
    Route::post('close/{ticket}', 'closeTicket')->name('close');
    Route::get('download/{ticket}', 'ticketDownload')->name('download');
});

Route::get('app/deposit/confirm/{hash}', 'Gateway\PaymentController@appDepositConfirm')->name('deposit.app.confirm');

Route::controller('SiteController')->group(function () {

    Route::post('/add/device/token', 'getDeviceToken')->name('add.device.token');

    Route::get('/contact', 'contact')->name('contact');
    Route::post('/contact', 'contactSubmit');
    Route::get('/change/{lang?}', 'changeLanguage')->name('lang');

    Route::get('cookie-policy', 'cookiePolicy')->name('cookie.policy');

    Route::get('/cookie/accept', 'cookieAccept')->name('cookie.accept');
    Route::get('/packages', 'packages')->name('packages');
    Route::get('/package-details/{id}', 'packageDetails')->name('packagedetails');
    Route::post('/package-details/{id}', 'storeUserRequest')->name('storeUserRequest');

    Route::get('/blogs', 'blogs')->name('blogs');
    Route::get('blog/{slug}/{id}', 'blogDetails')->name('blog.details');

    Route::post('/subscribe', 'subscribe')->name('subscribe');
    Route::get('policy/{slug}/{id}', 'policyPages')->name('policy.pages');

    Route::get('placeholder-image/{size}', 'placeholderImage')->name('placeholder.image');

    Route::get('/{slug}', 'pages')->name('pages');
    
    // Route::get('/', 'index')->name('home');


});

        // Zerodha Authentication Routes (for initial setup)
        Route::middleware(['auth'])->prefix('zerodha')->group(function () {
            // Redirect to Zerodha login
            Route::get('/login', [ZerodhaAuthController::class, 'redirectToZerodha'])
                ->name('zerodha.login');
            
            // Callback after Zerodha authentication
            Route::get('/callback', [ZerodhaAuthController::class, 'handleCallback'])
                ->name('zerodha.callback');
            
            // Manual token setup (for testing)
            Route::post('/set-token', [ZerodhaAuthController::class, 'setAccessToken'])
                ->name('zerodha.set-token');

            Route::get('/callback2', [ZerodhaAuthController::class, 'handleCallback2'])
                ->name('zerodha.callback');
            
            // Check authentication status
        });

        // Portfolio & Positions Routes
        // Route::middleware(['auth'])->prefix('portfolio')->name('portfolio.')->group(function () {
        //     Route::get('/index', [ZerodhaPortfolioController::class, 'index'])->name('index');
        //     Route::post('/fetch-positions', [ZerodhaPortfolioController::class, 'fetchPositions'])->name('fetch-positions');
        //     Route::post('/sell-position', [ZerodhaPortfolioController::class, 'sellPosition'])->name('sell-position');
        //     Route::post('/get-holdings', [ZerodhaPortfolioController::class, 'getHoldings'])->name('get-holdings');
        // });

        
        Route::middleware(['auth'])->prefix('portfolio')->name('portfolio.')->group(function () {
            // Main portfolio page
            Route::get('/index', [ZerodhaPortfolioController::class, 'index'])->name('index');
            // ── OPEN positions (from our DB, enriched with live LTP) ──────
            Route::post('/fetch-positions', [ZerodhaPortfolioController::class, 'fetchPositions'])->name('fetch-positions');
            // ── CLOSED positions history ───────────────────────────────────
            Route::post('/closed-positions', [ZerodhaPortfolioController::class, 'fetchClosedPositions'])->name('closed-positions');
            // ── TODAY's activity (what opened/closed today) ────────────────
            Route::post('/today-activity', [ZerodhaPortfolioController::class, 'fetchTodayActivity'])->name('today-activity');
            // ── Sell/square-off a position ─────────────────────────────────
            Route::post('/sell-position', [ZerodhaPortfolioController::class, 'sellPosition'])->name('sell-position');
            // ── Holdings (long-term CNC positions) ────────────────────────
            Route::post('/get-holdings', [ZerodhaPortfolioController::class, 'getHoldings'])->name('get-holdings');
        });

        
        // Zerodha Broker Routes
        // Route::middleware(['auth'])->prefix('zerodha-broker')->name('zerodha-broker.')->group(function () {
        //     // List brokers
        //     Route::get('/index', [ZerodhaBrokerController::class, 'index'])->name('index');
            
        //     // Add broker
        //     Route::post('/store', [ZerodhaBrokerController::class, 'store'])->name('store');
            
        //     // Edit broker
        //     Route::get('/{id}/edit', [ZerodhaBrokerController::class, 'getBrokerDetails'])->name('edit');
        //     Route::post('/{id}/update', [ZerodhaBrokerController::class, 'update'])->name('update');
            
        //     // Delete broker
        //     Route::delete('/{id}', [ZerodhaBrokerController::class, 'destroy'])->name('destroy');
            
        //     // AUTO LOGIN - New automatic login endpoint
        //     Route::post('/{id}/auto-login', [ZerodhaBrokerController::class, 'autoLogin'])->name('auto-login');
            
        //     // Manual login (redirect to Zerodha)
        //     Route::get('/{id}/login', [ZerodhaBrokerController::class, 'login'])->name('login');
            
        //     // Callback from Zerodha
        //     Route::get('/callback', [ZerodhaBrokerController::class, 'callback'])->name('callback');
            
        //     // Update access token manually
        //     Route::post('/{id}/update-token', [ZerodhaBrokerController::class, 'updateToken'])->name('update-token');
            
        //     // Check token status
        //     Route::get('/{id}/token-status', [ZerodhaBrokerController::class, 'checkTokenStatus'])->name('token-status');
        // });

        Route::middleware(['auth'])->prefix('futures')->name('futures.')->group(function () {
            // Supertrend Analysis Page
            Route::get('/supertrend-analysis', [FuturesController::class, 'supertrendAnalysis'])->name('supertrend-analysis');
            
            // AJAX endpoints
            Route::get('/supertrend-fetch', [FuturesController::class, 'supertrendFetch'])->name('supertrend-fetch');
            Route::post('/supertrend-calculate', [FuturesController::class, 'calculateAndSaveSupertrend'])->name('supertrend-calculate');
            
            // Export to CSV
            Route::get('/export', [FuturesController::class, 'export'])->name('export');
            Route::post('/manual-fetch', [FuturesController::class, 'manualFetchDaily'])->name('manual-fetch');

            Route::get('backtesting', [FuturesController::class, 'backtesting'])->name('backtesting');
            Route::get('backtesting-fetch', [FuturesController::class, 'backtestingFetch'])->name('backtesting-fetch');

            // NEW: Configuration routes
            Route::get('/config', [IndicatorConfigController::class, 'index'])->name('config');
            Route::post('/config/global', [IndicatorConfigController::class, 'updateGlobal'])->name('config.update-global');
            Route::post('/config/symbol', [IndicatorConfigController::class, 'updateSymbol'])->name('config.update-symbol');
            Route::delete('/config/symbol/{id}', [IndicatorConfigController::class, 'deleteSymbol'])->name('config.delete-symbol');
            Route::post('/config/recalculate', [IndicatorConfigController::class, 'recalculateIndicators'])->name('config.recalculate');
            Route::post('/config/recalculate-all', [IndicatorConfigController::class, 'recalculateAll'])->name('config.recalculate-all');
        });

        // Backtest Profit Calculation
        Route::middleware(['auth'])->name('futures.')->prefix('futures')->group(function () {
            Route::post('backtest-profit', [BacktestProfitController::class, 'calculateProfit'])
                ->name('backtest-profit');
            Route::post('store-access-token', [BacktestProfitController::class, 'storeAccessToken'])
                ->name('store-access-token');
        });
        
        
        Route::middleware(['auth'])->prefix('expiry')->name('expiry.')->group(function () {
            // Analysis Page
            Route::get('/analysis', [ExpiryController::class, 'analysis'])->name('analysis');
            Route::get('/fetch', [ExpiryController::class, 'fetch'])->name('fetch');
            Route::get('/export', [ExpiryController::class, 'export'])->name('export');
            Route::post('/manual-fetch', [ExpiryController::class, 'manualFetch'])->name('manual-fetch');
            
            // Configuration
            Route::prefix('config')->name('config.')->group(function () {
                Route::get('/', [ExpiryConfigController::class, 'index'])->name('index');
                Route::post('/global', [ExpiryConfigController::class, 'updateGlobal'])->name('update-global');
                Route::post('/symbol', [ExpiryConfigController::class, 'updateSymbol'])->name('update-symbol');
                Route::delete('/symbol/{id}', [ExpiryConfigController::class, 'deleteSymbol'])->name('delete-symbol');
                Route::post('/recalculate', [ExpiryConfigController::class, 'recalculateIndicators'])->name('recalculate');
                Route::post('/recalculate-all', [ExpiryConfigController::class, 'recalculateAll'])->name('recalculate-all');
            });
            
            // Auto Trading Configuration
            Route::prefix('auto')->name('auto.')->group(function () {
                Route::get('/', [ExpiryAutoConfigController::class, 'index'])->name('index');
                Route::post('/store', [ExpiryAutoConfigController::class, 'store'])->name('store');
                Route::post('/toggle/{id}', [ExpiryAutoConfigController::class, 'toggleStatus'])->name('toggle');
                Route::delete('/{id}', [ExpiryAutoConfigController::class, 'destroy'])->name('destroy');
                Route::get('/{id}/orders', [ExpiryAutoConfigController::class, 'orders'])->name('orders');
            });
        });

        Route::middleware(['auth'])->prefix('symbols')->name('symbols.')->group(function () {
            // Analysis Pages
            Route::get('/analysis', [SymbolController::class, 'analysis'])->name('analysis');
            
            // AJAX Endpoints
            Route::get('/analysis-latest', [SymbolController::class, 'analysisLatest'])->name('analysis-latest');  // ✅ NEW
            Route::get('/analysis-fetch', [SymbolController::class, 'analysisFetch'])->name('analysis-fetch');
            
            // Export to CSV
            Route::get('/export', [SymbolController::class, 'export'])->name('export');
            Route::post('/manual-fetch', [SymbolController::class, 'manualFetchDaily'])->name('manual-fetch');

            // Backtesting
            Route::get('backtesting', [SymbolController::class, 'backtesting'])->name('backtesting');
            Route::get('backtesting-fetch', [SymbolController::class, 'backtestingFetch'])->name('backtesting-fetch');

            // Option Strike Selections
            Route::get('option-strikes', [SymbolController::class, 'optionStrikes'])->name('option-strikes');
            Route::get('option-strikes-fetch', [SymbolController::class, 'optionStrikesFetch'])->name('option-strikes-fetch');
            Route::get('option-strikes-symbols', [SymbolController::class, 'optionStrikesSymbols'])->name('option-strikes-symbols');
        });

        Route::middleware(['auth'])->prefix('symbols')->name('symbols.')->group(function () {

            Route::post('backtest-profit', [SymbolBacktestProfitController::class, 'calculateProfit'])->name('backtest-profit');

            Route::post('place-backtest-order', [SymbolController::class, 'placeBacktestOrder'])
                ->name('place-backtest-order');
                
            Route::post('square-off-backtest-order', [SymbolController::class, 'squareOffBacktestOrder'])
                ->name('square-off-backtest-order');

            Route::get('/quality-momentum',  [SymbolController::class, 'qualityMomentum'])->name('quality-momentum');
            Route::get('/quality-momentum-fetch',  [SymbolController::class, 'qualityMomentumFetch'])->name('quality-momentum-fetch');

            // NEW ROUTES
            Route::get('one-percent', [SymbolOnePercentController::class, 'index'])->name('one-percent');
            Route::get('one-percent-analyze', [SymbolOnePercentController::class, 'analyzeOnePercent'])->name('one-percent-analyze');
            Route::post('one-percent-profit', [SymbolOnePercentController::class, 'calculateProfit'])->name('one-percent-profit');
            Route::post('one-percent-export', [SymbolOnePercentController::class, 'exportCSV'])->name('one-percent-export');

            // ✅ NEW - Order Picker Routes
            Route::get('one-percent/order-picker', [SymbolOnePercentController::class, 'orderPicker'])->name('order-picker');
            Route::get('one-percent/analyze-picker', [SymbolOnePercentController::class, 'analyzeOrderPicker'])->name('analyze-picker');
        });

        
        Route::middleware(['auth'])->prefix('pivot-point')->name('pivot-point.')->group(function () {
            // Main Pivot Point Analysis Page
            Route::get('/index', [SymbolPivotPointController::class, 'index'])->name('index');
            
            // AJAX endpoint for analyzing pivot signals
            Route::get('/analyze', [SymbolPivotPointController::class, 'analyzePivotPoints'])->name('analyze');
            
            // AJAX endpoint for calculating profit/loss
            Route::post('/profit', [SymbolPivotPointController::class, 'calculateProfit'])->name('profit');
            
            // Export to CSV
            Route::post('/export', [SymbolPivotPointController::class, 'exportCSV'])->name('export');
        });

        // One-Percent Auto Trading
        Route::middleware(['auth'])->prefix('one-percent-auto')->name('one-percent-auto.')->group(function() {
            Route::get('/index', [OnePercentAutoController::class, 'index'])->name('index');
            Route::post('/store', [OnePercentAutoController::class, 'store'])->name('store');
            Route::post('/update/{id}', [OnePercentAutoController::class, 'update'])->name('update');
            Route::get('/orders/{id}', [OnePercentAutoController::class, 'viewOrders'])->name('orders');
            Route::post('/toggle/{id}', [OnePercentAutoController::class, 'toggleStatus'])->name('toggle');
            Route::delete('/destroy/{id}', [OnePercentAutoController::class, 'destroy'])->name('destroy');
        });

        // === NEW CUSTOM BEHAVIOR ROUTES ===
        Route::middleware(['auth'])->prefix('custom-behavior')->name('custom.')->group(function () {
            // Custom Price Behavior Analysis
            Route::get('/analysis', [CustomBehaviorController::class, 'analysis'])->name('analysis');
            Route::get('/analysis-fetch', [CustomBehaviorController::class, 'analysisFetch'])->name('analysis-fetch');
            
            // Custom Behavior Backtesting
            Route::get('/backtesting', [CustomBehaviorController::class, 'backtesting'])->name('backtesting');
            Route::get('/backtesting-fetch', [CustomBehaviorController::class, 'backtestingFetch'])->name('backtesting-fetch');
        });

    // Auto Target Orders Routes
    Route::middleware(['auth'])->prefix('auto-targets')->name('auto-targets.')->group(function () {
        // Dashboard
        Route::get('/index', [AutoTargetOrderController::class, 'index'])->name('index');
        
        // Sync positions and create targets
        Route::post('/sync', [AutoTargetOrderController::class, 'syncPositions'])->name('sync');
        
        // Get active targets
        Route::get('/active', [AutoTargetOrderController::class, 'getActiveTargets'])->name('active');
        
        // Get target details
        Route::get('/{id}', [AutoTargetOrderController::class, 'getTargetDetails'])->name('details');
        
        // Cancel a target
        Route::post('/{id}/cancel', [AutoTargetOrderController::class, 'cancelTarget'])->name('cancel');
        
        // Get statistics
        Route::get('/stats/summary', [AutoTargetOrderController::class, 'getStats'])->name('stats');
        
        // Manually trigger monitoring
        Route::post('/monitor/trigger', [AutoTargetOrderController::class, 'triggerMonitoring'])->name('monitor');
        
        // Cleanup old targets
        Route::post('/cleanup', [AutoTargetOrderController::class, 'cleanup'])->name('cleanup');
    });

    // Route::middleware(['auth'])->prefix('data')->name('data.')->group(function () {
    //     // Supertrend Analysis Page
    //     Route::get('/analysis', [SymbolControllerNew::class, 'analysis'])->name('analysis');
        
    //     // AJAX endpoints
    //     Route::get('/analysis-fetch', [SymbolControllerNew::class, 'analysisFetch'])->name('analysis-fetch');
        
    //     // Export to CSV
    //     Route::get('/export', [SymbolControllerNew::class, 'export'])->name('export');
    //     Route::post('/manual-fetch', [SymbolControllerNew::class, 'manualFetchDaily'])->name('manual-fetch');

    //     // Backtesting
    //     Route::get('backtesting', [SymbolControllerNew::class, 'backtesting'])->name('backtesting');
    //     Route::get('backtesting-fetch', [SymbolControllerNew::class, 'backtestingFetch'])->name('backtesting-fetch');

    //     Route::post('backtest-profit', [SymbolBacktestProfitController::class, 'calculateProfit'])->name('backtest-profit');

    //     Route::post('place-backtest-order', [SymbolControllerNew::class, 'placeBacktestOrder'])
    //         ->name('place-backtest-order');
            
    //     Route::post('square-off-backtest-order', [SymbolControllerNew::class, 'squareOffBacktestOrder'])
    //         ->name('square-off-backtest-order');
    // });

    Route::middleware(['auth'])->prefix('manual-orders')->name('manual-orders.')->group(function () {
        Route::get('index', [ManualOrderController::class, 'index'])->name('index');
        Route::get('fetch', [ManualOrderController::class, 'manualOrdersFetch'])->name('fetch');
        Route::post('place', [ManualOrderController::class, 'placeManualOrder'])->name('place');
        Route::post('fetch-ltps', [ManualOrderController::class, 'fetchLiveLTPsBatch'])->name('fetch-ltps');
        Route::post('fetch-signal-ltps', [ManualOrderController::class, 'fetchSignalLTPsBatch'])->name('fetch-signal-ltps'); // ✅ NEW
    });
    
    // OI Analysis Routes (matching One Percent structure)
    Route::middleware(['auth'])->prefix('options')->name('options.')->group(function () {
        
        // OI Analysis Page
        Route::get('/oi-analysis', [OptionOIAnalysisController::class, 'index'])
            ->name('oi-analysis');
        
        // AJAX endpoints
        Route::get('/oi-analysis-fetch', [OptionOIAnalysisController::class, 'analysisFetch'])
            ->name('oi-analysis-fetch');
        
        // Profit calculation
        Route::post('/oi-profit', [OptionOIAnalysisController::class, 'calculateProfit'])
            ->name('oi-profit');
        
        // Export to CSV
        Route::post('/oi-export', [OptionOIAnalysisController::class, 'export'])
            ->name('oi-export');
    });

    Route::middleware(['auth'])->prefix('camarilla')->name('camarilla.')->group(function () {
        // Main page
        Route::get('/index', [SymbolCamarillaController::class, 'index'])->name('index');
        
        // Analysis endpoint
        Route::get('/analyze', [SymbolCamarillaController::class, 'analyzeCamarilla'])->name('analyze');
        
        // Profit calculation
        Route::post('/calculate-profit', [SymbolCamarillaController::class, 'calculateProfit'])->name('calculate-profit');
        
        // Export to CSV
        Route::post('/export', [SymbolCamarillaController::class, 'exportCSV'])->name('export');
    });


    Route::middleware(['auth'])->prefix('options')->name('options.')->group(function () {
        // IV Analysis Routes
        Route::get('iv-analysis', [OptionIVController::class, 'index'])->name('iv-analysis');
        Route::get('iv-analysis-fetch', [OptionIVController::class, 'fetchIVAnalysis'])->name('iv-analysis-fetch');
        Route::get('iv-trend-chart', [OptionIVController::class, 'getIVTrendChart'])->name('iv-trend-chart');
        Route::get('iv-export', [OptionIVController::class, 'exportIVData'])->name('iv-export');
        Route::post('iv-manual-fetch', [OptionIVController::class, 'manualFetchIV'])->name('iv-manual-fetch');
    });

    // OI+IV Auto Trading
    Route::middleware(['auth'])->prefix('oiiv-auto')->name('oiiv-auto.')->group(function() {
        // Analysis/Backtesting
        Route::get('/analysis', [OIIVAutoController::class, 'index'])->name('index');
        Route::get('/analyze-signals', [OIIVAutoController::class, 'analyzeSignals'])->name('analyze');
        Route::get('/get-symbols', [OIIVAutoController::class, 'getSymbols'])->name('symbols');
        
        // Configuration
        Route::get('/config', [OIIVAutoController::class, 'config'])->name('config');
        Route::post('/store', [OIIVAutoController::class, 'store'])->name('store');
        Route::post('/update/{id}', [OIIVAutoController::class, 'update'])->name('update');
        Route::post('/toggle/{id}', [OIIVAutoController::class, 'toggleStatus'])->name('toggle');
        Route::delete('/destroy/{id}', [OIIVAutoController::class, 'destroy'])->name('destroy');
        
        // Orders
        Route::get('/orders/{id}', [OIIVAutoController::class, 'viewOrders'])->name('orders');
        Route::post('/clear-price-cache', [OIIVAutoController::class, 'clearPriceCache'])->name('clear-cache');

        // PE/CE Analysis
        Route::get('/pece-analysis', [OIIVAutoController::class, 'peCeAnalysis'])->name('pece-analysis');
        Route::get('/analyze-pece-signals', [OIIVAutoController::class, 'analyzePECESignals'])->name('analyze-pece');
        
        // ✅ NEW - Profit Calculation Routes
        Route::post('/calculate-profit', [OIIVAutoController::class, 'calculateProfit'])->name('calculate-profit');
        Route::post('/calculate-bulk-profit', [OIIVAutoController::class, 'calculateBulkProfit'])->name('calculate-bulk-profit');
        
        Route::post('/run-signals', [OIIVAutoController::class, 'runSignalsManually'])->name('run-signals');
    });

    // CE/PE OI Change Analysis Routes
    Route::middleware(['auth'])->prefix('oi-change-analysis')->name('oi-change.')->group(function() {
        Route::get('/index', [OIChangeAnalysisController::class, 'index'])->name('index');
        Route::get('/analyze-signals', [OIChangeAnalysisController::class, 'analyzeSignals'])->name('analyze');
        Route::get('/get-symbols', [OIChangeAnalysisController::class, 'getSymbols'])->name('symbols');
    });

    // Individual Strike Analysis Routes
    Route::middleware(['auth'])->prefix('oiiv-individual')->name('oiiv-individual.')->group(function() {
        // Main Analysis Page
        Route::get('/analysis', [OIIVIndividualStrikeController::class, 'index'])->name('index');
        
        // Get available symbols
        Route::get('/get-symbols', [OIIVIndividualStrikeController::class, 'getSymbols'])->name('symbols');
        
        // Analyze individual strikes with dynamic merging
        Route::get('/analyze-strikes', [OIIVIndividualStrikeController::class, 'analyzeIndividualStrikes'])->name('analyze');
        
        // Calculate profit for individual strikes
        Route::post('/calculate-profit', [OIIVIndividualStrikeController::class, 'calculateBulkProfit'])->name('calculate-profit');
    });

    Route::middleware(['auth'])->prefix('portfolio/amo-config')->name('portfolio.amo-config.')->group(function () {
        Route::get('/index', [BrokerAmoConfigController::class, 'index'])->name('index');
        Route::post('/store', [BrokerAmoConfigController::class, 'store'])->name('store');
        Route::put('/update/{id}', [BrokerAmoConfigController::class, 'update'])->name('update');
        Route::delete('/destroy/{id}', [BrokerAmoConfigController::class, 'destroy'])->name('destroy');
        Route::post('/toggle/{id}', [BrokerAmoConfigController::class, 'toggleActive'])->name('toggle');
        Route::get('/get-by-date', [BrokerAmoConfigController::class, 'getConfigsForDate'])->name('get-by-date');
        Route::post('/copy', [BrokerAmoConfigController::class, 'copyConfigs'])->name('copy');
        Route::post('/execute', [BrokerAmoConfigController::class, 'execute'])->name('execute');
    });

    Route::middleware(['auth'])->prefix('portfolio/broker-sell-config')->name('portfolio.broker-sell-config.')->group(function () {
        Route::get('/index', [BrokerSellOrderConfigController::class, 'index'])->name('index');
        Route::post('/store', [BrokerSellOrderConfigController::class, 'store'])->name('store');
        Route::put('/update/{id}', [BrokerSellOrderConfigController::class, 'update'])->name('update');
        Route::delete('/destroy/{id}', [BrokerSellOrderConfigController::class, 'destroy'])->name('destroy');
        Route::post('/toggle/{id}', [BrokerSellOrderConfigController::class, 'toggleActive'])->name('toggle');
        Route::get('/get', [BrokerSellOrderConfigController::class, 'getConfigs'])->name('get');
        Route::post('/execute', [BrokerSellOrderConfigController::class, 'execute'])->name('execute');
    });

    Route::middleware(['auth'])->prefix('portfolio/live-ltp-config')->name('portfolio.live-ltp-config.')->group(function () {
        Route::get('/index', [BrokerLiveLtpConfigController::class, 'index'])->name('index');
        Route::post('/store', [BrokerLiveLtpConfigController::class, 'store'])->name('store');
        Route::put('/update/{id}', [BrokerLiveLtpConfigController::class, 'update'])->name('update');
        Route::delete('/destroy/{id}', [BrokerLiveLtpConfigController::class, 'destroy'])->name('destroy');
        Route::post('/toggle/{id}', [BrokerLiveLtpConfigController::class, 'toggleActive'])->name('toggle');
        Route::post('/execute', [BrokerLiveLtpConfigController::class, 'execute'])->name('execute');
    });

    Route::middleware(['auth'])->prefix('9to12-analysis')->name('9to12.')->group(function () {

        // ── Analysis page ──────────────────────────────────────────
        Route::get('/pece-analysis', [OIIVAuto9to12Controller::class, 'peCeAnalysis'])->name('pece-analysis');

        // ── Analysis AJAX ──────────────────────────────────────────
        Route::get('/get-symbols',           [OIIVAuto9to12Controller::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze-pece-signals',  [OIIVAuto9to12Controller::class, 'analyzePECESignals'])->name('analyze-pece');

        // ── Configuration ──────────────────────────────────────────
        Route::get('/config',                [OIIVAuto9to12Controller::class, 'config'])->name('config');
        Route::post('/store',                [OIIVAuto9to12Controller::class, 'store'])->name('store');
        Route::post('/update/{id}',          [OIIVAuto9to12Controller::class, 'update'])->name('update');
        Route::post('/toggle/{id}',          [OIIVAuto9to12Controller::class, 'toggleStatus'])->name('toggle');
        Route::delete('/destroy/{id}',       [OIIVAuto9to12Controller::class, 'destroy'])->name('destroy');

        Route::post('/profit', [OIIVAuto9to12Controller::class, 'calculateProfit'])->name('profit');
        // ── Orders ─────────────────────────────────────────────────
        Route::get('/orders/{id}',           [OIIVAuto9to12Controller::class, 'viewOrders'])->name('orders');
        Route::get('/get-series', [OIIVAuto9to12Controller::class, 'getSeries'])->name('series');
        Route::post('/run-signals', [OIIVAuto9to12Controller::class, 'runSignalsManually'])->name('run-signals');
    });

    Route::middleware(['auth'])->prefix('index-oi')->name('index-oi.')->group(function () {
        Route::get('/pece-analysis',          [IndexOIController::class, 'peCeAnalysis'])->name('pece-analysis');
        Route::get('/analyze-pece-signals',   [IndexOIController::class, 'analyzePECESignals'])->name('analyze-pece');
        Route::get('/get-symbols',            [IndexOIController::class, 'getSymbols'])->name('symbols');
        Route::post('/calculate-bulk-profit', [IndexOIController::class, 'calculateBulkProfit'])->name('calculate-bulk-profit');
    });

    Route::middleware(['auth'])->prefix('oi-crossover')->name('oi-crossover.')->group(function () {
        Route::get('/index',      [OiMaCrossoverController::class, 'index'])     ->name('index');
        Route::get('/chart-data', [OiMaCrossoverController::class, 'chartData']) ->name('chart-data');
    });

    Route::middleware(['auth'])->prefix('trade-book')->name('trade-book.')->group(function () {

        Route::get('/upload',          [TradeBookController::class, 'upload'])
            ->name('upload');

        Route::post('/upload',         [TradeBookController::class, 'processUpload'])
            ->name('process-upload');

        Route::get('/report',          [TradeBookController::class, 'report'])
            ->name('report');

        Route::get('/ajax-pnl',        [TradeBookController::class, 'ajaxPnlData'])   // ← ADD THIS
            ->name('ajax-pnl');

        Route::delete('/delete-upload',[TradeBookController::class, 'deleteUpload'])
            ->name('delete-upload');

    });

    Route::middleware(['auth'])->prefix('signal-engine')->name('signal-engine.')->group(function () {
        Route::get('/index', [OptionSignalController::class, 'index'])->name('index');
    });

    Route::middleware(['auth'])->prefix('strike-analysis')->name('strike-analysis.')->group(function () {
        Route::get('/index',          [OIStrikeAnalysisController::class, 'index'])->name('index');
        Route::get('/symbols',   [OIStrikeAnalysisController::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze',   [OIStrikeAnalysisController::class, 'analyze'])->name('analyze');
    });

    Route::middleware(['auth'])->prefix('ohlc-viewer')->name('ohlc-viewer.')->group(function () {
        Route::get('/index',           [OhlcDataViewerController::class, 'index'])->name('index');
        Route::get('/symbols',    [OhlcDataViewerController::class, 'getSymbols'])->name('symbols');
        Route::get('/data',       [OhlcDataViewerController::class, 'getData'])->name('data');
        Route::get('/oi-compare', [OhlcDataViewerController::class, 'getOiComparison'])->name('oi-compare');
    });

    Route::middleware(['auth'])->prefix('pivot-points')->name('pivot.')->group(function () {
        Route::get('/index',         [PivotPointsController::class, 'index'])->name('index');
        Route::get('/symbols',  [PivotPointsController::class, 'getSymbols'])->name('symbols');
        Route::get('/series',   [PivotPointsController::class, 'getSeries'])->name('series');
        Route::get('/data',     [PivotPointsController::class, 'getData'])->name('data');
        Route::get('/candles',  [PivotPointsController::class, 'getCandles'])->name('candles');
    });

    Route::middleware(['auth'])->prefix('pivot-order-config')->name('pivot-order-config.')->group(function () {
        Route::get('/index',         [PivotOrderConfigController::class, 'index'])     ->name('index');
        Route::post('/save',    [PivotOrderConfigController::class, 'save'])      ->name('save');
        Route::get('/get',      [PivotOrderConfigController::class, 'getConfig']) ->name('get');
        Route::delete('/reset', [PivotOrderConfigController::class, 'reset'])     ->name('reset');
        Route::get('/preview',  [PivotOrderConfigController::class, 'preview'])   ->name('preview');
        Route::post('/execute',  [PivotOrderConfigController::class, 'execute'])   ->name('execute');
    });

    Route::middleware(['auth'])->prefix('pivot-normal-order-config')->name('pivot-normal-order-config.')->group(function () {
        Route::get('/index',   [PivotNormalOrderConfigController::class, 'index'])     ->name('index');
        Route::post('/save',   [PivotNormalOrderConfigController::class, 'save'])      ->name('save');
        Route::get('/get',     [PivotNormalOrderConfigController::class, 'getConfig']) ->name('get');
        Route::delete('/reset',[PivotNormalOrderConfigController::class, 'reset'])     ->name('reset');
        Route::post('/toggle', [PivotNormalOrderConfigController::class, 'toggle'])    ->name('toggle');
        Route::get('/preview', [PivotNormalOrderConfigController::class, 'preview'])   ->name('preview');
        Route::post('/execute',[PivotNormalOrderConfigController::class, 'execute'])   ->name('execute');
    });

    Route::middleware(['auth'])->prefix('fut-ohl')->name('fut-ohl.')->group(function () {
        Route::get('/index',            [FutOpenHighLowController::class, 'analysis'])->name('analysis');
        Route::get('/get-symbols', [FutOpenHighLowController::class, 'getSymbols'])->name('symbols');
        Route::get('/get-series',  [FutOpenHighLowController::class, 'getSeries'])->name('series');
        Route::get('/analyze',     [FutOpenHighLowController::class, 'analyze'])->name('analyze');
    });

    Route::middleware(['auth'])->prefix('mcx-oiiv')->name('mcx-oiiv.')->group(function () {

        // ── Analysis pages ────────────────────────────────────────────────────────
        Route::get('/analysis',         [McxOiivAutoController::class, 'index'])->name('index');
        Route::get('/pece-analysis',    [McxOiivAutoController::class, 'peCeAnalysis'])->name('pece-analysis');

        // ── API endpoints ─────────────────────────────────────────────────────────
        Route::get('/get-symbols',      [McxOiivAutoController::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze-pece',     [McxOiivAutoController::class, 'analyzePECESignals'])->name('analyze-pece');
        Route::post('/calculate-profit',[McxOiivAutoController::class, 'calculateProfit'])->name('calculate-profit');
        Route::post('/clear-cache',     [McxOiivAutoController::class, 'clearPriceCache'])->name('clear-cache');

        // ── Config CRUD ───────────────────────────────────────────────────────────
        Route::get('/config',           [McxOiivAutoController::class, 'config'])->name('config');
        Route::post('/store',           [McxOiivAutoController::class, 'store'])->name('store');
        Route::post('/update/{id}',     [McxOiivAutoController::class, 'update'])->name('update');
        Route::post('/toggle/{id}',     [McxOiivAutoController::class, 'toggleStatus'])->name('toggle');
        Route::delete('/destroy/{id}',  [McxOiivAutoController::class, 'destroy'])->name('destroy');

        // ── Orders ────────────────────────────────────────────────────────────────
        Route::get('/orders/{id}',      [McxOiivAutoController::class, 'viewOrders'])->name('orders');
    });
    
    Route::middleware(['auth'])->prefix('fut-ohl-auto')->name('fut-ohl-auto.')->group(function () {

        // Config page (list + create/edit modals + Run Now button)
        Route::get('/config',              [FutOhlAutoController::class, 'config'])->name('config');

        // Config CRUD
        Route::post('/config',             [FutOhlAutoController::class, 'store'])->name('store');
        Route::put('/config/{id}',         [FutOhlAutoController::class, 'update'])->name('update');
        Route::post('/config/{id}/toggle', [FutOhlAutoController::class, 'toggleStatus'])->name('toggle');
        Route::delete('/config/{id}',      [FutOhlAutoController::class, 'destroy'])->name('destroy');

        // Orders view per config
        Route::get('/orders/{configId}',   [FutOhlAutoController::class, 'viewOrders'])->name('orders');

        // Manual trigger endpoints (called by "Run Now" and "Place Pending" buttons via AJAX)
        Route::post('/run-now',            [FutOhlAutoController::class, 'runNow'])->name('run-now');
        Route::post('/place-pending',      [FutOhlAutoController::class, 'placePending'])->name('place-pending');
    });

    Route::middleware(['auth'])->prefix('ohlc-signal')->name('ohlc.')->group(function () {

        // ── Page ──────────────────────────────────────────────────────────────
        Route::get('/index',             [OIIVAutoOHLCController::class, 'index'])->name('index');

        // ── AJAX ──────────────────────────────────────────────────────────────
        Route::get('/get-series',   [OIIVAutoOHLCController::class, 'getSeries'])->name('series');
        Route::get('/analyze',      [OIIVAutoOHLCController::class, 'analyze'])->name('analyze');
    });

    Route::middleware(['auth'])->prefix('pivot-signal')->name('pivot-signal.')->group(function () {

        // ── Signal Page & API ──────────────────────────────────────────────
        Route::get('/index',           [PivotSignalController::class, 'index'])->name('index');
        Route::get('/signals',         [PivotSignalController::class, 'getSignals'])->name('signals');
        Route::get('/series',          [PivotSignalController::class, 'getSeries'])->name('series');

        // ── Config CRUD ────────────────────────────────────────────────────
        Route::get('/config',          [PivotSignalController::class, 'configIndex'])->name('config.index');
        Route::post('/config',         [PivotSignalController::class, 'configStore'])->name('config.store');
        Route::put('/config/{id}',     [PivotSignalController::class, 'configUpdate'])->name('config.update');
        Route::post('/config/{id}/toggle', [PivotSignalController::class, 'configToggle'])->name('config.toggle');
        Route::delete('/config/{id}',  [PivotSignalController::class, 'configDestroy'])->name('config.destroy');
        Route::get('/config/{id}/orders', [PivotSignalController::class, 'configOrders'])->name('config.orders');

        Route::post('config/{id}/run-now', [PivotSignalController::class, 'configRunNow'])->name('config.run-now');
        Route::get('/traps', [PivotSignalController::class, 'getTraps'])->name('traps');
    });

    Route::prefix('mcx-pivot')->name('mcx-pivot.')->middleware(['auth'])->group(function () {

        // Analysis (signal viewer)
        Route::get('analysis',         [McxPivotController::class, 'analysis'])->name('analysis');
        Route::get('signals',          [McxPivotController::class, 'getSignals'])->name('signals');

        // Config CRUD
        Route::get('config',           [McxPivotController::class, 'configIndex'])->name('config.index');
        Route::post('config',          [McxPivotController::class, 'configStore'])->name('config.store');
        Route::put('config/{id}',      [McxPivotController::class, 'configUpdate'])->name('config.update');
        Route::put('config/{id}/toggle', [McxPivotController::class, 'configToggle'])->name('config.toggle');
        Route::delete('config/{id}',   [McxPivotController::class, 'configDestroy'])->name('config.destroy');

        // Orders for a config
        Route::get('config/{id}/orders', [McxPivotController::class, 'configOrders'])->name('config.orders');
    });

    Route::middleware(['auth'])->prefix('daily-oi-sentiment')->name('daily-oi-sentiment.')->group(function () {

        // Dashboard page
        Route::get('/index',          [DailyOiSentimentController::class, 'index'])      ->name('index');

        // Symbols dropdown (GET — same source as OIIVAutoController)
        Route::get('/symbols',   [DailyOiSentimentController::class, 'getSymbols']) ->name('symbols');

        // Main 3-column analysis (GET — date range + optional symbol filter)
        Route::get('/analyze',   [DailyOiSentimentController::class, 'analyze'])    ->name('analyze');
    });

    Route::middleware(['auth'])->prefix('atm-oi-sentiment')->name('atm-oi-sentiment.')->group(function () {

        Route::get('/index',         [AtmOiSentimentController::class, 'index'])      ->name('index');
        Route::get('/symbols',  [AtmOiSentimentController::class, 'getSymbols']) ->name('symbols');
        Route::get('/analyze',  [AtmOiSentimentController::class, 'analyze'])    ->name('analyze');

    });

    Route::middleware(['auth'])->prefix('expiry-oi')->name('expiry-oi.')->group(function () {
        Route::get('/index',  [ExpiryOiController::class, 'index'])->name('index');
        Route::get('/data',   [ExpiryOiController::class, 'getData'])->name('data');
        Route::get('/symbols', [ExpiryOiController::class, 'getSymbols'])->name('symbols');
    });

    Route::get('expiry-oi/scan',       [ExpiryOiScanController::class, 'index'])   ->name('expiry-oi.scan');
    Route::get('expiry-oi/scan/data',  [ExpiryOiScanController::class, 'getData']) ->name('expiry-oi.scan.data');

    Route::prefix('exit-plan')->name('exit-plan.')->group(function () {
        Route::get('/index',         [ExitPlanController::class, 'index'])->name('index');
        Route::get('/signals',  [ExitPlanController::class, 'getExitSignals']) ->name('signals');
        Route::get('/today',    [ExitPlanController::class, 'getTodayExitCheck'])->name('today');
        Route::get('/symbols',  [ExitPlanController::class, 'getSymbols'])->name('symbols');
    });

    
    // Exit Plan — Config & order management
    Route::prefix('exit-plan/config')->name('exit-plan.config.')->group(function () {
        Route::get('/index',             [ExitPlanConfigController::class, 'config'])       ->name('index');
        Route::post('/store',       [ExitPlanConfigController::class, 'store'])        ->name('store');
        Route::post('/update/{id}', [ExitPlanConfigController::class, 'update'])       ->name('update');
        Route::post('/toggle/{id}', [ExitPlanConfigController::class, 'toggleStatus']) ->name('toggle');
        Route::delete('/{id}',      [ExitPlanConfigController::class, 'destroy'])      ->name('destroy');
        Route::get('/orders/{id}',  [ExitPlanConfigController::class, 'viewOrders'])   ->name('orders');
        Route::post('/run/{id}',    [ExitPlanConfigController::class, 'runManually'])  ->name('run');
        Route::post('/run-all',     [ExitPlanConfigController::class, 'runAllSignals'])->name('run-all');
    });

    Route::middleware(['auth'])->prefix('pivot-signal-15')->name('pivot-signal-15.')->group(function () {
    
        // ── Signal Page & API ──────────────────────────────────────────────────
        Route::get('/index',           [PivotSignal15Controller::class, 'index'])->name('index');
        Route::get('/signals',         [PivotSignal15Controller::class, 'getSignals'])->name('signals');
    
        // ── Config CRUD ────────────────────────────────────────────────────────
        Route::get('/config',          [PivotSignal15Controller::class, 'configIndex'])->name('config.index');
        Route::post('/config',         [PivotSignal15Controller::class, 'configStore'])->name('config.store');
        Route::put('/config/{id}',     [PivotSignal15Controller::class, 'configUpdate'])->name('config.update');
        Route::post('/config/{id}/toggle', [PivotSignal15Controller::class, 'configToggle'])->name('config.toggle');
        Route::delete('/config/{id}',  [PivotSignal15Controller::class, 'configDestroy'])->name('config.destroy');
        Route::get('/config/{id}/orders', [PivotSignal15Controller::class, 'configOrders'])->name('config.orders');
        Route::post('/config/{id}/run-now', [PivotSignal15Controller::class, 'configRunNow'])->name('config.run-now');
    });
 
    Route::middleware(['auth'])->prefix('option-fair-value')->name('option-fair-value.')->group(function () {
        Route::get('/index',          [OptionFairValueController::class, 'index'])->name('index');
        Route::get('/symbols',   [OptionFairValueController::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze',   [OptionFairValueController::class, 'analyze'])->name('analyze');
    });

    Route::middleware(['auth'])->prefix('smart-money')->name('smart-money.')->group(function () {
        Route::get('/index',           [SmartMoneyAnalysisController::class, 'index'])  ->name('index');
        Route::get('/signals',         [SmartMoneyAnalysisController::class, 'signals'])->name('signals');  // ← AJAX JSON
        Route::get('/{symbol}',        [SmartMoneyAnalysisController::class, 'show'])   ->name('show');
    });

    Route::middleware(['auth'])->prefix('straddle-strangle')->name('straddle-strangle.')->group(function () {
        Route::get('/index',           [StraddleStrangleController::class, 'index'])->name('index');
        Route::get('/data',            [StraddleStrangleController::class, 'getData'])->name('data');
        Route::post('/clear-ltp-cache',[StraddleStrangleController::class, 'clearLtpCache'])->name('clear-ltp-cache');
    });   
 
    Route::middleware(['auth'])->prefix('straddle-strangle-15')->name('straddle-strangle-15.')->group(function () {
        Route::get('/index',     [StraddleStrangle15Controller::class, 'index'])->name('index');
        Route::get('/data', [StraddleStrangle15Controller::class, 'getData'])->name('data');
        Route::post('/clear-ltp-cache', [StraddleStrangle15Controller::class, 'clearLtpCache'])->name('clear-ltp-cache');
    });
 
    Route::middleware(['auth'])->prefix('oi-strategy')->name('oi-strategy.')->group(function () {
        Route::get('/index',                [OIStrategyController::class, 'index'])         ->name('index');
        Route::get('/get-symbols',     [OIStrategyController::class, 'getSymbols'])    ->name('symbols');
        Route::get('/analyze',         [OIStrategyController::class, 'analyzeSignals'])->name('analyze');
    });
 
    Route::middleware(['auth'])
    ->prefix('next-series-daily')
    ->name('next-series-daily.')
    ->group(function () {
        Route::get('/index',        [NextSeriesDailyAnalysisController::class, 'index'])      ->name('index');
        Route::get('/analyze', [NextSeriesDailyAnalysisController::class, 'analyze'])    ->name('analyze');
        Route::get('/symbols', [NextSeriesDailyAnalysisController::class, 'getSymbols']) ->name('symbols');
    });
 
    Route::middleware(['auth'])->prefix('volume-spike-15')->name('volume-spike-15.')->group(function () {
        Route::get('/index',   [VolumeSpike15Controller::class, 'index'])->name('index');
        Route::get('/signals', [VolumeSpike15Controller::class, 'getSignals'])->name('signals');
    });
 
    Route::middleware(['auth'])->prefix('smart-volume-spike-15')->name('smart-volume-spike-15.')->group(function () {
        Route::get('/index',   [SmartVolumeSpike15Controller::class, 'index'])->name('index');
        Route::get('/signals', [SmartVolumeSpike15Controller::class, 'getSignals'])->name('signals');
    });
 
    Route::middleware(['auth'])->prefix('signal-intelligence-5min')->name('signal-intel-5min.')->group(function () {
    
        // ── Signal Page & API ──────────────────────────────────────────────────
        Route::get('/index',        [SignalIntelligence5minController::class, 'index'])->name('index');
        Route::get('/signals',      [SignalIntelligence5minController::class, 'getSignals'])->name('signals');
    
        // ── Time Performance (stock-specific best/worst time zones) ───────────
        Route::get('/time-performance',        [SignalIntelligence5minController::class, 'getTimePerformance'])->name('time-performance');
        Route::post('/time-performance/build', [SignalIntelligence5minController::class, 'buildTimePerformance'])->name('time-performance.build');
    
        // ── Next Day Prediction ────────────────────────────────────────────────
        Route::get('/next-day-prediction', [SignalIntelligence5minController::class, 'getNextDayPrediction'])->name('next-day-prediction');
    });

    Route::middleware(['auth'])->prefix('smart-volume-daily-eod')->name('smart-volume-daily-eod.')->group(function () {
        Route::get('/index',   [SmartVolumeDailyEodController::class, 'index'])->name('index');
        Route::get('/signals', [SmartVolumeDailyEodController::class, 'getSignals'])->name('signals');
    });

    // ─── EOD Signal (daily trade picker) ─────────────────────────────────────────
    Route::middleware(['auth'])->prefix('eod-signal')->name('eod-signal.')->group(function () {
        Route::get('/index',   [EodSignalController::class,   'index'])->name('index');
        Route::get('/signals', [EodSignalController::class,   'getSignals'])->name('signals');
    });
    
    Route::middleware(['auth'])->prefix('eod-backtest')->name('eod-backtest.')->group(function () {
        Route::get('/index',  [EodBacktestController::class, 'index'])->name('index');
        Route::get('/run',    [EodBacktestController::class, 'runForDate'])->name('run');
        Route::get('/contra', [EodBacktestController::class, 'runContra'])->name('contra');  // ← NEW
        Route::get('/both',   [EodBacktestController::class, 'runBoth'])->name('both');      // ← NEW
        Route::get('/dates',  [EodBacktestController::class, 'getAvailableDates'])->name('dates');
    });

    Route::middleware(['auth'])->prefix('straddle-exit')->name('straddle-exit.')->group(function () {
        Route::get('/index',       [StraddleExitController::class, 'index'])       ->name('index');
        Route::get('/get-symbols', [StraddleExitController::class, 'getSymbols'])  ->name('symbols');
        Route::get('/analyze',     [StraddleExitController::class, 'analyzeExit']) ->name('analyze');
    });

    Route::middleware(['auth'])->prefix('trade-backtest')->name('trade-backtest.')->group(function () {
        Route::get('/index',         [TradeBacktestController::class, 'index'])->name('index');
        Route::get('/table',         [TradeBacktestController::class, 'table'])->name('table');
        Route::post('/upload',       [TradeBacktestController::class, 'upload'])->name('upload');
        Route::post('/upload-table', [TradeBacktestController::class, 'uploadTable'])->name('uploadTable');
    });

    Route::middleware(['auth'])->prefix('portfolio/broker-stop-loss-config')->name('portfolio.broker-stop-loss-config.')->group(function () {
        Route::get('/index',               [BrokerStopLossConfigController::class, 'index'])->name('index');
        Route::post('/store',              [BrokerStopLossConfigController::class, 'store'])->name('store');
        Route::put('/update/{id}',         [BrokerStopLossConfigController::class, 'update'])->name('update');
        Route::delete('/destroy/{id}',     [BrokerStopLossConfigController::class, 'destroy'])->name('destroy');
        Route::post('/toggle/{id}',        [BrokerStopLossConfigController::class, 'toggleActive'])->name('toggle');
        Route::get('/get',                 [BrokerStopLossConfigController::class, 'getConfigs'])->name('get');
        Route::post('/execute',            [BrokerStopLossConfigController::class, 'execute'])->name('execute');
        Route::post('/execute-one/{id}',   [BrokerStopLossConfigController::class, 'executeOne'])->name('execute-one');
    }); 
    
    Route::middleware(['auth'])->prefix('oiiv-tri')->name('oiiv-tri.')->group(function () {
    
        // ── Page ──────────────────────────────────────────────────────────────────
        Route::get('/index',         [OIIVTriSentimentController::class, 'index'])      ->name('index');
    
        // ── API endpoints ─────────────────────────────────────────────────────────
        Route::get('/symbols',  [OIIVTriSentimentController::class, 'getSymbols']) ->name('symbols');
        Route::get('/analyze',  [OIIVTriSentimentController::class, 'analyzeSignals'])->name('analyze');
    
        // ── Profit calculation (POST, same as oiiv-auto) ──────────────────────────
        Route::post('/calculate-profit', [OIIVTriSentimentController::class, 'calculateProfit'])->name('calculate-profit');
    });

    Route::middleware(['auth'])->prefix('next-series-oiiv')->name('next-series-oiiv.')->group(function () {
        Route::get('/pece-analysis',         [NextSeriesOIIVAutoController::class, 'peCeAnalysis'])     ->name('pece-analysis');
        Route::get('/get-symbols',           [NextSeriesOIIVAutoController::class, 'getSymbols'])        ->name('symbols');
        Route::get('/analyze-pece-signals',  [NextSeriesOIIVAutoController::class, 'analyzePECESignals'])->name('analyze-pece');
        Route::post('/calculate-profit',     [NextSeriesOIIVAutoController::class, 'calculateProfit'])   ->name('calculate-profit');
    });
 
    // ── Analysis routes (already existing) ─────────────────────────────────────
    // Route::middleware(['auth'])->prefix('nifty-driven-breakout')->name('nifty-driven-breakout.')->group(function () {
    //     Route::get('/index',       [NiftyDrivenAllSymbolsController::class, 'index'])      ->name('index');
    //     Route::get('/get-symbols', [NiftyDrivenAllSymbolsController::class, 'getSymbols']) ->name('symbols');
    //     Route::get('/analyze',     [NiftyDrivenAllSymbolsController::class, 'analyze'])    ->name('analyze');
    
    //     // ── NEW: Config & order management ────────────────────────────────────
    //     Route::get('/config',                  [NiftyDrivenBreakoutConfigController::class, 'config'])       ->name('config');
    //     Route::post('/config',                 [NiftyDrivenBreakoutConfigController::class, 'store'])        ->name('config-store');
    //     Route::put('/config/{id}',             [NiftyDrivenBreakoutConfigController::class, 'update'])       ->name('config-update');
    //     Route::post('/config/{id}/toggle',     [NiftyDrivenBreakoutConfigController::class, 'toggleStatus']) ->name('toggle');
    //     Route::delete('/config/{id}',          [NiftyDrivenBreakoutConfigController::class, 'destroy'])      ->name('destroy');
    //     Route::get('/orders/{configId}',       [NiftyDrivenBreakoutConfigController::class, 'orders'])       ->name('orders');
    //     Route::post('/run-now',                [NiftyDrivenBreakoutConfigController::class, 'runNow'])       ->name('run-now');
    // });

    // ── NIFTY-Driven Breakout routes ──────────────────────────────────────────────
    Route::middleware(['auth'])->prefix('nifty-driven-breakout')->name('nifty-driven-breakout.')->group(function () {
        Route::get('/index',       [NiftyDrivenAllSymbolsController::class, 'index'])      ->name('index');
        Route::get('/get-symbols', [NiftyDrivenAllSymbolsController::class, 'getSymbols']) ->name('symbols');
        Route::get('/analyze',     [NiftyDrivenAllSymbolsController::class, 'analyze'])    ->name('analyze');

        // ── NEW: Exit P&L table (aggregate exit scenarios at every 15-min candle) ──
        Route::get('/exit-pnl',    [NiftyDrivenAllSymbolsController::class, 'exitPnl'])    ->name('exit-pnl');

        // ── Config & order management ──────────────────────────────────────────────
        Route::get('/config',                  [NiftyDrivenBreakoutConfigController::class, 'config'])       ->name('config');
        Route::post('/config',                 [NiftyDrivenBreakoutConfigController::class, 'store'])        ->name('config-store');
        Route::put('/config/{id}',             [NiftyDrivenBreakoutConfigController::class, 'update'])       ->name('config-update');
        Route::post('/config/{id}/toggle',     [NiftyDrivenBreakoutConfigController::class, 'toggleStatus']) ->name('toggle');
        Route::delete('/config/{id}',          [NiftyDrivenBreakoutConfigController::class, 'destroy'])      ->name('destroy');
        Route::get('/orders/{configId}',       [NiftyDrivenBreakoutConfigController::class, 'orders'])       ->name('orders');
        Route::post('/run-now',                [NiftyDrivenBreakoutConfigController::class, 'runNow'])       ->name('run-now');
    });
 
    Route::middleware(['auth'])->prefix('nifty50-sector-trend')->name('nifty50-sector.')->group(function () {
 
        Route::get('/index', [Nifty50SectorTrendController::class, 'index'])
            ->name('index');
    
        // GET /nifty50-sector-trend/analyze?date=2025-03-31
        Route::get('/analyze', [Nifty50SectorTrendController::class, 'analyze'])
            ->name('analyze');
    
    });
 
    Route::middleware(['auth'])->prefix('banknifty-sector-trend')->name('banknifty-sector.')->group(function () {
    
        Route::get('/index', [BankNiftySectorTrendController::class, 'index'])
            ->name('index');
    
        // GET /banknifty-sector-trend/analyze?date=2025-03-31
        Route::get('/analyze', [BankNiftySectorTrendController::class, 'analyze'])
            ->name('analyze');
    
    });
 
    Route::middleware(['auth'])->prefix('sensex-expiry')->name('sensex-expiry.')->group(function () {
        Route::get('/index', [SensexExpiryAnalysisController::class, 'index'])->name('index');
        Route::get('/analyze', [SensexExpiryAnalysisController::class, 'analyze'])->name('analyze');
    });

    Route::middleware(['auth'])->prefix('sensex-backtest')->name('sensex-backtest.')->group(function () {
        Route::get('/index', [SensexBacktestController::class, 'index'])->name('index');
        Route::get('/analyze', [SensexBacktestController::class, 'analyze'])->name('analyze');
        Route::get('/backtest', [SensexBacktestController::class, 'backtest'])->name('backtest');
    });

    Route::middleware(['auth'])->prefix('sensex-intraday')->name('sensex-intraday.')->group(function () {
        Route::get('/index', [SensexIntradayController::class, 'index'])->name('index');
        Route::get('/data', [SensexIntradayController::class, 'data'])->name('data');
    });

    Route::middleware(['auth'])->prefix('scalping')->name('scalping.')->group(function () {
        Route::get('/index',    [VolatileIndexScalpingController::class, 'index'])      ->name('index');
        Route::get('/symbols',  [VolatileIndexScalpingController::class, 'getSymbols']) ->name('symbols');
        Route::get('/signals',  [VolatileIndexScalpingController::class, 'getSignals']) ->name('signals');
        Route::get('/heatmap',  [VolatileIndexScalpingController::class, 'getHeatmap']) ->name('heatmap');
    });
 
    Route::middleware(['auth'])->prefix('account-wise')->name('account-wise.')->group(function () {
        Route::get('/zzl',  [AccountWiseAnalysisController::class, 'index'])->name('zzl');
        Route::get('/oqj',  [AccountWiseAnalysisController::class, 'index'])->name('oqj');
        Route::get('/analyze', [AccountWiseAnalysisController::class, 'analyze'])->name('analyze');
    });

    Route::middleware(['auth'])->prefix('universal-btst')->name('universal-btst.')->group(function () {
        Route::get('/index',        [UniversalBTSTController::class, 'index'])->name('index');
        Route::get('/analyze', [UniversalBTSTController::class, 'analyze'])->name('analyze');
    });

    Route::middleware(['auth'])->prefix('universal-btst-reverse')->name('universal-btst-reverse.')->group(function () {
        Route::get('/index',        [UniversalBTSTReverseController::class, 'index'])->name('index');
        Route::get('/analyze', [UniversalBTSTReverseController::class, 'analyze'])->name('analyze');
    });

    Route::middleware(['auth'])->prefix('stock-signals')->name('stock-signals.')->group(function () {
        Route::get('/index',   [StockSignalController::class, 'index'])->name('index');
        Route::get('/analyze', [StockSignalController::class, 'analyze'])->name('analyze');
        Route::get('/detail',  [StockSignalController::class, 'detail'])->name('detail');
    });

    Route::middleware(['auth'])->prefix('gann')->name('gann.')->group(function () {
        Route::get('/index',          [GannOctaveController::class, 'index'])->name('index');
        Route::get('/analyze',   [GannOctaveController::class, 'analyze'])->name('analyze');
        Route::get('/symbols',   [GannOctaveController::class, 'getSymbols'])->name('symbols');
    });
 
    // OI Engine — Advanced Phase + Speed + Trend + Intent Analysis
    Route::middleware(['auth'])->prefix('oi-engine')->name('oi-engine.')->group(function () {
    
        // Main page
        Route::get('/index', [OIEngineController::class, 'index'])->name('index');
    
        // AJAX: fetch symbols list
        Route::get('/symbols', [OIEngineController::class, 'getSymbols'])->name('symbols');
    
        // AJAX: run the engine analysis
        Route::get('/analyze', [OIEngineController::class, 'analyze'])->name('analyze');
    });
 
    Route::middleware(['auth'])->prefix('near-strike-oi')->name('near-strike-oi.')->group(function () {
        Route::get('/index',              [NearStrikeOIController::class, 'index'])->name('index');
        Route::get('/symbols',       [NearStrikeOIController::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze',       [NearStrikeOIController::class, 'analyze'])->name('analyze');
        Route::post('/calculate-profit', [NearStrikeOIController::class, 'calculateProfit'])->name('calculate-profit');
        Route::get('/export', [NearStrikeOIController::class, 'exportFilteredData'])->name('export');
    });
 
    Route::middleware(['auth'])->prefix('multiday-oi')->name('multiday-oi.')->group(function () {
        Route::get('/index',         [MultiDayOIController::class, 'index'])->name('index');
        Route::get('/symbols',  [MultiDayOIController::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze',  [MultiDayOIController::class, 'analyze'])->name('analyze');
    });
 
    Route::middleware(['auth'])->prefix('oi-dominance-v2')->name('oi-dominance-v2.')->group(function () {
        Route::get('/index',        [OIDominanceV2Controller::class, 'index'])->name('index');
        Route::get('/symbols', [OIDominanceV2Controller::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze', [OIDominanceV2Controller::class, 'analyze'])->name('analyze');
    });

    Route::middleware(['auth'])->prefix('auro-oi')->name('auro-oi.')->group(function () {
        Route::get('/index',        [AuroOIAnalysisController::class, 'index'])->name('index');
        Route::get('/analyze', [AuroOIAnalysisController::class, 'analyze'])->name('analyze');
    });

    // ── NEW: All-symbol OI scanner ───────────────────────────────
    Route::prefix('oi-scanner')->name('oi-scanner.')->group(function () {
        Route::get('/index',   [AllSymbolOIAnalysisController::class, 'index'])->name('index');
        Route::get('/analyze', [AllSymbolOIAnalysisController::class, 'analyze'])->name('analyze');
    });

    Route::prefix('options')->name('options.')->group(function () {
        // Main pages
        Route::get('/intraday',          [OptionAnalysisController::class, 'intraday'])->name('intraday');
        Route::get('/swing',             [OptionAnalysisController::class, 'swing'])->name('swing');
        Route::get('/intraday-backtest', [OptionAnalysisController::class, 'intradayBacktest'])->name('intraday-backtest');
        Route::get('/swing-backtest',    [OptionAnalysisController::class, 'swingBacktest'])->name('swing-backtest');
    
        // AJAX JSON endpoints
        Route::get('/intraday-data',     [OptionAnalysisController::class, 'intradayData'])->name('intraday-data');
        Route::get('/swing-data',        [OptionAnalysisController::class, 'swingData'])->name('swing-data');
    });
 
    // Route::prefix('mf-signals')->name('mf-signals.')->group(function () {
    //     Route::get('/index',        [MFSignalScannerController::class, 'index'])->name('index');
    //     Route::get('/scan',         [MFSignalScannerController::class, 'scan'])->name('scan');
    //     Route::get('/performance',  [MFSignalScannerController::class, 'fundPerformance'])->name('performance');
    //     Route::post('/save-amount', [MFSignalScannerController::class, 'saveFundAmount'])->name('save-amount');
    //     Route::post('/close',       [MFSignalScannerController::class, 'closePosition'])->name('close-position');
    // });
 
    Route::prefix('mf-backtest')->name('mf-backtest.')->group(function () {
        Route::get('/index',        [MFBacktestController::class, 'index'])->name('index');
        Route::get('/simulate',     [MFBacktestController::class, 'simulate'])->name('simulate');
        Route::post('/save-amount', [MFBacktestController::class, 'saveFundAmount'])->name('save-amount');
    });
 
   // ── FUT + Option Sell Strategy ────────────────────────────────────────────
    Route::prefix('fut-option-strategy')->name('fut-option-strategy.')->group(function () {
    
        // Page view
        Route::get('/index',          [App\Http\Controllers\User\FutOptionStrategyController::class, 'index'])
            ->name('index');
    
        // AJAX: list of symbols (for the multi-select filter)
        Route::get('/symbols',   [App\Http\Controllers\User\FutOptionStrategyController::class, 'getSymbols'])
            ->name('symbols');
    
        // AJAX: main analysis endpoint
        Route::get('/analyze',   [App\Http\Controllers\User\FutOptionStrategyController::class, 'analyze'])
            ->name('analyze');
    });

    Route::middleware(['auth'])->prefix('intraday-oi')->name('intraday-oi.')->group(function () {
        Route::get('/index',        [IntradayOIController::class, 'index'])->name('index');
        Route::get('/symbols', [IntradayOIController::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze', [IntradayOIController::class, 'analyzeSignals'])->name('analyze');
    });
    
    Route::prefix('trend-predictor')->name('trend-predictor.')->group(function () {
        Route::get('/index',        [TrendPredictorController::class, 'index'])->name('index');
        Route::get('/symbols', [TrendPredictorController::class, 'symbols'])->name('symbols');
        Route::get('/predict', [TrendPredictorController::class, 'predict'])->name('predict');
    });

    // ── OIIV Order Book & Positions ───────────────────────────────────────────────
    Route::middleware(['auth'])->prefix('oiiv-orders')->name('oiiv-orders.')->group(function () {
    
        // ── Pages ─────────────────────────────────────────────────────────────
        Route::get('/order-book',  [\App\Http\Controllers\User\OiivOrderBookController::class, 'ordersPage'])->name('order-book');
        Route::get('/positions',   [\App\Http\Controllers\User\OiivOrderBookController::class, 'positionsPage'])->name('positions');
    
        // ── Order book API ────────────────────────────────────────────────────
        Route::get('/fetch-orders',     [\App\Http\Controllers\User\OiivOrderBookController::class, 'fetchOrders'])->name('fetch-orders');
        Route::post('/modify-order',    [\App\Http\Controllers\User\OiivOrderBookController::class, 'modifyOrder'])->name('modify-order');
        Route::post('/cancel-order',    [\App\Http\Controllers\User\OiivOrderBookController::class, 'cancelOrder'])->name('cancel-order');
        Route::get('/available-dates',  [\App\Http\Controllers\User\OiivOrderBookController::class, 'availableDates'])->name('available-dates');
    
        // ── Positions API ─────────────────────────────────────────────────────
        Route::get('/fetch-positions',    [\App\Http\Controllers\User\OiivOrderBookController::class, 'fetchPositions'])->name('fetch-positions');
        Route::post('/square-off',        [\App\Http\Controllers\User\OiivOrderBookController::class, 'squareOffPosition'])->name('square-off');
    
        // ── Manual sync trigger ───────────────────────────────────────────────
        Route::post('/trigger-sync',      [\App\Http\Controllers\User\OiivOrderBookController::class, 'triggerSync'])->name('trigger-sync');

        // ── Fetch Ltps ───────────────────────────────────────────────
        Route::get('/fetch-ltps',      [\App\Http\Controllers\User\OiivOrderBookController::class, 'fetchLtps'])->name('fetch-ltps');
    });

    Route::middleware(['auth'])->prefix('oiiv-btst')->name('oiiv-btst.')->group(function () {
        Route::get('/config',          [\App\Http\Controllers\User\OiivBtstConfigController::class, 'index'])->name('index');
        Route::post('/store',          [\App\Http\Controllers\User\OiivBtstConfigController::class, 'store'])->name('store');
        Route::put('/update/{id}',     [\App\Http\Controllers\User\OiivBtstConfigController::class, 'update'])->name('update');
        Route::delete('/destroy/{id}', [\App\Http\Controllers\User\OiivBtstConfigController::class, 'destroy'])->name('destroy');
        Route::post('/toggle/{id}',    [\App\Http\Controllers\User\OiivBtstConfigController::class, 'toggleActive'])->name('toggle');
        Route::post('/run',            [\App\Http\Controllers\User\OiivBtstConfigController::class, 'runManual'])->name('run');
        Route::get('/today-orders',    [\App\Http\Controllers\User\OiivBtstConfigController::class, 'todayOrders'])->name('today-orders');
    });

    // ── Monthly P&L Dashboard ─────────────────────────────────────────────────
    Route::prefix('fut-option-monthly')->name('fut-option-monthly.')->group(function () {
    
        // Dashboard page
        Route::get('/index',   [App\Http\Controllers\User\FutOptionMonthlyController::class, 'index'])
            ->name('index');
    
        // AJAX: monthly grouped data
        Route::get('/analyze', [App\Http\Controllers\User\FutOptionMonthlyController::class, 'analyze'])
            ->name('analyze');
    });

    Route::middleware(['auth'])->prefix('fut-contrarian')->name('fut-contrarian.')->group(function () {
        // Page
        Route::get('/index',              [FutContrarianController::class, 'index'])->name('index');
    
        // AJAX endpoints
        Route::get('/symbols',       [FutContrarianController::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze',       [FutContrarianController::class, 'analyze'])->name('analyze');
        Route::post('/calculate-pl', [FutContrarianController::class, 'calculatePL'])->name('calculate-pl');
    });

    Route::middleware(['auth'])->prefix('fut-contrarian-monthly')->name('fut-contrarian-monthly.')->group(function () {
        Route::get('/index',             [FutContrarianMonthlyController::class, 'index'])->name('index');
        Route::get('/trade-dates',  [FutContrarianMonthlyController::class, 'getTradeDates'])->name('trade-dates');
        Route::get('/analyze-day',  [FutContrarianMonthlyController::class, 'analyzeDay'])->name('analyze-day');
    });

    Route::middleware(['auth'])->prefix('fut-contrarian-config')->name('fut-contrarian-config.')->group(function () {
 
        // Config CRUD page
        Route::get('/index',                [FutContrarianConfigController::class, 'config'])->name('index');
        Route::get('/symbols',              [FutContrarianConfigController::class, 'getSymbols'])->name('symbols');
        Route::post('/store',               [FutContrarianConfigController::class, 'store'])->name('store');
        Route::put('/update/{id}',          [FutContrarianConfigController::class, 'update'])->name('update');
        Route::post('/toggle/{id}',         [FutContrarianConfigController::class, 'toggleStatus'])->name('toggle');
        Route::delete('/destroy/{id}',      [FutContrarianConfigController::class, 'destroy'])->name('destroy');
    
        // Orders for a config
        Route::get('/orders/{configId}',    [FutContrarianConfigController::class, 'viewOrders'])->name('orders');
    
        // Order book for a single signal order
        Route::get('/order-book/{orderId}', [FutContrarianConfigController::class, 'viewOrderBook'])->name('order-book');
    });



    // Pivot Analysis
    Route::middleware(['auth'])->prefix('pivot-analysis')->name('pivot-analysis.')->group(function () {
        Route::get('/index',       [PivotAnalysisController::class, 'index'])->name('index');
        Route::get('/stock/signals',  [PivotAnalysisController::class, 'stockSignals'])->name('stock.signals');
        Route::get('/fut/signals',    [PivotAnalysisController::class, 'futSignals'])->name('fut.signals');
        Route::get('/option/signals', [PivotAnalysisController::class, 'optionSignals'])->name('option.signals');
    });
 
    Route::middleware(['auth'])->prefix('open-high-low')->name('open-hl.')->group(function () {
        Route::get('/index',     [OpenHighLowController::class, 'index'])->name('index');
        Route::get('/symbols',   [OpenHighLowController::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze',   [OpenHighLowController::class, 'analyze'])->name('analyze');
    });
 
    Route::middleware(['auth'])->prefix('momentum-breakout')->name('momentum-breakout.')->group(function () {
        Route::get('/index',       [MomentumBreakoutController::class, 'index'])->name('index');
        Route::get('/symbols',     [MomentumBreakoutController::class, 'getSymbols'])->name('symbols');
        Route::get('/scan',        [MomentumBreakoutController::class, 'scan'])->name('scan');
    });
 
    Route::middleware(['auth'])->prefix('oi-flow-sentiment')->name('oi-flow-sentiment.')->group(function () {
        Route::get('/index',          [OIFlowSentimentController::class, 'index'])->name('index');
        Route::get('/symbols',   [OIFlowSentimentController::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze',   [OIFlowSentimentController::class, 'analyze'])->name('analyze');
    });
 
    Route::middleware(['auth'])->prefix('intraday-oi-snapshot')->name('intraday-oi-snapshot.')->group(function () {
        Route::get('/index',        [IntradayOISnapshotController::class, 'index'])->name('index');
        Route::get('/symbols', [IntradayOISnapshotController::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze', [IntradayOISnapshotController::class, 'analyze'])->name('analyze');
    });
 
    Route::middleware(['auth'])->prefix('index-driven-signal')->name('index-driven-signal.')->group(function () {
        Route::get('/index', [IndexDrivenSignalController::class, 'index'])->name('index');
        Route::get('/symbols', [IndexDrivenSignalController::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze', [IndexDrivenSignalController::class, 'analyze'])->name('analyze');
        Route::get('/exit-pnl', [IndexDrivenSignalController::class, 'exitPnl'])->name('exit-pnl');
    });

    Route::middleware(['auth'])->prefix('nifty-breakout-analyzer')->name('nifty-breakout-analyzer.')->group(function () {
        Route::get('/index',       [NiftyDrivenBreakoutAnalysisController::class, 'index'])->name('index');
        Route::get('/get-symbols', [NiftyDrivenBreakoutAnalysisController::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze',     [NiftyDrivenBreakoutAnalysisController::class, 'analyze'])->name('analyze');
    });

    Route::middleware(['auth'])->prefix('strata-options-fv')->name('strata-options-fv.')->group(function () {
        Route::get('/index',   [StrataOptionsFairValueController::class, 'index'])->name('index');
        Route::get('/symbols', [StrataOptionsFairValueController::class, 'getSymbols'])->name('symbols');
        Route::get('/analyze', [StrataOptionsFairValueController::class, 'analyze'])->name('analyze');
    });

    Route::middleware(['auth'])->prefix('quantedge-smc')->name('quantedge-smc.')->group(function () {
        Route::get('/index',   [QuantEdgeSmartMoneyController::class, 'index'])->name('index');
        Route::get('/symbols', [QuantEdgeSmartMoneyController::class, 'getSymbols'])->name('symbols');
        Route::get('/signals', [QuantEdgeSmartMoneyController::class, 'signals'])->name('signals');
    });

    Route::middleware(['auth'])->prefix('primeflow-scanner')->name('primeflow-scanner.')->group(function () {
        Route::get('/index', [PrimeFlowScannerController::class, 'index'])  ->name('index');
        Route::get('/data',  [PrimeFlowScannerController::class, 'getData'])->name('data');
    });

    Route::middleware(['auth'])->prefix('straddle-strategy')->name('straddle-strategy.')->group(function () {
        Route::get('/index',  [StraddleStrategyController::class, 'index'])  ->name('index');
        Route::get('/data',   [StraddleStrategyController::class, 'getData'])->name('data');
    });   
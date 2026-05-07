<?php

use Illuminate\Support\Facades\Route;

Route::namespace('Auth')->group(function () {
    Route::controller('LoginController')->group(function () {
        Route::get('/', 'showLoginForm')->name('login');
        Route::post('/', 'login')->name('login');
        Route::get('logout', 'logout')->middleware('admin')->name('logout');
        
    });
    // Admin Password Reset
    Route::controller('ForgotPasswordController')->prefix('password')->name('password.')->group(function(){
        Route::get('reset', 'showLinkRequestForm')->name('reset');
        Route::post('reset', 'sendResetCodeEmail');
        Route::get('code-verify', 'codeVerify')->name('code.verify');
        Route::post('verify-code', 'verifyCode')->name('verify.code');
    });

    Route::controller('ResetPasswordController')->group(function(){
        Route::get('password/reset/{token}', 'showResetForm')->name('password.reset.form');
        Route::post('password/reset/change', 'reset')->name('password.change');
    });
});

Route::middleware('admin')->group(function () {
    Route::controller('AdminController')->group(function(){
        Route::get('dashboard', 'dashboard')->name('dashboard');
        Route::get('profile', 'profile')->name('profile');
        Route::post('profile', 'profileUpdate')->name('profile.update');
        Route::get('password', 'password')->name('password');
        Route::post('password', 'passwordUpdate')->name('password.update');

        //Notification
        Route::get('notifications','notifications')->name('notifications');
        Route::get('notification/read/{id}','notificationRead')->name('notification.read');
        Route::get('notifications/read-all','readAll')->name('notifications.readAll');

        //Report Bugs
        Route::get('request-report','requestReport')->name('request.report');
        Route::post('request-report','reportSubmit');

        Route::get('download-attachments/{file_hash}', 'downloadAttachment')->name('download.attachment');
    });

    // Users Manager
    Route::controller('ManageUsersController')->name('users.')->prefix('users')->group(function(){
        Route::get('/', 'allUsers')->name('all');
        Route::get('add-user', 'addUser')->name('add-user');
        Route::post('add-user', 'storeUser')->name('store-user');
        Route::get('active', 'activeUsers')->name('active');
        Route::get('banned', 'bannedUsers')->name('banned');
        Route::get('email-verified', 'emailVerifiedUsers')->name('email.verified');
        Route::get('email-unverified', 'emailUnverifiedUsers')->name('email.unverified');
        Route::get('mobile-unverified', 'mobileUnverifiedUsers')->name('mobile.unverified');
        Route::get('mobile-verified', 'mobileVerifiedUsers')->name('mobile.verified');
        Route::get('with-balance', 'usersWithBalance')->name('with.balance');

        Route::get('detail/{id}', 'detail')->name('detail');
        Route::post('update/{id}', 'update')->name('update');
        Route::post('add-sub-balance/{id}', 'addSubBalance')->name('add.sub.balance');
        Route::get('send-notification/{id}', 'showNotificationSingleForm')->name('notification.single');
        Route::post('send-notification/{id}', 'sendNotificationSingle')->name('notification.single');
        Route::get('login/{id}', 'login')->name('login');
        Route::post('status/{id}', 'status')->name('status');

        Route::post('update/package/validity', 'updateValidity')->name('update.validity');

        Route::get('send-notification', 'showNotificationAllForm')->name('notification.all');
        Route::post('send-notification', 'sendNotificationAll')->name('notification.all.send');
        Route::get('list', 'list')->name('list');
        Route::get('notification-log/{id}', 'notificationLog')->name('notification.log');
        Route::get('signal-log/{id}', 'signalLog')->name('signal.log');
        Route::get('referrals/{id}', 'referrals')->name('referrals');

        
        Route::get('user-enquiry','getuserEnquiry')->name('getuserEnquiry');
    });

    // Route::controller('TradeController')->group(function(){
    
    //     Route::get('trade-desk-signal', 'index')->name('tradeDeskSignal');
    //     Route::get('trade-position', 'tradePosition')->name('trade.tradePosition');
    //     Route::get('broker-details', 'brokerDetails')->name('trade.brokerDetails');
    //     Route::get('order-book', 'orderBook')->name('trade.orderBook');
    //     Route::get('oms-config', 'omsConfig')->name('trade.omsConfig');
       
    // });


    Route::controller('TradeController')->name('trade.')->prefix('trade')->group(function(){
        Route::get('save-all-angel-instruments', 'saveAllAngelInstruments');
        Route::get('upload-zerodha-instruments', 'uploadZerodhaInstruments')->name('uploadZerodhaInstruments');

        Route::group(['as' => 'trade-desk-signal.', 'prefix' => 'trade-desk-signal'], function() {
            Route::get('/', 'tradeDeskSignal')->name('all');
        });

        Route::group(['as' => 'trade-position.', 'prefix' => 'trade-position'], function() {
            Route::get('/', 'tradePosition')->name('all');
        });

        Route::group(['as' => 'broker-details.', 'prefix' => 'broker-details'], function() {
            Route::get('/', 'brokerDetails')->name('all');
        });

        Route::group(['as' => 'order-book.', 'prefix' => 'order-book'], function() {
            Route::get('/', 'orderBook')->name('all');
        });

        Route::group(['as' => 'oms-config.', 'prefix' => 'oms-config'], function() {
            Route::get('/', 'omsConfig')->name('all');
        });
        
    });

    Route::controller('InvestmentOverviewController')->name('investment.')->prefix('investment')->group(function(){
        Route::group(['as' => 'thematic-portfolios.', 'prefix' => 'thematic-portfolios'], function() {
            Route::get('/', 'allThematicPortfolios')->name('all');
            Route::get('add', 'addThematicPortfolios')->name('add.page');
            Route::post('add', 'addSubmitThematicPortfolios')->name('add.submit');

            Route::get('download', 'templateThematicPortfolioDownload')->name('download.template');
            Route::post('upload', 'uploadThematicPortfolios')->name('upload');
            Route::post('delete', 'deleteThematicPortfolio')->name('delete');
            // New
            Route::get('get-search-client-id', 'getThematicPortfoliosSearchClientId')->name('get-search-client-id');
            Route::get('get-stock-name', 'getThematicPortfolios')->name('get-stock-name');
            Route::post('remove-stock-portfolio', 'removeThematicPortfolios')->name('remove-stock-portfolio');
        });

        // global-stock portfolio
        Route::group(['as' => 'global-stock-portfolios.', 'prefix' => 'global-stock-portfolios'], function() {
            Route::get('/', 'allGlobalStockPortfolios')->name('all');
            Route::get('add', 'addGlobalStockPortfolios')->name('add.page');
            Route::post('add', 'addSubmitGlobalStockPortfolios')->name('add.submit');

            Route::get('download', 'templateGlobalStockPortfolioDownload')->name('download.template');
            Route::post('upload', 'uploadGlobalStockPortfolios')->name('upload');
            Route::get('get-search-client-id', 'getSearchClientId')->name('get-search-client-id');
            Route::get('get-stock-name', 'getStockName')->name('get-stock-name');
            Route::post('remove-stock-portfolio', 'removeStockPortfolio')->name('remove-stock-portfolio');
            Route::post('delete', 'deleteGlobalStockPortfolio')->name('delete');
        });

        // F&O portfolio hedging
        Route::group(['as' => 'fo-portfolio-hedging.', 'prefix' => 'fo-portfolio-hedging'], function() {
            Route::get('/', 'allFoPortfolioHedging')->name('all');
            Route::get('add', 'addFoPortfolioHedging')->name('add.page');
            Route::post('add', 'addSubmitFoPortfolioHedging')->name('add.submit');

            Route::get('get-faport-search-client-id', 'getFoPortSearchClientId')->name('get-faport-search-client-id');
            Route::get('get-foport-name', 'getFoPortfolioHedging')->name('get-foport-name');
            Route::post('remove-foPortfolio', 'removefoPortfolio')->name('remove-foPortfolio');

            Route::get('download', 'templateFoPortfolioHedgingDownload')->name('download.template');
            Route::post('upload', 'uploadFoPortfolioHedging')->name('upload');

            Route::post('delete', 'deleteFoPortfolioHedging')->name('delete');
        });

        // Metals portfolio
        Route::group(['as' => 'metals-portfolios.', 'prefix' => 'metals-portfolios'], function() {
            Route::get('/', 'allMetalsPortfolios')->name('all');
            Route::get('add', 'addMetalsPortfolios')->name('add.page');
            Route::post('add', 'addSubmitMetalsPortfolios')->name('add.submit');


            Route::get('get-metals-search-client-id', 'getMetalsPortfoliosSearchClientId')->name('get-metals-search-client-id');
            Route::get('get-metals-name', 'getMetalsPortfoliosfolio')->name('get-metals-name');
            Route::post('remove-MetalsPortfolios', 'removeMetalsPortfolios')->name('remove-MetalsPortfolios');
            
            Route::get('download', 'templateMetalsPortfolioDownload')->name('download.template');
            Route::post('upload', 'uploadMetalsPortfolios')->name('upload');

            Route::post('delete', 'deleteMetalsPortfolio')->name('delete');

        });
    });

    // Portfolio Insights
    Route::controller('PortfolioInsightsController')->name('portfolio-insights.')->prefix('portfolio-insights')->group(function(){
        // Portfolio Top Gainers
        Route::group(['as' => 'top-gainers.', 'prefix' => 'top-gainers'], function() {
            Route::get('/', 'allTopGainers')->name('all');
            Route::get('add', 'addTopGainers')->name('add.page');
            Route::post('add', 'addSubmitTopGainers')->name('add.submit');
            Route::get('download', 'templateTopGainersDownload')->name('download.template');
            Route::post('upload', 'uploadTopGainers')->name('upload');
            Route::post('delete', 'deleteTopGainer')->name('delete');

            Route::get('get-stock-name', 'getTopGainers')->name('get-stock-name');
            Route::post('remove-stock-portfolio', 'removeTopGainers')->name('remove-stock-portfolio');

        });


        // Portfolio Top Losers
        Route::group(['as' => 'top-losers.', 'prefix' => 'top-losers'], function() {
            Route::get('/', 'allTopLosers')->name('all');
            Route::get('add', 'addTopLosers')->name('add.page');
            Route::post('add', 'addSubmitTopLosers')->name('add.submit');

            Route::get('download', 'templateTopLosersDownload')->name('download.template');
            Route::post('upload', 'uploadTopLosers')->name('upload');

            Route::post('delete', 'deleteTopLoser')->name('delete');
            Route::get('get-stock-name', 'getTopLosers')->name('get-stock-name');
            Route::post('remove-stock-portfolio', 'removeTopLosers')->name('remove-stock-portfolio');
        });

        
        Route::group(['as' => 'strategy.', 'prefix' => 'strategy'], function() {
            Route::get('/', 'allstrategy')->name('all');
            Route::get('add', 'addstrategy')->name('add.page');
            Route::post('post-add','createstrategy')->name('create');
            Route::get('edit-strategy/{id}','editStrategy')->name('edit');
            Route::post('post-edit/{id}','postEdit')->name('postedit');
            Route::get('delete-strategy/{id}','deleteStrategy')->name('delete');
        });
    });

    // Financial Overview
    Route::controller('FinancialOverviewController')->name('financial-overview.')->prefix('financial-overview')->group(function(){
       // Ledger
        Route::group(['as' => 'ledger.', 'prefix' => 'ledger'], function() {
            Route::get('/', 'allLedger')->name('all');

            Route::get('download', 'templateLedgerDownload')->name('download.template');
            Route::post('upload', 'uploadLedger')->name('upload');
            Route::post('delete', 'deleteLedger')->name('delete');

            Route::get('get-search-client-id', 'getLedgerSearchClientId')->name('get-search-client-id');
            Route::get('get-stock-name', 'getLedger')->name('get-stock-name');
            Route::post('remove-stock-portfolio', 'removeLedger')->name('remove-stock-portfolio');
        });

        // Stock Portfolio
        Route::group(['as' => 'stock-portfolio.', 'prefix' => 'stock-portfolio'], function() {
            Route::get('/', 'allStockPortfolio')->name('all');

            Route::get('download', 'templateStockPortfolioDownload')->name('download.template');
            Route::post('upload', 'uploadStockPortfolio')->name('upload');

            Route::post('delete', 'deleteStockPortfolio')->name('delete');
            Route::get('get-search-client-id', 'getSearchClientId')->name('get-search-client-id');
            Route::get('get-stock-name', 'getStockName')->name('get-stock-name');
            Route::post('remove-stock-portfolio', 'removeStockPortfolio')->name('remove-stock-portfolio');
        });
    });

    // Routes for Transactions.
    Route::controller('TransactionController')->name('transaction.')->prefix('transaction')->group(function(){
        Route::get('/', 'allTransactions')->name('all');
        // download transaction template
        Route::get('download', 'templateTransactionDownload')->name('download.template');
        // upload transaction template
        Route::post('upload', 'uploadTransaction')->name('upload');
        Route::post('delete', 'deleteTransaction')->name('delete');

        Route::get('get-stock-name', 'getTransactions')->name('get-stock-name');
        Route::post('remove-stock-portfolio', 'removeTransactions')->name('remove-stock-portfolio');
    });

    // Subscriber
    Route::controller('SubscriberController')->prefix('subscriber')->name('subscriber.')->group(function(){
        Route::get('/', 'index')->name('index');
        Route::get('send-email', 'sendEmailForm')->name('send.email');
        Route::post('remove/{id}', 'remove')->name('remove');
        Route::post('send-email', 'sendEmail')->name('send.email');
    });

    // Package
    Route::controller('PackageController')->name('package.')->prefix('package')->group(function(){
        Route::get('/all', 'all')->name('all');
        Route::post('/add', 'add')->name('add');
        Route::post('/update', 'update')->name('update');
        Route::post('/status/{id}', 'status')->name('status');
        Route::get('/set-fibonaci-variables', 'setFibonaciVariables');
        Route::post('/store-fibonaci-variables', 'storeFibonaciVariables');
        Route::post('/store-angel-api-variables', 'storeAngelApiVariables');
        Route::post('/store-charge-tax-variables', 'storeChargeTaxVariables');
    });

    // Signal
    Route::controller('SignalController')->name('signal.')->prefix('signal')->group(function(){
        Route::get('/all', 'all')->name('all');
        Route::get('/sent', 'sent')->name('sent');
        Route::get('/not-send', 'notSent')->name('not.send');
        Route::get('/add/page', 'addPage')->name('add.page');
        Route::post('/signal', 'add')->name('add');
        Route::get('/edit/{id}', 'edit')->name('edit');
        Route::post('/update', 'update')->name('update');
        Route::post('/delete', 'delete')->name('delete');
    });

    // Referral system for deposit only
    Route::controller('ReferralController')->name('referral.')->prefix('referral')->group(function(){
        Route::get('/setting', 'setting')->name('setting');
        Route::post('/setting/update', 'settingUpdate')->name('setting.update');
        Route::get('/setting/status', 'settingStatus')->name('setting.status');
    });

    // Deposit Gateway
    Route::name('gateway.')->prefix('gateway')->group(function(){

        // Automatic Gateway
        Route::controller('AutomaticGatewayController')->prefix('automatic')->name('automatic.')->group(function(){
            Route::get('/', 'index')->name('index');
            Route::get('edit/{alias}', 'edit')->name('edit');
            Route::post('update/{code}', 'update')->name('update');
            Route::post('remove/{id}', 'remove')->name('remove');
            Route::post('status/{id}', 'status')->name('status');
        });


        // Manual Methods
        Route::controller('ManualGatewayController')->prefix('manual')->name('manual.')->group(function(){
            Route::get('/', 'index')->name('index');
            Route::get('new', 'create')->name('create');
            Route::post('new', 'store')->name('store');
            Route::get('edit/{alias}', 'edit')->name('edit');
            Route::post('update/{id}', 'update')->name('update');
            Route::post('status/{id}', 'status')->name('status');
        });
    });


    // DEPOSIT SYSTEM
    Route::controller('DepositController')->prefix('deposit')->name('deposit.')->group(function(){
        Route::get('/', 'deposit')->name('list');
        Route::get('pending', 'pending')->name('pending');
        Route::get('rejected', 'rejected')->name('rejected');
        Route::get('approved', 'approved')->name('approved');
        Route::get('successful', 'successful')->name('successful');
        Route::get('initiated', 'initiated')->name('initiated');
        Route::get('details/{id}', 'details')->name('details');

        Route::post('reject', 'reject')->name('reject');
        Route::post('approve/{id}', 'approve')->name('approve');

    });

    // Report
    Route::controller('ReportController')->prefix('report')->name('report.')->group(function(){
        Route::get('transaction', 'transaction')->name('transaction');
        Route::get('login/history', 'loginHistory')->name('login.history');
        Route::get('login/ipHistory/{ip}', 'loginIpHistory')->name('login.ipHistory');
        Route::get('notification/history', 'notificationHistory')->name('notification.history');
        Route::get('email/detail/{id}', 'emailDetails')->name('email.details');
        Route::get('signal', 'signal')->name('signal');
    });

    Route::controller('ZerodhaBrokerController')->prefix('zerodha-broker')->name('zerodha-broker.')->group(function () {
        Route::get('/index', 'index')->name('index');
        Route::post('/store','store')->name('store');
        Route::post('/{id}/update','update')->name('update');
        Route::delete('/{id}', 'destroy')->name('destroy');
        Route::get('/{id}/login', 'login')->name('login');
        Route::post('/{id}/update-token', 'updateToken')->name('update-token');
    });

    // ── Symbol List Routes ────────────────────────────────────────────────────────
    Route::controller('SymbolListController')->prefix('symbol-list')->name('symbol-list.')->group(function () {
        Route::get('/index',              'index')->name('index');
        Route::post('/store',        'store')->name('store');
        Route::post('/{id}/update',  'update')->name('update');
        Route::delete('/{id}',       'destroy')->name('destroy');
    });
    
    // ── Analysis Config Routes ────────────────────────────────────────────────────
    Route::controller('AnalysisConfigController')->prefix('analysis-config')->name('analysis-config.')->group(function () {
        Route::get('/index',                        'index')->name('index');
        Route::post('/store',                  'store')->name('store');
        Route::post('/{id}/update',            'update')->name('update');
        Route::delete('/{id}',                 'destroy')->name('destroy');
        Route::get('/{id}/toggle-status',      'toggleStatus')->name('toggle-status');
    });   

    // Admin Support
    Route::controller('SupportTicketController')->prefix('ticket')->name('ticket.')->group(function(){
        Route::get('/', 'tickets')->name('index');
        Route::get('pending', 'pendingTicket')->name('pending');
        Route::get('closed', 'closedTicket')->name('closed');
        Route::get('answered', 'answeredTicket')->name('answered');
        Route::get('view/{id}', 'ticketReply')->name('view');
        Route::post('reply/{id}', 'replyTicket')->name('reply');
        Route::post('close/{id}', 'closeTicket')->name('close');
        Route::get('download/{ticket}', 'ticketDownload')->name('download');
        Route::post('delete/{id}', 'ticketDelete')->name('delete');
    });


    // Language Manager
    Route::controller('LanguageController')->prefix('language')->name('language.')->group(function(){
        Route::get('/', 'langManage')->name('manage');
        Route::post('/', 'langStore')->name('manage.store');
        Route::post('delete/{id}', 'langDelete')->name('manage.delete');
        Route::post('update/{id}', 'langUpdate')->name('manage.update');
        Route::get('edit/{id}', 'langEdit')->name('key');
        Route::post('import', 'langImport')->name('import.lang');
        Route::post('store/key/{id}', 'storeLanguageJson')->name('store.key');
        Route::post('delete/key/{id}', 'deleteLanguageJson')->name('delete.key');
        Route::post('update/key/{id}', 'updateLanguageJson')->name('update.key');
        Route::get('get-keys', 'getKeys')->name('get.key');
    });

    Route::controller('GeneralSettingController')->group(function(){
        // General Setting
        Route::get('general-setting', 'index')->name('setting.index');
        Route::post('general-setting', 'update')->name('setting.update');

        //configuration
        Route::get('setting/system-configuration','systemConfiguration')->name('setting.system.configuration');
        Route::post('setting/system-configuration','systemConfigurationSubmit');

        // Logo-Icon
        Route::get('setting/logo-icon', 'logoIcon')->name('setting.logo.icon');
        Route::post('setting/logo-icon', 'logoIconUpdate')->name('setting.logo.icon');

        //Custom CSS
        Route::get('custom-css','customCss')->name('setting.custom.css');
        Route::post('custom-css','customCssSubmit');

        //Cookie
        Route::get('cookie','cookie')->name('setting.cookie');
        Route::post('cookie','cookieSubmit');

        //maintenance_mode
        Route::get('maintenance-mode','maintenanceMode')->name('maintenance.mode');
        Route::post('maintenance-mode','maintenanceModeSubmit');
    });

    //Cron Configuration
    Route::controller('CronConfigurationController')->name('cron.')->prefix('cron')->group(function () {
        Route::get('index', 'cronJobs')->name('index');
        Route::post('store', 'cronJobStore')->name('store');
        Route::post('update', 'cronJobUpdate')->name('update');
        Route::post('delete/{id}', 'cronJobDelete')->name('delete');
        Route::get('schedule', 'schedule')->name('schedule');
        Route::post('schedule/store', 'scheduleStore')->name('schedule.store');
        Route::post('schedule/status/{id}', 'scheduleStatus')->name('schedule.status');
        Route::get('schedule/pause/{id}', 'schedulePause')->name('schedule.pause');
        Route::get('schedule/logs/{id}', 'scheduleLogs')->name('schedule.logs');
        Route::post('schedule/log/resolved/{id}', 'scheduleLogResolved')->name('schedule.log.resolved');
        Route::post('schedule/log/flush/{id}', 'logFlush')->name('log.flush');
    });

    //Notification Setting
    Route::name('setting.notification.')->controller('NotificationController')->prefix('notification')->group(function(){
        //Template Setting
        Route::get('global','global')->name('global');
        Route::post('global/update','globalUpdate')->name('global.update');
        Route::get('templates','templates')->name('templates');
        Route::get('template/edit/{id}','templateEdit')->name('template.edit');
        Route::post('template/update/{id}','templateUpdate')->name('template.update');

        //Email Setting
        Route::get('email/setting','emailSetting')->name('email');
        Route::post('email/setting','emailSettingUpdate');
        Route::post('email/test','emailTest')->name('email.test');

        //SMS Setting
        Route::get('sms/setting','smsSetting')->name('sms');
        Route::post('sms/setting','smsSettingUpdate');
        Route::post('sms/test','smsTest')->name('sms.test');

        Route::get('notification/push/setting','pushSetting')->name('push');
        Route::post('notification/push/setting','pushSettingUpdate');

        Route::get('notification/telegram/setting','telegramSetting')->name('telegram');
        Route::post('notification/telegram/setting','telegramSettingUpdate');
    });

    // Plugin
    Route::controller('ExtensionController')->prefix('extensions')->name('extensions.')->group(function(){
        Route::get('/', 'index')->name('index');
        Route::post('update/{id}', 'update')->name('update');
        Route::post('status/{id}', 'status')->name('status');
    });


    //System Information
    Route::controller('SystemController')->name('system.')->prefix('system')->group(function(){
        Route::get('info','systemInfo')->name('info');
        Route::get('server-info','systemServerInfo')->name('server.info');
        Route::get('optimize', 'optimize')->name('optimize');
        Route::get('optimize-clear', 'optimizeClear')->name('optimize.clear');
        Route::get('system-update','systemUpdate')->name('update');
        Route::post('update-upload','updateUpload')->name('update.upload');
    });


    // SEO
    Route::get('seo', 'FrontendController@seoEdit')->name('seo');


    // Frontend
    Route::name('frontend.')->prefix('frontend')->group(function () {

        Route::controller('FrontendController')->group(function(){
            Route::get('templates', 'templates')->name('templates');
            Route::post('templates', 'templatesActive')->name('templates.active');
            Route::get('frontend-sections/{key}', 'frontendSections')->name('sections');
            Route::post('frontend-content/{key}', 'frontendContent')->name('sections.content');
            Route::get('frontend-element/{key}/{id?}', 'frontendElement')->name('sections.element');
            Route::post('remove/{id}', 'remove')->name('remove');
            Route::post('import-content/{key}', 'importContent')->name('import');
        });

        // Page Builder
        Route::controller('PageBuilderController')->group(function(){
            Route::get('manage-pages', 'managePages')->name('manage.pages');
            Route::post('manage-pages', 'managePagesSave')->name('manage.pages.save');
            Route::post('manage-pages/update', 'managePagesUpdate')->name('manage.pages.update');
            Route::post('manage-pages/delete/{id}', 'managePagesDelete')->name('manage.pages.delete');
            Route::get('manage-section/{id}', 'manageSection')->name('manage.section');
            Route::post('manage-section/{id}', 'manageSectionUpdate')->name('manage.section.update');
        });

    });
 
    //  Route::controller('ZerodhaBrokerController')
    //     ->prefix('zerodha-broker')
    //     ->name('zerodha-broker.')
    //     ->group(function () {
    
    //     // List all brokers
    //     Route::get('/',          'index')->name('index');
 
    //     // Add a new broker
    //     Route::post('/store',    'store')->name('store');
 
    //     // Edit modal partial (AJAX GET)
    //     Route::get('/{id}/edit', 'edit')->name('edit');
 
    //     // Update broker details
    //     Route::post('/{id}/update', 'update')->name('update');
 
    //     // Delete broker
    //     Route::delete('/{id}',  'destroy')->name('destroy');
 
    //     // Manual login — redirects admin to Zerodha OAuth page
    //     Route::get('/{id}/login', 'login')->name('login');
 
    //     // Zerodha OAuth callback — auto-saves token
    //     Route::get('/callback',  'callback')->name('callback');
 
    //     // Paste callback URL manually to extract & save token
    //     Route::post('/{id}/update-token', 'updateToken')->name('update-token');
 
    //     // Toggle active / inactive
    //     Route::get('/{id}/toggle-status', 'toggleStatus')->name('toggle-status');
 
    //     // JSON: check token validity
    //     Route::get('/{id}/token-status', 'checkTokenStatus')->name('token-status');
    // });
 
    Route::controller('BrokerTimeframeController')
    ->prefix('broker-timeframe')
    ->name('broker-timeframe.')
    ->group(function () {
 
        // Main management page
        Route::get('/', 'index')->name('index');
 
        // ── Assignments ───────────────────────────────────────────────────
        Route::post('/assignment/store',          'storeAssignment')->name('assignment.store');
        Route::get('/assignment/{id}/toggle',     'toggleAssignment')->name('assignment.toggle');
        Route::delete('/assignment/{id}',         'destroyAssignment')->name('assignment.destroy');
 
        // ── Symbols ───────────────────────────────────────────────────────
        Route::post('/symbol/store',              'storeSymbol')->name('symbol.store');
        Route::post('/symbol/bulk',               'bulkAddSymbols')->name('symbol.bulk');
        Route::get('/symbol/{id}/toggle',         'toggleSymbol')->name('symbol.toggle');
        Route::delete('/symbol/{id}',             'destroySymbol')->name('symbol.destroy');
    });


});


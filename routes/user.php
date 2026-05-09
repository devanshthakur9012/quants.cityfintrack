<?php

use Illuminate\Support\Facades\Route;

Route::namespace('User\Auth')->name('user.')->group(function () {

    // Route::controller('LoginController')->group(function(){
    //     Route::get('/login', 'showLoginForm')->name('login');
    //     Route::post('/login', 'login');
    //     Route::get('logout', 'logout')->middleware('auth')->name('logout');
    // });

    // Route::controller('RegisterController')->group(function(){
    //     Route::get('register', 'showRegistrationForm')->name('register');
    //     Route::post('register', 'register')->middleware('registration.status');
    //     Route::post('check-mail', 'checkUser')->name('checkUser');
    // });

    Route::controller('ForgotPasswordController')->prefix('password')->name('password.')->group(function(){
        Route::get('reset', 'showLinkRequestForm')->name('request');
        Route::post('email', 'sendResetCodeEmail')->name('email');
        Route::get('code-verify', 'codeVerify')->name('code.verify');
        Route::post('verify-code', 'verifyCode')->name('verify.code');
    });
    Route::controller('ResetPasswordController')->group(function(){
        Route::post('password/reset', 'reset')->name('password.update');
        Route::get('password/reset/{token}', 'showResetForm')->name('password.reset');
    });
});

Route::middleware('auth')->name('user.')->group(function () {
    //authorization
    Route::namespace('User')->controller('AuthorizationController')->group(function(){
        Route::get('authorization', 'authorizeForm')->name('authorization');
        Route::get('resend-verify/{type}', 'sendVerifyCode')->name('send.verify.code');
        Route::post('verify-email', 'emailVerification')->name('verify.email');
        Route::post('verify-mobile', 'mobileVerification')->name('verify.mobile');
        Route::post('verify-g2fa', 'g2faVerification')->name('go2fa.verify');
    });

    Route::middleware(['check.status'])->group(function () {

        Route::get('user-data', 'User\UserController@userData')->name('data');
        Route::post('user-data-submit', 'User\UserController@userDataSubmit')->name('data.submit');

        Route::middleware('registration.complete')->namespace('User')->group(function () {

            Route::controller('OiBuildUpController')->group(function(){
                Route::get('oi-buildup-data', 'oiBuildupData')->name('oi-buildup-data');
                Route::get('fetch-oi-buildup-data', 'fetchOiBuildupData')->name('fetch-oi-buildup-data');

                // Directional
                Route::get('directional', 'directional')->name('directional');
                Route::get('/directional-fetch', 'directionalFetch')->name('directional-fetch');

                // Bi-Directional
                Route::get('bi-directional', 'biDirectional')->name('bi-directional');
                Route::get('/bi-directional-fetch', 'biDirectionalFetch')->name('bi-directional-fetch');

                // Futures-Direct
                Route::get('futures-direct', 'futuresDirect')->name('futures-direct');
                Route::get('/futures-direct-fetch', 'futuresDirectFetch')->name('futures-direct-fetch');

                // Options-Opposite
                Route::get('options-opposite', 'optionsOpposite')->name('options-opposite');
                Route::get('/options-opposite-fetch', 'optionsOppositeFetch')->name('options-opposite-fetch');

                // Futures-Opposite
                Route::get('futures-opposite', 'futuresOpposite')->name('futures-opposite');
                Route::get('/futures-opposite-fetch', 'futuresOppositeFetch')->name('futures-opposite-fetch');
            });

            Route::controller('PortfolioController')->group(function(){
                Route::get('histofical-portfolio', 'portfolioStrongBullish')->name('histofical-portfolio');
                Route::get('portfolio-histofical-fetch', 'portfolioStrongBullishFetch')->name('portfolio-histofical-fetch');


                // NEW ONE
                Route::get('smart-histofical-portfolio', 'smartPortfolioStrongBullish')->name('smart-histofical-portfolio');
                Route::get('smartPortfolio-histofical-fetch', 'smartPortfolioStrongBullishFetch')->name('smart-portfolio-histofical-fetch');


                
                Route::get('supertrend-analysis', 'supertrendAnalysis')->name('supertrend-analysis');
                Route::get('supertrend-fetch', 'supertrendFetch')->name('supertrend-fetch');
                Route::post('supertrend-calculate', 'calculateAndSaveSupertrend')->name('supertrend-calculate');
                
                Route::get('trend-analyst', 'trendAnalyst')->name('trend-analyst');
                Route::post('trend-fetch', 'trendAnalystFetch')->name('trend-fetch');
                
                Route::get('early-trend-analyst', 'earlyTrendAnalyst')->name('early-trend-analyst');
                Route::post('early-trend-fetch', 'earlyTrendAnalystFetch')->name('early-trend-fetch');

                Route::get('portfolio-mild-bullish', 'portfolioMildBullish')->name('portfolio-mild-bullish');
                Route::get('portfolio-mild-bullish-fetch', 'portfolioMildBullishFetch')->name('portfolio-mild-bullish-fetch');

                Route::get('portfolio-strong-bearish', 'portfolioStrongBearish')->name('portfolio-strong-bearish');
                Route::get('portfolio-strong-bearish-fetch', 'portfolioStrongBearishFetch')->name('portfolio-strong-bearish-fetch');
                       
                Route::get('portfolio-mild-bearish', 'portfolioMildBearish')->name('portfolio-mild-bearish');
                Route::get('portfolio-mild-bearish-fetch', 'portfolioMildBearishFetch')->name('portfolio-mild-bearish-fetch');
            });

            Route::controller('AnalysisController')->group(function(){
                Route::get('analysis', 'analysis')->name('analysis');
                Route::post('analysis-data', 'getSymbolData')->name('analysis.data');
            });

            Route::controller('OrderController')->group(function(){
                Route::get('order-historical-master', 'orderHistoricalMaster')->name('order-historical-master');
                Route::post('store-historical-master', 'storeHistoricalMaster')->name('oms-historical-store');
                Route::get('oms-historical-order/{id}', 'omsHistoricalMasterOrder')->name('oms-historical-order');
                Route::delete('oms-historical-master-destroy/{id}', 'omsHistoricalMasterOrderDestroy')->name('oms-historical-master-destroy');
            });

            Route::prefix('zerodha-auto')->name('zerodha-auto.')->controller('ZerodhaAutoController')->group(function() {
                Route::get('/', 'index')->name('index');
                Route::post('/store', 'store')->name('store');
                Route::get('/orders/{id}', 'viewOrders')->name('orders');
                Route::post('/toggle/{id}', 'toggleStatus')->name('toggle');
                Route::delete('/destroy/{id}', 'destroy')->name('destroy');
                Route::post('update/{id}', 'update')->name('update');
            });

            // In your web.php
            Route::controller('ZerodhaOrderController')->group(function(){
                Route::get('order-zerodha-master', 'orderZerodhaMaster')->name('order-zerodha-master');
                Route::post('zerodha-store', 'storeZerodhaMaster')->name('zerodha-store');
                Route::get('zerodha-order/{id}', 'zerodhaOrderMaster')->name('zerodha-order-detail');
                Route::delete('zerodha-destroy/{id}', 'zerodhaOrderDestroy')->name('zerodha-destroy');
            });

            // Pyramid Orders Routes
            Route::prefix('pyramid-orders')->name('pyramid-orders.')->controller('PyramidOrderController')->group(function(){
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/store', 'store')->name('store');
                Route::get('/show/{id}', 'show')->name('show');
                
                // AJAX routes
                Route::get('/get-expiries', 'getExpiries')->name('get-expiries');
                Route::get('/get-strikes', 'getStrikes')->name('get-strikes');
                Route::get('/get-lot-size', 'getLotSize')->name('get-lot-size');
                Route::post('/preview', 'preview')->name('preview');
            });

            Route::controller('OMSMasterConfigController')->group(function(){
                Route::get('test-kite', 'testKiteAuth')->name('test-kite');
                Route::get('oms-config-master', 'omsConfigMaster')->name('oms-config-master');
                Route::post('store-config-master', 'storeConfigMaster')->name('oms-config-store');
                Route::get('oms-config-order/{id}', 'omsConfigMasterOrder')->name('oms-config-order');
                Route::delete('oms-config-master-destroy/{id}', 'omsConfigMasterOrderDestroy')->name('oms-config-master-destroy');
            });

            Route::controller('TradePositionController')->group(function(){
                Route::get('trade-positions-master', 'tradePositionsMaster')->name('trade-positions-master');
                Route::get('trade-position-master-fetch', 'tradePositionMasterFetch')->name('trade-position-master-fetch');
                Route::post('place-trade-order', 'placeTradeOrder')->name('place-trade-order');
            });

            Route::controller('OMSConfigController')->group(function(){
                Route::get('new-oms-config', 'omsConfig')->name('portfolio.new-oms-config');
                Route::post('new-store-oms-config', 'storeOmsConfig')->name('portfolio.new-store-oms-config');
                Route::post('new-update-oms-config', 'updateOmsConfig')->name('portfolio.new-update-oms-config');
                Route::post('new-get-pe-ce-symbol-names', 'getPeCeSymbolNames');
                Route::post('new-get-omg-config-data', 'getOmgConfigData');
                Route::post('new-remove-oms-config', 'removeOmsConfig')->name('portfolio.new-remove-oms-config');
            });

            Route::controller('FuturesSignalController')->group(function(){
                Route::get('/futures-signal', 'index')->name('futures.signal');
                Route::post('/futures-signal/fetch', 'fetch')->name('futures.signal.fetch');
                Route::get('/futures-signal/detail/{id}', 'detail')->name('futures.signal.detail');
            });

            // NEW CODE
            Route::controller('InstrumentHistoricalController')->group(function(){
                Route::get('/instrument-historical-data', 'instrumentHistoricalData')
                    ->name('instrument.historical.data');
                Route::post('/instrument-historical-data/fetch', 'instrumentHistoricalDataFetch')
                    ->name('instrument.historical.data.fetch');
            });

            // Historical Analysis
            Route::controller('InstrumentHistoricalAnalysisController')->group(function(){
                Route::get('/instrument-historical-analysis', 'historicalAnalysis')
                    ->name('instrument.historical.analysis');
                Route::post('/instrument-historical-analysis/fetch', 'historicalAnalysisFetch')
                    ->name('instrument.historical.analysis.fetch');
            });

            // Volume Analytics
            Route::controller('InstrumentVolumeAnalyticsController')->group(function(){
                Route::get('/instrument-volume-analytics', 'volumeAnalytics')
                    ->name('instrument.volume.analytics');
                Route::post('/instrument-volume-analytics/fetch', 'volumeAnalyticsFetch')
                    ->name('instrument.volume.analytics.fetch');
            });

            Route::controller('HistoricalOptionController')->group(function(){
                Route::get('/historical-options', 'historicalOptions')->name('historical.options');
                Route::post('/historical-options/fetch', 'historicalOptionsFetch')  ->name('historical.options.fetch');

                Route::get('/early-historical-options', 'earlyHistoricalOptions')->name('early.historical.options');
                Route::post('/early-historical-options/fetch', 'earlyHistoricalOptionsFetch')  ->name('early.historical.options.fetch');

                Route::get('/manually-run-early-cron', 'runEarlyHistoricalData')->name('manually.historical.options');

                // COMBINATION ANALYSIS
                // Add these routes to your web.php or routes file
                Route::get('/unified-analysis', 'unifiedAnalysis')->name('unified.analysis');
                Route::post('/unified-analysis/fetch', 'unifiedAnalysisFetch')->name('unified.analysis.fetch');
            });   


            Route::controller('ManualOptionDataController')->group(function(){
                Route::get('/manual-historical-data', 'index')->name('manual.historical.data');
                Route::post('/manual-historical-data/store', 'store')->name('manual.historical.store');
                Route::post('/manual-historical-data/fetch', 'fetch')->name('manual.historical.fetch');
            });


            Route::controller('FinancialAstrologyController')->group(function(){
                Route::get('/financial-astrology', 'financialAstrology')->name('financial.astrology');
                Route::post('/financial-astrology/generate', 'financialAstrologyGenerate')->name('financial.astrology.generate');
            });

            Route::controller('MarketAstroController')->group(function(){
                Route::get('/market-astrology', 'index')->name('market.astrology');
                Route::post('/market-astrology/generate', 'generate')->name('market.astrology.generate');
            });

            // Add these routes to your web.php file (inside your user route group)

            Route::controller('AstroTradingController')->group(function(){
                Route::get('/astro-trading', 'index')->name('astro.trading');
                Route::post('/astro-trading/generate', 'generateWeeklyForecast')->name('astro.trading.generate');
            });
            
            Route::controller('WeeklyAstroController')->group(function(){
                Route::get('/weekly-astro-analysis', 'index')->name('weekly.astro.analysis');
                Route::post('/weekly-astro-analysis/generate', 'generateAnalysis')->name('weekly.astro.generate');
            });
            
            Route::controller('NewAstroTradingController')->group(function(){
                Route::get('/new-astro-trading', 'index')->name('new.astro.trading');
                Route::post('/new-astro-trading/generate', 'generateWeeklyForecast')->name('new.astro.analysis.generate');
            });

            Route::controller('HistoricalOptionAnalysis')->group(function(){
                Route::get('/historical-analysis', 'historicalAnalysis')->name('historical.analysis');
                Route::post('/historical-analysis/fetch', 'historicalAnalysisFetch')  ->name('historical.analysis.fetch');

                Route::get('/volume-historical-analysis', 'volumeAnalytics')->name('volume.analytics');
                Route::post('/volume-historical-analysis/fetch', 'volumeAnalyticsFetch')->name('volume.analytics.fetch');


                
                Route::get('/early-historical-analysis', 'earlyHistoricalAnalysis')->name('early-historical.analysis');
                Route::post('/early-historical-analysis/fetch', 'earlyHistoricalAnalysisFetch')  ->name('early-historical.analysis.fetch');
                
                Route::get('/portfolio-sentiment', 'portfolioSentimentBased')->name('historical.new-portfolio');
                Route::get('/portfolio-sentiment-fetch/fetch', 'portfolioSentimentBasedFetch')->name('portfolio-sentiment-fetch');

            });

            Route::controller('UserController')->group(function(){
                Route::get('dashboard', 'home')->name('home');
                Route::get('dashboard-ajax','homeajax')->name('homeajax');
                Route::get('option-startegies', 'optionStatergy')->name('optionStatergy');
                
                Route::get('oi-buildup-new', 'oiBuildupNew')->name('oi-buildup-new');

                
                Route::get('oi-buildup-fresh', 'oiBuildupFresh')->name('oi-buildup-fresh');
                Route::post('/oi-buildup-fresh/fetch', 'fetchOiBuildupDataFresh')->name('oi.buildup-fresh.fetch');

                Route::get('/oi-transitions', 'showOiTransitions')->name('oi-transitions');
                Route::get('/oi-transitions-fetch', 'showOiTransitionsFetch')->name('oi-transitions-fetch');


                Route::get('oi-buildup', 'oiBuildup')->name('oi-buildup');
                Route::post('/oi-buildup/fetch', 'fetchOiBuildupData')->name('oi.buildup.fetch');
                Route::get('oi-buildup/{symbol}/{type}', 'oiBuildupDetail')->name('oi.buildup.detail');


                Route::get('stratergies-details/{id}', 'stratergyDetails')->name('stratergyDetails');
                Route::get('watch-list', 'watchList')->name('watchList');
                Route::get('watch-list-ajax', 'watchListAjax')->name('watchListAjax');
                Route::get('watch-list-order', 'watchListOrder')->name('watchListOrder');
                Route::get('watch-list-order-ajax', 'watchListOrderAjax')->name('watchListOrderAjax');
                Route::get('watch-list-position', 'watchListPosition')->name('watchListPosition');
                Route::get('watch-list-position-ajax', 'watchListPositionAjax')->name('watchListPositionAjax');
                Route::post('fetch-watch-list-data','fetchwatchList')->name('fetchwatchList');
                Route::post('buy-watch-list-stock','buywishlist')->name('buyWatchListStock');
                Route::post('purchase/package', 'purchasePackage')->name('purchase.package');
                Route::post('renew/package', 'renewPackage')->name('renew.package');
                Route::get('signals', 'signals')->name('signals');
                Route::get('referrals', 'referrals')->name('referrals');
                Route::get('option-analysis', 'OptionAnalysis')->name('option-analysis');
                Route::get('option-analysis-ajax', 'OptionAnalysisAjax')->name('option-analysis-ajax');

                Route::get('predictions', 'predictions')->name('predictions');

                // PAPER TRADING
                Route::get('/paper-trading','paperTrading')->name('paperTrading');
                Route::get('/paper-trading-ajax','paperTradingAjax')->name('paperTradingAjax');
                
                //2FA
                Route::get('twofactor', 'show2faForm')->name('twofactor');
                Route::post('twofactor/enable', 'create2fa')->name('twofactor.enable');
                Route::post('twofactor/disable', 'disable2fa')->name('twofactor.disable');

                //Report
                Route::any('deposit/history', 'depositHistory')->name('deposit.history');
                Route::get('transactions','transactions')->name('transactions');
                Route::get('ledgers', 'ledgers')->name('ledgers');
                Route::get('stock-portfolios', 'stockPortfolios')->name('stock.portfolios');
                Route::get('thematic-portfolios', 'thematicPortfolios')->name('thematic.portfolios');
                Route::get('global-stock-portfolio', 'globalStockPortfolio')->name('global.stock.portfolio');
                Route::get('fo-portfolio-hedging', 'foPortfolioHedging')->name('fo.portfolio.hedging');
                Route::get('metals-portfolio', 'metalsPortfolio')->name('metals.portfolio');
                Route::get('portfolio-top-gainers', 'portfolioTopGainers')->name('portfolio.top.gainers');
                Route::get('portfolio-top-gainers-ajx', 'portfolioTopGainersAjx')->name('portfolio.top.gainers-ajx');

                Route::get('portfolio-greeks', 'portfolioGreeks');
                Route::get('portfolio-greeks-graphs','portfolioGreeksGraphs');
                Route::get('portfolio-top-gainers-stock', 'portfolioTopGainersStock')->name('portfolio.top.gainers-stock');
                Route::get('portfolio-top-gainers-stock-ajx', 'portfolioTopGainersStockAjx')->name('portfolio.top.gainers-stock-ajx');
                Route::get('portfolio-top-losers', 'portfolioTopLosers')->name('portfolio.top.losers');
                Route::get('broker-details', 'brokerDetails')->name('portfolio.broker-details');
                Route::post('store-broker-details', 'storeBrokerDetails')->name('portfolio.store-broker-details');
                Route::post('update-broker-details/{id}', 'updateBrokerDetails')->name('portfolio.update-broker-details');
                Route::get('get-broker-details/{id}', 'getBrokerDetails')->name('portfolio.get-broker-details');
                Route::post('remove-broker-details/{id}', 'removeBrokerDetails')->name('portfolio.remove-broker-details');
                Route::get('trade-book','tradeBook')->name('trade-book');
                Route::get('trade-book-ajax','tradeBookAjax')->name('trade-book-ajax');

                Route::get('fetch-trade-record','fetchTradeRecord')->name('fetch-trade-book');
                Route::get('store-ohlc-record','storenewData')->name('store-ohlc-record');
                Route::get('pl-reports','plReports')->name('pl-reports');
                Route::get('pl-reports-ajax','plReportsAjax')->name('pl-reports-ajax');

                Route::get('attachment-download/{fil_hash}','attachmentDownload')->name('attachment.download');

                Route::get('oms-config', 'omsConfig')->name('portfolio.oms-config');


                Route::post('store-oms-config', 'storeOmsConfig')->name('portfolio.store-oms-config');
                Route::post('update-oms-config', 'updateOmsConfig')->name('portfolio.update-oms-config');
                Route::post('get-pe-ce-symbol-names', 'getPeCeSymbolNames');
                Route::post('get-omg-config-data', 'getOmgConfigData');
                Route::post('remove-oms-config', 'removeOmsConfig')->name('portfolio.remove-oms-config');

                Route::get('order-books', 'orderBooks')->name('order-books');
                Route::get('order-books-ajax', 'orderBooksAjax')->name('order-books-ajax');
                Route::get('trade-positions', 'tradePositions')->name('trade-positions');

                Route::get('match-delta', 'matchDelta')->name('match-delta');
                Route::get('match-delta-ajax', 'ajaxmatchDelta')->name('match-delta-ajax');
                Route::get('match-theta', 'matchTheta')->name('match-theta');
                Route::get('match-theta-ajax', 'ajaxmatchTheta')->name('match-theta-ajax');
                Route::get('match-premium', 'matchPremium')->name('match-premium');
                Route::get('match-premium-ajax', 'ajaxmatchPremium')->name('match-premium-ajax');
                Route::get('match-iv', 'matchIv')->name('match-iv');
                Route::get('match-iv-ajax', 'ajaxmatchIv')->name('match-iv-ajax');

                Route::get('paper-x-factor', 'paperXFactor')->name('paper-x-factor');
                Route::get('paper-x-factor-ajax', 'paperxFactorAjax')->name('paper-x-factor-ajax');
                Route::get('paper-x-factor-combined', 'paperXFactorCombined')->name('paper-x-factor-combined');
                Route::get('paper-x-factor-combined-ajax', 'paperXFactorCombinedAjax')->name('paper-x-factor-combined-ajax');
            });

            //Profile setting
            Route::controller('ProfileController')->group(function(){
                Route::get('info', 'userInfo')->name('info');
                
                Route::get('profile-setting', 'profile')->name('profile.setting');
                Route::post('profile-setting', 'submitProfile');
                Route::get('change-password', 'changePassword')->name('change.password');
                Route::post('change-password', 'submitPassword');
                Route::get('trade-desk-signal', 'tradeDeskSignal')->name('trade-desk-signal');
                // Route::get('oms-config-order', 'omsConfigOrder')->name('oms-config-order');
                Route::post('get-pe-ce-symbol-names-order', 'getPeCeSymbolNamesOrder')->name('get-pe-ce-symbol-names-order');
                Route::post('get-omg-config-data-order', 'getOmgConfigDataOrder')->name('get-omg-config-data-order');
                // Route::get('trade-desk-signal-test', 'tradeDeskSignalTest')->name('trade-desk-signal-test');
            });

        });

        // Payment
        Route::middleware('registration.complete')->prefix('deposit')->name('deposit.')->controller('Gateway\PaymentController')->group(function(){
            Route::any('/', 'deposit')->name('index');
            Route::post('insert', 'depositInsert')->name('insert');
            Route::get('confirm', 'depositConfirm')->name('confirm');
            Route::get('manual', 'manualDepositConfirm')->name('manual.confirm');
            Route::post('manual', 'manualDepositUpdate')->name('manual.update');
        });

    });
    
});

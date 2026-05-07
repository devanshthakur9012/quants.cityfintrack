<?php

namespace App\Providers;

use App\Models\AdminNotification;
use App\Models\Deposit;
use App\Models\Frontend;
use App\Models\Language;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Constants\Status;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        // if (!cache()->get('SystemInstalled')) {
        //     $envFilePath = base_path('.env');
        //     $envContents = file_get_contents($envFilePath);
        //     if (empty($envContents)) {
        //         header('Location: install');
        //         exit;
        //     }else{
                cache()->put('SystemInstalled',true);
        //     }
        // }

        $general = gs();
        $activeTemplate = activeTemplate();

        $viewShare['general'] = $general;
        $viewShare['activeTemplate'] = $activeTemplate;
        $viewShare['activeTemplateTrue'] = activeTemplate(true);
        $viewShare['language'] = Language::all();
        $viewShare['emptyMessage'] = 'Data not found';
        view()->share($viewShare);

        view()->composer('admin.partials.sidenav', function ($view) {
            $view->with([
                'bannedUsersCount'           => User::banned()->count(),
                'emailUnverifiedUsersCount' => User::emailUnverified()->count(),
                'mobileUnverifiedUsersCount'   => User::mobileUnverified()->count(),
                'pendingTicketCount'         => SupportTicket::whereIN('status', [Status::TICKET_OPEN, Status::TICKET_REPLY])->count(),
                'pendingDepositsCount'    => Deposit::pending()->count(),
            ]);
        });

        view()->composer('admin.partials.topnav', function ($view) {
            $view->with([
                'adminNotifications'=>AdminNotification::where('is_read',Status::NO)->with('user')->orderBy('id','desc')->take(10)->get(),
                'adminNotificationCount'=>AdminNotification::where('is_read',Status::NO)->count(),
            ]);
        });

        view()->composer('partials.seo', function ($view) {
            $seo = Frontend::where('data_keys', 'seo.data')->first();
            $view->with([
                'seo' => $seo ? $seo->data_values : $seo,
            ]);
        });

        view()->composer('*', function ($view) {
            $userGlblNameData = null;
            if(auth()->user()){
                $userGlblNameData = auth()->user();
            }
            $view->with([
                'userGlblNameData' => $userGlblNameData,
            ]);
        });

        if($general->force_ssl){
            \URL::forceScheme('https');
        }

        Paginator::useBootstrapFour();
    }
}

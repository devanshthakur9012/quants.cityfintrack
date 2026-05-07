<?php
namespace App\Http\Controllers;
use App\Traits\SupportTicketManager;
use App\Traits\AngelApiAuth;

class TicketController extends Controller
{
    use SupportTicketManager,AngelApiAuth;

    public function __construct()
    {  
        parent::__construct();
        $this->layout = 'frontend';

        $this->middleware(function ($request, $next) {
            $this->user = auth()->user();
            if ($this->user) {
                $this->layout = 'master';
            }
            return $next($request);
        });

        $this->redirectLink = 'ticket.view';
        $this->userType     = 'user';
        $this->column       = 'user_id';
    }

    public function getMarketData(){
        dd($this->getMarketData());
        return response()->json($this->getMarketData());
    }
}

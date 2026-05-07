<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;
use App\Models\DeviceToken;
use App\Models\Frontend;
use App\Models\Language;
use App\Models\Page;
use App\Models\Package;
use App\Models\Subscriber;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Carbon\Carbon;
use App\Models\UserEnquiry;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use App\Constants\Status;
use App\Traits\AngelApiAuth;
use App\Models\OiBuildUp;
use App\Models\TopPortfolio;
use App\Models\PcrVolume;
use App\Imports\ImportProductData;
use Maatwebsite\Excel\Facades\Excel;

class SiteController extends Controller
{
    use AngelApiAuth;

    public function index(Request $request)
    {
        $pageTitle = 'Home';
        $fullUrl = $request->fullUrl();
        $reference = $request->get('reference');
        if ($reference) {
            session()->put('reference', $reference);
        }

        // Default empty collections to avoid breaking
        $topGainer = collect();
        $topLoser = collect();
        $sections = null;

        // Fetch Top Portfolio data
        try {
            $topPortfolios = TopPortfolio::latest()
                ->whereIn('type', ['gainer', 'loser'])
                ->get()
                ->groupBy('type');

            $topGainer = $topPortfolios->get('gainer', collect())->take(5);
            $topLoser  = $topPortfolios->get('loser', collect())->take(5);
        } catch (\Exception $e) {
            \Log::error("Error fetching Top Portfolio data: " . $e->getMessage());
        }
        
        // Fetch homepage sections
        try {
            $sections = Page::where('tempname', $this->activeTemplate)
                            ->where('slug', '/')
                            ->first();
        } catch (\Exception $e) {
            \Log::error("Error fetching homepage sections: " . $e->getMessage());
        }

        return view($this->activeTemplate . 'home', compact('pageTitle', 'sections','topGainer', 'topLoser'));
    }

    public function indexAjax(Request $request)
    {
        $pageTitle = 'Home';
        $fullUrl = $request->fullUrl();

        // Initialize empty collections
        $topGainer = $topLoser = collect();
        $sections = null;

        // Load TopPortfolio (gainers and losers)
        try {
            $topPortfolios = TopPortfolio::latest()
                ->whereIn('type', ['gainer', 'loser'])
                ->get()
                ->groupBy('type');

            $topGainer = $topPortfolios->get('gainer', collect())->take(5);
            $topLoser = $topPortfolios->get('loser', collect())->take(5);
        } catch (\Exception $e) {
            \Log::error("TopPortfolio fetch error: " . $e->getMessage());
        }

        // Load homepage sections
        try {
            $sections = Page::where('tempname', $this->activeTemplate)
                ->where('slug', '/')
                ->first();
        } catch (\Exception $e) {
            \Log::error("Page sections fetch error: " . $e->getMessage());
        }

        // Handle referral
        $reference = $request->get('reference');
        if ($reference) {
            session()->put('reference', $reference);
        }

        return view($this->activeTemplate . 'sections.table-ajax', compact(
            'pageTitle',
            'sections',
            'topGainer',
            'topLoser',
        ));
    }


    public function pages($slug)
    {
        $page = Page::where('tempname',$this->activeTemplate)->where('slug',$slug)->firstOrFail();
        $pageTitle = $page->name;
        $sections = $page->secs;
        // dd($this->activeTemplate . 'pages');
        return view($this->activeTemplate . 'pages', compact('pageTitle','sections'));
    }


    public function contact()
    {
        $pageTitle = "Contact Us";
        $user = auth()->user();
        $sections = Page::where('tempname',$this->activeTemplate)->where('slug','contact')->first();
        return view($this->activeTemplate . 'contact',compact('pageTitle', 'sections', 'user'));
    }


    public function contactSubmit(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required',
            'subject' => 'required|string|max:255',
            'message' => 'required',
        ]);

        if(!verifyCaptcha()){
            $notify[] = ['error','Invalid captcha provided'];
            return back()->withNotify($notify);
        }

        $request->session()->regenerateToken();

        $random = getNumber();

        $ticket = new SupportTicket();
        $ticket->user_id = auth()->id() ?? 0;
        $ticket->name = $request->name;
        $ticket->email = $request->email;
        $ticket->priority = Status::PRIORITY_MEDIUM;


        $ticket->ticket = $random;
        $ticket->subject = $request->subject;
        $ticket->last_reply = Carbon::now();
        $ticket->status = $ticket->status = Status::TICKET_OPEN;;
        $ticket->save();

        $adminNotification = new AdminNotification();
        $adminNotification->user_id = auth()->user() ? auth()->user()->id : 0;
        $adminNotification->title = 'A new contact message has been submitted';
        $adminNotification->click_url = urlPath('admin.ticket.view',$ticket->id);
        $adminNotification->save();

        $message = new SupportMessage();
        $message->support_ticket_id = $ticket->id;
        $message->message = $request->message;
        $message->save();

        $notify[] = ['success', 'Ticket created successfully!'];

        return to_route('ticket.view', [$ticket->ticket])->withNotify($notify);
    }

    public function policyPages($slug,$id)
    {
        $policy = Frontend::where('id',$id)->where('data_keys','policy_pages.element')->firstOrFail();
        $pageTitle = $policy->data_values->title;
        return view($this->activeTemplate.'policy',compact('policy','pageTitle'));
    }

    public function changeLanguage($lang = null)
    {
        $language = Language::where('code', $lang)->first();
        if (!$language) $lang = 'en';
        session()->put('lang', $lang);
        return back();
    }

    public function blogs(){
        $pageTitle = 'Blogs';
        $blogs = Frontend::where('data_keys', 'blog.element')->where('template_name', activeTemplateName())->orderBy('id', 'DESC')->paginate(getPaginate());
        $sections = Page::where('tempname', $this->activeTemplate)->where('slug','blogs')->first();
        return view($this->activeTemplate.'blogs',compact('pageTitle', 'blogs', 'sections'));
    }

    public function blogDetails($slug, $id){

        $pageTitle = 'Blog Details';
        $blog = Frontend::where('id', $id)->where('data_keys','blog.element')->firstOrFail();
        $latestBlogs = Frontend::where('data_keys','blog.element')->where('id', '!=', $blog->id)->where('template_name', activeTemplateName())->orderBy('id', 'DESC')->take(10)->get();

        $customPageTitle                   = $blog->data_values->title;
        $seoContents['keywords']           = $blog->meta_keywords ?? [];
        $seoContents['social_title']       = $blog->data_values->title;
        $seoContents['description']        = strLimit(strip_tags($blog->data_values->description), 150);
        $seoContents['social_description'] = strLimit(strip_tags($blog->data_values->description), 150);
        $seoContents['image']              = getImage('assets/images/frontend/blog/' . @$blog->data_values->image, '855x480');
        $seoContents['image_size']         = '855x480';

        return view($this->activeTemplate.'blog_details',compact('blog','pageTitle', 'latestBlogs', 'seoContents', 'customPageTitle'));
    }

    public function cookieAccept(){
        Cookie::queue('gdpr_cookie',gs('site_name') , 43200);
        return back();
    }

    public function cookiePolicy(){
        $pageTitle = 'Cookie Policy';
        $cookie = Frontend::where('data_keys','cookie.data')->first();
        return view($this->activeTemplate.'cookie',compact('pageTitle','cookie'));
    }

    public function placeholderImage($size = null){
        $imgWidth = explode('x',$size)[0];
        $imgHeight = explode('x',$size)[1];
        $text = $imgWidth . '×' . $imgHeight;
        $fontFile = realpath('assets/font/RobotoMono-Regular.ttf');
        $fontSize = round(($imgWidth - 50) / 8);
        if ($fontSize <= 9) {
            $fontSize = 9;
        }
        if($imgHeight < 100 && $fontSize > 30){
            $fontSize = 30;
        }

        $image     = imagecreatetruecolor($imgWidth, $imgHeight);
        $colorFill = imagecolorallocate($image, 100, 100, 100);
        $bgFill    = imagecolorallocate($image, 175, 175, 175);
        imagefill($image, 0, 0, $bgFill);
        $textBox = imagettfbbox($fontSize, 0, $fontFile, $text);
        $textWidth  = abs($textBox[4] - $textBox[0]);
        $textHeight = abs($textBox[5] - $textBox[1]);
        $textX      = ($imgWidth - $textWidth) / 2;
        $textY      = ($imgHeight + $textHeight) / 2;
        header('Content-Type: image/jpeg');
        imagettftext($image, $fontSize, 0, $textX, $textY, $colorFill, $fontFile, $text);
        imagejpeg($image);
        imagedestroy($image);
    }

    public function maintenance()
    {
        $pageTitle = 'Maintenance Mode';
        if(gs('maintenance_mode') == Status::DISABLE){
            return to_route('home');
        }
        $maintenance = Frontend::where('data_keys','maintenance.data')->first();
        return view($this->activeTemplate.'maintenance',compact('pageTitle','maintenance'));
    }

    public function subscribe(Request $request){

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:250|unique:subscribers,email'
        ]);

        if(!$validator->passes()) {
            return response()->json(['error'=>$validator->errors()->all()]);
        }

        $newSubscriber = new Subscriber();
        $newSubscriber->email = $request->email;
        $newSubscriber->save();

        return response()->json(['success'=>true, 'message'=>'Thank you, We\'ll notice you our latest news']);
    }

    public function packages(){
        $pageTitle = 'Products';
        $extends = Auth::user() ? $this->activeTemplate.'layouts.master' : $this->activeTemplate.'layouts.frontend';
        $sections = Page::where('tempname', $this->activeTemplate)->where('slug','packages')->first();
        return view($this->activeTemplate.'package',compact('pageTitle', 'extends','sections'));
    }

    public function getDeviceToken(Request $request){

        $validator = Validator::make($request->all(), [
            'token'=> 'required'
        ]);

        if($validator->fails()){
            return ['success'=>false, 'errors'=>$validator->errors()->all()];
        }

        $deviceToken = DeviceToken::where('token', $request->token)->first();

        if($deviceToken){
            return ['success'=>true, 'message'=>'Already exists'];
        }

        $deviceToken = new DeviceToken();
        $deviceToken->user_id = auth()->user()->id;
        $deviceToken->token = $request->token;
        $deviceToken->is_app = 0;
        $deviceToken->save();

        return ['success'=>true, 'message'=>'Token save successfully'];
    }

    public function getMarketData(){
        return response()->json($this->getMarketDataResp());
    }

    public function getTopLoserData(){
        try {
            $response = response()->json($this->getTopLoserAngleApiData());
        } catch (\Throwable $th) {
           return response()->json(
            [ 'data' => ['status'=>false] ]
           );
        }
        
    }

    public function getTopGainerApiData(){

        try {
            return response()->json($this->getTopGainerAngleApiData());
        } catch (\Throwable $th) {
           return response()->json(
            [ 'data' => ['status'=>false] ]
           );
        }
    }

    public function getPcrApiData(){
        try {
            return response()->json($this->getPCRApiDatas());
        } catch (\Throwable $th) {
           return response()->json(
            [ 'data' => ['status'=>false] ]
           );
        }
    }

    public function getLongBuildApiData(){
        try {
            return response()->json($this->getLongBuildData());
        } catch (\Throwable $th) {
           return response()->json(
            [ 'data' => ['status'=>false] ]
           );
        }
    }

    public function getShortBuildApiData(){
        try {
            return response()->json($this->getShortBuildData());
        } catch (\Throwable $th) {
           return response()->json(
            [ 'data' => ['status'=>false] ]
           );
        }
    }

    public function getShortCoveringApiData(){
        try {
            return response()->json($this->getShortCoveringData());
        } catch (\Throwable $th) {
           return response()->json(
            [ 'data' => ['status'=>false] ]
           );
        }
    }

    public function getLongUnwillingApiData(){
        try {
            return response()->json($this->getLongUnwillingData());
        } catch (\Throwable $th) {
           return response()->json(
            [ 'data' => ['status'=>false] ]
           );
        }
    }

    public function storeTokenData(){
        return response()->json($this->getTokenData());
    }

    public function storeApiFetchData(){
        try {
            // dd($this->storeApiFetch());
            $data = response()->json($this->storeApiFetch());
            dd('Inserted');
        } catch (\Throwable $th) {
           return response()->json(
            [ 'data' => ['status'=>false] ]
           );
        }
    }

    public function fetchGreeksApiData(){
        // try {
        //     $symbol = "";
        //     $expDate = "";
            return response()->json($this->fetchGreeksApi());
        // } catch (\Throwable $th) {
        //    return response()->json(
        //     [ 'data' => ['status'=>false] ]
        //    );
        // }
    }

    public function packageDetails($id){
        $pageTitle = 'Products Details';
        $packageDetails = Package::Where('id',$id)->first();
        return view($this->activeTemplate.'package-details',compact('pageTitle', 'packageDetails'));
    }

    public function storeUserRequest(Request $request,$id){

        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'number' => 'required|integer',
        ]);

        $userId = Auth::id();
        $userEnquiry = new UserEnquiry;
        $userEnquiry->user_id = $userId;
        $userEnquiry->package_id = $id;
        $userEnquiry->name = $request->name;
        $userEnquiry->email = $request->email;
        $userEnquiry->phone = $request->number;
        $userEnquiry->save();

        $notify[] = ['success', 'Request Submitted Successfully'];
        return back()->withNotify($notify);
    }


    // EXCEL DATA
    public function importExcelData(){
        ini_set("max_execution_time", "-1");
        ini_set("memory_limit", "-1");
        ignore_user_abort(true);
        set_time_limit(0);

        $filePath = public_path('excel-data.xlsx');
        Excel::queueImport(new ImportProductData, $filePath);
    }
   
}

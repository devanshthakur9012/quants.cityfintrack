<?php

namespace App\Http\Controllers\Admin;

use App\Exports\FOPortfolioHedgingTemplateExport;
use App\Exports\GlobalStockPortfolioTemplateExport;
use App\Exports\MetalsPortfolioTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\ThematicPortfolio;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ThematicPortfolioTemplateExport;
use App\Imports\FOPortfolioHedgingDataImport;
use App\Imports\GlobalStockPortfolioDataImport;
use App\Imports\MetalsPortfolioDataImport;
use App\Imports\ThematicPortfolioDataImport;
use App\Models\FOPortfolios;
use App\Models\GlobalStockPortfolio;
use App\Models\MetalsPortfolio;

class InvestmentOverviewController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function allThematicPortfolios(Request $request)
    {
        $pageTitle = 'All Thematic Portfolios';
        $thematicPortfolios = ThematicPortfolio::orderBy('id','ASC');

        $clientId = 'all';
        $stockName = 'all';

        if(!empty($request->client_id) && $request->client_id!='all'){
            $thematicPortfolios->where('sector',$request->client_id);
            $clientId = $request->client_id;
        }
        if(!empty($request->stock_name) && $request->stock_name!='all'){
            $thematicPortfolios->where('stock_name',$request->stock_name);
            $stockName = $request->stock_name;
        }
        
        $thematicPortfolios = $thematicPortfolios->paginate(getPaginate());

        $symbolArray = [];
        foreach ($thematicPortfolios as $val) {
           array_push($symbolArray , $val['stock_name'].".NS");
        }
        return view('admin.investments.thematic.all', compact('pageTitle', 'thematicPortfolios','clientId','stockName','symbolArray'));

    }

    public function getThematicPortfolios(Request $request){
        $term = $request->term;
        $data = [];
        if(!empty($term)){
            $data = ThematicPortfolio::select('id','stock_name')->where('stock_name','like','%'.$term.'%')->limit(10)->groupBy('stock_name')->get();
        }
        return response()->json($data);
    }

    public function getThematicPortfoliosSearchClientId(Request $request){
        $term = $request->term;
        $data = [];
        if(!empty($term)){
            $data = ThematicPortfolio::select('id','sector')->where('sector','like','%'.$term.'%')->limit(10)->limit(10)->groupBy('sector')->get();
        }
        return response()->json($data);
    }

    public function removeThematicPortfolios(Request $request){
        $data = $request->data;
        if(!empty($data)){
            ThematicPortfolio::whereIn('id',$data)->delete();
        }        
        $notify[] = ['success', 'Thematic Portfolios deleted successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function addThematicPortfolios()
    {
        $pageTitle = 'Add Thematic Portfolios';
        return view('admin.investments.thematic.add', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addSubmitThematicPortfolios(Request $request)
    {
        // dd($request);
        $request->validate([
            'stock_name' => 'required|max:250',
            'reco_date' => 'required',
            'buy_price' => 'required',
            'cmp' => 'required',
            'pnl' => 'required',
            'sector' => 'required',
        ]);

        $thematicPortfolio = new ThematicPortfolio();
        $thematicPortfolio->stock_name = $request->stock_name;
        $thematicPortfolio->reco_date = $request->reco_date;
        $thematicPortfolio->buy_price = $request->buy_price;
        $thematicPortfolio->cmp = $request->cmp;
        $thematicPortfolio->pnl = $request->pnl;
        $thematicPortfolio->sector = $request->sector;
        $thematicPortfolio->save();

        $notify[] = ['success', 'Thematic Portfolio added successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Download the template for the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function templateThematicPortfolioDownload()
    {
        $fileName = 'thematic_portfolio_excel_template.xlsx';

        return Excel::download(new ThematicPortfolioTemplateExport, $fileName);
    }

    /**
     * Upload the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function uploadThematicPortfolios(Request $request)
    {
        $request->validate([
            'xlsFile' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('xlsFile');

        try {
            Excel::import(new ThematicPortfolioDataImport, $file);

            $notify[] = ['success', 'Thematic Portfolio data imported successfully'];
            return back()->withNotify($notify);
        } catch (\Exception $ex) {
            $notify[] = ['error', $ex->getMessage()];
            return back()->withNotify($notify);
        }

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function allFoPortfolioHedging(Request $request)
    {
        $pageTitle = 'All F&O Portfolio Hedging';

        $foPortFolioHedgings = FOPortfolios::with(['user', 'poolingAccountPortfolio']);
        $clientId = 'all';
        $stockName = 'all';
        $buyDate = 'all';

        if(!empty($request->client_id) && $request->client_id!='all'){
            $foPortFolioHedgings->whereHas('user',function($q) use($request){
                $q->where('user_code',$request->client_id);
            });
            $clientId = $request->client_id;
        }
        if(!empty($request->stock_name) && $request->stock_name!='all'){
            $foPortFolioHedgings->where('stock_name',$request->stock_name);
            $stockName = $request->stock_name;
        }
        if(!empty($request->buy_date) && $request->buy_date!='all'){
            $foPortFolioHedgings->where('buy_date',$request->buy_date);
            $buyDate = $request->buy_date;
        }
        $foPortFolioHedgings = $foPortFolioHedgings->paginate(getPaginate());

        $symbolArray = [];
        foreach ($foPortFolioHedgings as $val) {
           array_push($symbolArray , $val['stock_name'].".NS");
        }

        return view('admin.investments.foprortfolio.all', compact('pageTitle', 'foPortFolioHedgings','clientId','stockName','buyDate','symbolArray'));
    }

    public function getFoPortfolioHedging(Request $request){
        $term = $request->term;
        $data = [];
        if(!empty($term)){
            $data = FOPortfolios::select('id','stock_name')->where('stock_name','like','%'.$term.'%')->limit(10)->groupBy('stock_name')->get();
        }
        return response()->json($data);
    }

    public function getFoPortSearchClientId(Request $request){
        $term = $request->term;
        $data = [];
        if(!empty($term)){
            $data = User::select('id','user_code')->where('user_code','like','%'.$term.'%')->limit(10)->get();
        }
        return response()->json($data);
    }

    public function removefoPortfolio(Request $request){
        $data = $request->data;
        if(!empty($data)){
            FOPortfolios::whereIn('id',$data)->delete();
        }        
        $notify[] = ['success', 'F&O Portfolio Hedging deleted successfully'];
        return back()->withNotify($notify);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function addFoPortfolioHedging()
    {
        $pageTitle = 'Add F&O Portfolio Hedging';
        return view('admin.investments.foprortfolio.add', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addSubmitFoPortfolioHedging(Request $request)
    {
        // dd($request);
        $request->validate([
            'stock_name' => 'required|max:250',
            'reco_date' => 'required',
            'buy_price' => 'required',
            'cmp' => 'required',
            'pnl' => 'required',
            'sector' => 'required',
        ]);

        $thematicPortfolio = new FOPortfolios();
        $thematicPortfolio->stock_name = $request->stock_name;
        $thematicPortfolio->reco_date = $request->reco_date;
        $thematicPortfolio->buy_price = $request->buy_price;
        $thematicPortfolio->cmp = $request->cmp;
        $thematicPortfolio->pnl = $request->pnl;
        $thematicPortfolio->sector = $request->sector;
        $thematicPortfolio->save();

        $notify[] = ['success', 'F&O Portfolio Hedging added successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Download the template for the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function templateFoPortfolioHedgingDownload()
    {
        $fileName = 'fo_portfolio_excel_template.xlsx';

        return Excel::download(new FOPortfolioHedgingTemplateExport, $fileName);
    }

    /**
     * Upload the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function uploadFoPortfolioHedging(Request $request)
    {
        $request->validate([
            'xlsFile' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('xlsFile');

        try {
            Excel::import(new FOPortfolioHedgingDataImport, $file);

            $notify[] = ['success', 'F&O Portfolio Hedging data imported successfully'];
            return back()->withNotify($notify);
        } catch (\Exception $ex) {
            $notify[] = ['error', $ex->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function allMetalsPortfolios(Request $request)
    {
        $pageTitle = 'All Metals Portfolios (Gold & Silver)';


        $metalsPortfolios = MetalsPortfolio::with(['user', 'poolingAccountPortfolio']);
        $clientId = 'all';
        $stockName = 'all';
        $buyDate = 'all';

        if(!empty($request->client_id) && $request->client_id!='all'){
            $metalsPortfolios->whereHas('user',function($q) use($request){
                $q->where('user_code',$request->client_id);
            });
            $clientId = $request->client_id;
        }
        if(!empty($request->stock_name) && $request->stock_name!='all'){
            $metalsPortfolios->where('stock_name',$request->stock_name);
            $stockName = $request->stock_name;
        }
        if(!empty($request->buy_date) && $request->buy_date!='all'){
            $metalsPortfolios->where('buy_date',$request->buy_date);
            $buyDate = $request->buy_date;
        }
        $metalsPortfolios = $metalsPortfolios->paginate(getPaginate());

        $symbolArray = [];
        foreach ($metalsPortfolios as $val) {
           array_push($symbolArray , $val['stock_name'].".NS");
        }

        return view('admin.investments.metals.all', compact('pageTitle', 'metalsPortfolios','clientId','stockName','buyDate','symbolArray'));
    }

    public function getMetalsPortfoliosfolio(Request $request){
        $term = $request->term;
        $data = [];
        if(!empty($term)){
            $data = MetalsPortfolio::select('id','stock_name')->where('stock_name','like','%'.$term.'%')->limit(10)->groupBy('stock_name')->get();
        }
        return response()->json($data);
    }

    public function getMetalsPortfoliosSearchClientId(Request $request){
        $term = $request->term;
        $data = [];
        if(!empty($term)){
            $data = User::select('id','user_code')->where('user_code','like','%'.$term.'%')->limit(10)->get();
        }
        return response()->json($data);
    }

    public function removeMetalsPortfolios(Request $request){
        $data = $request->data;
        if(!empty($data)){
            MetalsPortfolio::whereIn('id',$data)->delete();
        }        
        $notify[] = ['success', 'Metals Portfolios deleted successfully'];
        return back()->withNotify($notify);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function addMetalsPortfolios()
    {
        $pageTitle = 'Add Metals Portfolios';
        return view('admin.investments.metals.add', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addSubmitMetalsPortfolios(Request $request)
    {
        // dd($request);
        $request->validate([
            'stock_name' => 'required|max:250',
            'reco_date' => 'required',
            'buy_price' => 'required',
            'cmp' => 'required',
            'pnl' => 'required',
            'sector' => 'required',
        ]);

        $thematicPortfolio = new MetalsPortfolio();
        $thematicPortfolio->stock_name = $request->stock_name;
        $thematicPortfolio->reco_date = $request->reco_date;
        $thematicPortfolio->buy_price = $request->buy_price;
        $thematicPortfolio->cmp = $request->cmp;
        $thematicPortfolio->pnl = $request->pnl;
        $thematicPortfolio->sector = $request->sector;
        $thematicPortfolio->save();

        $notify[] = ['success', 'Metals Portfolio added successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Download the template for the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function templateMetalsPortfolioDownload()
    {
        $fileName = 'metals_portfolio_excel_template.xlsx';

        return Excel::download(new MetalsPortfolioTemplateExport, $fileName);
    }

    /**
     * Upload the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function uploadMetalsPortfolios(Request $request)
    {
        $request->validate([
            'xlsFile' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('xlsFile');

        try {
            Excel::import(new MetalsPortfolioDataImport, $file);

            $notify[] = ['success', 'Metals Portfolio data imported successfully'];
            return back()->withNotify($notify);
        } catch (\Exception $ex) {
            $notify[] = ['error', $ex->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function allGlobalStockPortfolios(Request $request)
    {
        $pageTitle = 'All Global Stock Portfolios';
        
        $globalStockPortfolios = GlobalStockPortfolio::with(['user', 'poolingAccountPortfolio']);
        $clientId = 'all';
        $stockName = 'all';
        $buyDate = 'all';

        if(!empty($request->client_id) && $request->client_id!='all'){
            $globalStockPortfolios->whereHas('user',function($q) use($request){
                $q->where('user_code',$request->client_id);
            });
            $clientId = $request->client_id;
        }
        if(!empty($request->stock_name) && $request->stock_name!='all'){
            $globalStockPortfolios->where('stock_name',$request->stock_name);
            $stockName = $request->stock_name;
        }
        if(!empty($request->buy_date) && $request->buy_date!='all'){
            $globalStockPortfolios->where('buy_date',$request->buy_date);
            $buyDate = $request->buy_date;
        }
        $globalStockPortfolios = $globalStockPortfolios->paginate(getPaginate());

        $symbolArray = [];
        foreach ($globalStockPortfolios as $val) {
           array_push($symbolArray , $val['stock_name'].".NS");
        }

        return view('admin.investments.global.all', compact('pageTitle', 'globalStockPortfolios','stockName','clientId','buyDate','symbolArray'));
    }

    public function getStockName(Request $request){
        $term = $request->term;
        $data = [];
        if(!empty($term)){
            $data = GlobalStockPortfolio::select('id','stock_name')->where('stock_name','like','%'.$term.'%')->limit(10)->groupBy('stock_name')->get();
        }
        return response()->json($data);
    }

    public function getSearchClientId(Request $request){
        $term = $request->term;
        $data = [];
        if(!empty($term)){
            $data = User::select('id','user_code')->where('user_code','like','%'.$term.'%')->limit(10)->get();
        }
        return response()->json($data);
    }

    public function removeStockPortfolio(Request $request){
        $data = $request->data;
        if(!empty($data)){
            GlobalStockPortfolio::whereIn('id',$data)->delete();
        }        
        $notify[] = ['success', 'Global Stock Portfolio deleted successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function addGlobalStockPortfolios()
    {
        $pageTitle = 'Add Global Stock  Portfolios';
        return view('admin.investments.global.add', compact('pageTitle'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addSubmitGlobalStockPortfolios(Request $request)
    {
        // dd($request);
        $request->validate([
            'stock_name' => 'required|max:250',
            'reco_date' => 'required',
            'buy_price' => 'required',
            'cmp' => 'required',
            'pnl' => 'required',
            'sector' => 'required',
        ]);

        $thematicPortfolio = new GlobalStockPortfolio();
        $thematicPortfolio->stock_name = $request->stock_name;
        $thematicPortfolio->reco_date = $request->reco_date;
        $thematicPortfolio->buy_price = $request->buy_price;
        $thematicPortfolio->cmp = $request->cmp;
        $thematicPortfolio->pnl = $request->pnl;
        $thematicPortfolio->sector = $request->sector;
        $thematicPortfolio->save();

        $notify[] = ['success', 'Global Stock Portfolio added successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Download the template for the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function templateGlobalStockPortfolioDownload()
    {
        $fileName = 'global_stock_portfolio_excel_template.xlsx';

        return Excel::download(new GlobalStockPortfolioTemplateExport, $fileName);
    }

    /**
     * Upload the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function uploadGlobalStockPortfolios(Request $request)
    {
        $request->validate([
            'xlsFile' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('xlsFile');

        try {
            Excel::import(new GlobalStockPortfolioDataImport, $file);

            $notify[] = ['success', 'Global Stock Portfolio data imported successfully'];
            return back()->withNotify($notify);
        } catch (\Exception $ex) {
            $notify[] = ['error', $ex->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function deleteThematicPortfolio(Request $request)
    {
        $request->validate([
            'id' => 'required|required',
        ]);

        $thematicPortfolio = ThematicPortfolio::findOrFail($request->id);
        $thematicPortfolio->delete();

        $notify[] = ['success', 'Record deleted Successfully'];
        return back()->withNotify($notify);
    }

    public function deleteFoPortfolioHedging(Request $request)
    {
        $request->validate([
            'id' => 'required|required',
        ]);

        $foPortfolioHedging = FOPortfolios::findOrFail($request->id);
        $foPortfolioHedging->delete();

        $notify[] = ['success', 'Record deleted Successfully'];
        return back()->withNotify($notify);
    }

    public function deleteMetalsPortfolio(Request $request)
    {
        $request->validate([
            'id' => 'required|required',
        ]);

        $metalsPortfolio = MetalsPortfolio::findOrFail($request->id);
        $metalsPortfolio->delete();

        $notify[] = ['success', 'Record deleted Successfully'];
        return back()->withNotify($notify);
    }

    public function deleteGlobalStockPortfolio(Request $request)
    {
        $request->validate([
            'id' => 'required|required',
        ]);

        $globalStockPortfolio = GlobalStockPortfolio::findOrFail($request->id);
        $globalStockPortfolio->delete();

        $notify[] = ['success', 'Record deleted Successfully'];
        return back()->withNotify($notify);
    }
}

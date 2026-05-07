<?php

namespace App\Http\Controllers\Admin;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\StockPortfolio;
use App\Imports\LedgerDataImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Controller;
use App\Exports\LedgerTemplateExport;
use App\Imports\PortfolioTopLoserDataImport;
use App\Exports\PortfolioTopLoserTemplateExport;
use App\Exports\StockPortfolioTemplateExport;
use App\Imports\StockPortfolioDataImport;

class FinancialOverviewController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function allLedger(Request $request)
    {
        $pageTitle = 'All Ledger';
        $ledgers = Ledger::with(['user', 'poolingAccountPortfolio']);

        $clientId = 'all';
        $stockName = 'all';
        $buyDate = 'all';

        if(!empty($request->client_id) && $request->client_id!='all'){
            $ledgers->whereHas('user',function($q) use($request){
                $q->where('user_code',$request->client_id);
            });
            $clientId = $request->client_id;
        }
        if(!empty($request->stock_name) && $request->stock_name!='all'){
            $ledgers->where('stock_name',$request->stock_name);
            $stockName = $request->stock_name;
        }
        if(!empty($request->buy_date) && $request->buy_date!='all'){
            $ledgers->where('bought_date',$request->buy_date);
            $buyDate = $request->buy_date;
        }
        
        $ledgers = $ledgers->paginate(getPaginate());
        return view('admin.financial.ledger.all', compact('pageTitle', 'ledgers','buyDate','clientId','stockName'));

    }

    public function getLedger(Request $request){
        $term = $request->term;
        $data = [];
        if(!empty($term)){
            $data = Ledger::select('id','stock_name')->where('stock_name','like','%'.$term.'%')->limit(10)->groupBy('stock_name')->get();
        }
        return response()->json($data);
    }

    public function getLedgerSearchClientId(Request $request){
        $term = $request->term;
        $data = [];
        if(!empty($term)){
            $data = User::select('id','user_code')->where('user_code','like','%'.$term.'%')->limit(10)->get();
        }
        return response()->json($data);
    }

    public function removeLedger(Request $request){
        $data = $request->data;
        if(!empty($data)){
            Ledger::whereIn('id',$data)->delete();
        }        
        $notify[] = ['success', 'Ledger deleted successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Download the template for the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function templateLedgerDownload()
    {
        $fileName = 'ledger_excel_template.xlsx';

        return Excel::download(new LedgerTemplateExport, $fileName);
    }

    /**
     * Upload the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function uploadLedger(Request $request)
    {
        $request->validate([
            'xlsFile' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('xlsFile');

        try {
            Excel::import(new LedgerDataImport, $file);

            $notify[] = ['success', 'Ledger data imported successfully'];
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
    public function allStockPortfolio(Request $request)
    {
        $pageTitle = 'All Stock Portfolio';
        $stockPortfolios = StockPortfolio::with(['user', 'poolingAccountPortfolio']);

        $clientId = 'all';
        $stockName = 'all';
        $buyDate = 'all';

        if(!empty($request->client_id) && $request->client_id!='all'){
            $stockPortfolios->whereHas('user',function($q) use($request){
                $q->where('user_code',$request->client_id);
            });
            $clientId = $request->client_id;
        }
        if(!empty($request->stock_name) && $request->stock_name!='all'){
            $stockPortfolios->where('stock_name',$request->stock_name);
            $stockName = $request->stock_name;
        }
        if(!empty($request->buy_date) && $request->buy_date!='all'){
            $stockPortfolios->where('buy_date',$request->buy_date);
            $buyDate = $request->buy_date;
        }
        $stockPortfolios =  $stockPortfolios->paginate(getPaginate());

        $symbolArray = [];
        foreach ($stockPortfolios as $val) {
           array_push($symbolArray , $val['stock_name'].".NS");
        }

        return view('admin.financial.stock_portfolio.all', compact('pageTitle', 'stockPortfolios','clientId','stockName','buyDate','symbolArray'));
    }

    public function getSearchClientId(Request $request){
        $term = $request->term;
        $data = [];
        if(!empty($term)){
            $data = User::select('id','user_code')->where('user_code','like','%'.$term.'%')->limit(10)->get();
        }
        return response()->json($data);
    }

    public function getStockName(Request $request){
        $term = $request->term;
        $data = [];
        if(!empty($term)){
            $data = StockPortfolio::select('id','stock_name')->where('stock_name','like','%'.$term.'%')->limit(10)->groupBy('stock_name')->get();
        }
        return response()->json($data);
    }



    /**
     * Download the template for the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function templateStockPortfolioDownload()
    {
        $fileName = 'stock_portfolio_excel_template.xlsx';

        return Excel::download(new StockPortfolioTemplateExport, $fileName);
    }

    /**
     * Upload the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function uploadStockPortfolio(Request $request)
    {
        $request->validate([
            'xlsFile' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('xlsFile');

        try {
            Excel::import(new StockPortfolioDataImport, $file);

            $notify[] = ['success', 'Stock Portfolio data imported successfully'];
            return back()->withNotify($notify);
        } catch (\Exception $ex) {
            $notify[] = ['error', $ex->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Delete a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function deleteLedger(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        $ledger = Ledger::findOrFail($request->id);
        $ledger->delete();

        $notify[] = ['success', 'Ledger deleted successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Delete a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteStockPortfolio(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        $stockPortfolio = StockPortfolio::findOrFail($request->id);
        $stockPortfolio->delete();

        $notify[] = ['success', 'Stock Portfolio deleted successfully'];
        return back()->withNotify($notify);
    }

    public function removeStockPortfolio(Request $request){
        $data = $request->data;
        if(!empty($data)){
            StockPortfolio::whereIn('id',$data)->delete();
        }        
        $notify[] = ['success', 'Stock Portfolio deleted successfully'];
        return back()->withNotify($notify);
    }
}

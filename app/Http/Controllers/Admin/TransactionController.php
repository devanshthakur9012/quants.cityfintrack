<?php

namespace App\Http\Controllers\Admin;

use App\Exports\TransactionTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\TransactionDataImport;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function allTransactions(Request $request)
    {
        $pageTitle = 'All Transactions';
        $transactions = Transaction::with(['user', 'poolingAccountPortfolio']);

        $stockName = 'all';
        if(!empty($request->stock_name) && $request->stock_name!='all'){
            $transactions->where('stock_name',$request->stock_name);
            $stockName = $request->stock_name;
        }
        $transactions = $transactions->paginate(getPaginate());
        return view('admin.transaction.all', compact('pageTitle', 'transactions','stockName'));

    }

    public function getTransactions(Request $request){
        $term = $request->term;
        $data = [];
        if(!empty($term)){
            $data = Transaction::select('id','stock_name')->where('stock_name','like','%'.$term.'%')->limit(10)->groupBy('stock_name')->get();
        }
        return response()->json($data);
    }

    public function removeTransactions(Request $request){
        $data = $request->data;
        if(!empty($data)){
            Transaction::whereIn('id',$data)->delete();
        }        
        $notify[] = ['success', 'Transactions deleted successfully'];
        return back()->withNotify($notify);
    }

    /**
     * Download the template for the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function templateTransactionDownload()
    {
        $fileName = 'transaction_excel_template.xlsx';

        return Excel::download(new TransactionTemplateExport, $fileName);
    }

    /**
     * Upload the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function uploadTransaction(Request $request)
    {
        $request->validate([
            'xlsFile' => 'required|mimes:xlsx,xls',
        ]);

        $file = $request->file('xlsFile');

        try {
            Excel::import(new TransactionDataImport, $file);

            $notify[] = ['success', 'Transactions data imported successfully'];
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
    public function deleteTransaction(Request $request)
    {
        $request->validate([
            'id' => 'required|required',
        ]);

        $transaction = Transaction::findOrFail($request->id);
        $transaction->delete();

        $notify[] = ['success', 'Transaction deleted Successfully'];
        return back()->withNotify($notify);
    }
}

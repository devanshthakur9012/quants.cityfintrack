<?php


namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OmsConfigMaster;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\Auth;
use App\Models\OmsConfigs;
use App\Helpers\KiteConnectCls;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\AngelApiInstrument;

class OMSMasterConfigController extends Controller
{
    public function omsConfigMaster()
    {
        $pageTitle = 'OMS Master Config';
        $brokers = BrokerApi::select('client_name', 'id')->where('user_id', Auth::id())->get();
            
        $masterConfigs = OmsConfigMaster::where('user_id', Auth::id())->with('broker:id,client_name')->paginate(50);

        return view($this->activeTemplate . 'user.oms-config.oms-master', [
            'pageTitle' => $pageTitle,
            'brokers' => $brokers,
            'masterConfigs' => $masterConfigs
        ]);
    }

    public function storeConfigMaster(Request $request)
    {
        $request->validate([
            'portfolio_type' => 'required|string|in:PF_1,PF_2,Portfolio-Futures-Direct,Portfolio-Options-Opposite,Portfolio-Futures-Opposite',
            'buildup_type' => 'required|string|in:all,Long Built Up,Short Built Up,Short Covering,Long Unwinding',
            'broker_api_id' => 'required|exists:broker_apis,id',
            'disc_ltp' => 'required|numeric|min:0|max:100',
            'order_type' => 'required|in:LIMIT,MARKET',
            'pyramid_percent' => 'nullable|numeric|in:33,50,100',
            'product' => 'required|in:NRML,MIS',
            'quantity' => 'required|integer|min:1',
            'pyramid_freq' => 'required|integer|min:0',
            'status' => 'required|in:1,0',
        ]);

        try {
            OmsConfigMaster::create([
                'portfolio_type' => $request->portfolio_type,
                'buildup_type' => $request->buildup_type,
                'broker_api_id' => $request->broker_api_id,
                'disc_ltp' => $request->disc_ltp,
                'order_type' => $request->order_type,
                'pyramid_percent' => $request->pyramid_percent,
                'product' => $request->product,
                'quantity' => $request->quantity,
                'pyramid_freq' => $request->pyramid_freq,
                'exit_1_qty' => $request->exit_1_qty ?? 0,
                'exit_1_target' => $request->exit_1_target ?? 0,
                'exit_2_qty' => $request->exit_2_qty ?? 0,
                'exit_2_target' => $request->exit_2_target ?? 0,
                'user_id' => Auth::id(),
                'status' => $request->status,
            ]);

            $notify[] = ['success', 'Master configuration created successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('OMS Master Config Store Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating master configuration: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function omsConfigMasterOrder($masterId)
    {
        $pageTitle = 'OMS Master Config Orders';
        $userId = Auth::id();

        $masterConfig = OmsConfigMaster::where('user_id',$userId)->where('id',$masterId)->first();
        if(! $masterId){
            $notify[] = ['error', 'Invalid Request'];
            return redirect()->route('user.oms-config-master')->withNotify($notify);
        }

        // Fetch orders linked to this master config with broker info
        $configOrders = OmsConfigs::where('user_id', $userId)
            ->where('master_config_id', $masterId)
            ->with('broker:id,client_name')
            ->orderByDesc('created_at')
            ->paginate(50); // pagination

        return view($this->activeTemplate . 'user.oms-config.oms-master-order', [
            'pageTitle' => $pageTitle,
            'configOrders' => $configOrders,
            'masterConfig' => $masterConfig
        ]);
    }
    
    public function omsConfigMasterOrderDestroy($id)
    {
        try {
            $masterConfig = OmsConfigMaster::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $masterConfig->status = 0;
            $masterConfig->save();

            $notify[] = ['success', 'Master configuration Inactive successfully!'];
            return redirect()->back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('OMS Master Config Inactive Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error deleting master configuration.'];
            return redirect()->back()->withNotify($notify);
        }
    }

    public function getSymbols($id)
    {
        try {
            $masterConfig = OmsConfigMaster::where('id', $id)
                ->where('user_id', Auth::id())
                ->with('broker:id,client_name')
                ->firstOrFail();

            $symbols = OmsConfigs::where('master_config_id', $id)
                ->with('broker:id,client_name')
                ->orderBy('created_at', 'desc')
                ->get();

            $html = view('user.oms-master-config.symbols-partial', [
                'masterConfig' => $masterConfig,
                'symbols' => $symbols
            ])->render();

            return response()->json(['html' => $html]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error loading symbols'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'portfolio_type' => 'required|string|in:PF_1,PF_2,Portfolio-Futures-Direct,Portfolio-Options-Opposite,Portfolio-Futures-Opposite',
            'buildup_type' => 'required|string|in:all,Long Built Up,Short Built Up,Short Covering,Long Unwinding',
            'broker_api_id' => 'required|exists:broker_apis,id',
            'disc_ltp' => 'required|numeric|min:0|max:100',
            'order_type' => 'required|in:LIMIT,MARKET',
            'pyramid_percent' => 'nullable|numeric|in:33,50,100',
            'product' => 'required|in:NRML,MIS',
            'quantity' => 'required|integer|min:1',
            'pyramid_freq' => 'required|integer|min:0',
            'status' => 'required|in:1,0',
        ]);

        try {
            $masterConfig = OmsConfigMaster::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $masterConfig->update($request->only([
                'portfolio_type', 'buildup_type', 'broker_api_id', 'disc_ltp',
                'order_type', 'pyramid_percent', 'product', 'quantity', 
                'pyramid_freq', 'exit_1_qty', 'exit_1_target', 'exit_2_qty', 
                'exit_2_target', 'status'
            ]));

            // If configuration changed significantly, mark existing orders for re-evaluation
            if ($request->status == 0) {
                // If deactivated, mark all associated orders as inactive
                OmsConfigs::where('master_config_id', $id)->update(['status' => 0]);
            }

            $notify[] = ['success', 'Master configuration updated successfully!'];
            return redirect()->back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('OMS Master Config Update Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error updating master configuration.'];
            return redirect()->back()->withNotify($notify);
        }
    }

    // Enhanced symbol sync with better error handling
    public function syncSymbolsManually($id)
    {
        try {
            $masterConfig = OmsConfigMaster::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $helper = new OmsSymbolSyncHelper();
            $result = $helper->syncSymbolsForMaster($masterConfig);

            $notify[] = ['success', "Sync completed! Added: {$result['added']} symbols, Errors: {$result['errors']}"];
            return redirect()->back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('Manual Symbol Sync Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error syncing symbols.'];
            return redirect()->back()->withNotify($notify);
        }
    }

    // public function testKiteAuth()
    // {
    //     $params = [
    //         'accountUserName' => env('KITE_LOGIN_ID'),
    //         'accountPassword' => env('KITE_PASSWORD'),
    //         'totpSecret'      => env('KITE_TOTP_SECRET'),
    //         'apiKey'          => env('KITE_API_KEY'),
    //         'apiSecret'       => env('KITE_API_SECRET'),
    //     ];

    //     $kiteConnector = new KiteConnectCls($params);
    //     $kite = $kiteConnector->generateSession();
        
    // }

    public function testKiteAuth()
    {

        $symbol = "ASTRAL28AUG251360CE";
        // $lotSizeData = ZerodhaInstrument::select('lot_size', 'tick_size')
        //     ->where('trading_symbol', $symbol)
        //     ->first();
        $data = AngelApiInstrument::select('zi.trading_symbol as kiteSymbol', 'zi.exchange_token','lotsize', 'symbol_name', 'angel_api_instruments.tick_size')
        ->join('zerodha_instruments as zi', 'zi.exchange_token', '=', 'angel_api_instruments.token') // FIXED JOIN CONDITION
        ->where('angel_api_instruments.symbol_name', $symbol)
        ->first();

        dd($data);

        if ($data) {
            $tSize = $data->tick_size / 100;
            return [
                'symbol' => $data->kiteSymbol,
                'token' => $data->exchange_token,
                'lot_size' => $data->lotsize,
                'tick_size' => $tSize
            ];
        }
        
        return [
            'symbol' => null,
            'token' => null,
            'lot_size' => null,
            'tick_size' => null,
        ];
    }




}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\SiteVariable;
use App\Traits\AngelApiAuth;
class PackageController extends Controller{
    use AngelApiAuth;
    public function all(){
        $pageTitle = 'Manage Product';
        $packages = Package::paginate(getPaginate());
        return view('admin.package.all', compact('pageTitle' , 'packages'));
    }

    public function add(){
        $this->addOrUpdate();

        $notify[] = ['success', 'Product added successfully'];
        return back()->withNotify($notify);
    }

    public function update(){
        $this->addOrUpdate();

        $notify[] = ['success', 'Product updated successfully'];
        return back()->withNotify($notify);
    }

    private function addOrUpdate(){

        $request = request();
        $package = new Package();

        $validation = [
            'name'=> 'required|max:250|unique:packages,name,'.$request->id,
            // 'price'=> 'required|numeric|gt:0',
            // 'validity'=> 'required|integer|gt:0',
            // 'features' => 'required|array|max:60000',
            'description'=> 'required',
            'asset_type'=> 'required',
            'min_investment'=> 'required|gt:0',
            'time_horizon'=> 'required',
            'risk_appetite'=> 'required',
            'expected_returns'=> 'required',
            'frequency'=> 'required',
            'hedging_strategy'=> 'required',
        ];

        if($request->id){
            $validation['id'] = 'required|integer';
        }

        $request->validate($validation);

        if($request->id){
            $package = Package::findOrFail($request->id);
        }

        $package->name = $request->name;
        $package->price = NULL;
        $package->validity = NULL;
        $package->features = NULL;
        $package->description = $request->description;
        $package->asset_type = $request->asset_type;
        $package->min_investment = $request->min_investment;
        $package->time_horizon = $request->time_horizon;
        $package->risk_appetite = $request->risk_appetite;
        $package->expected_returns = $request->expected_returns;
        $package->frequency = $request->frequency;
        $package->hedging_strategy = $request->hedging_strategy;
        $package->save();
    }

    public function status($id){
        return Package::changeStatus($id);
    }

    public function setFibonaciVariables(){
        dd($this->generate_access_token());
        $pageTitle = 'Site Variables';
        $data = SiteVariable::pluck('content','value')->toArray();
        $percentData = (object)[
            'percentage_one'=>'',
            'percentage_two'=>'',
            'percentage_three'=>'',
        ];
        $angeApiData = (object)[
            'account_user_name'=>'',
            'account_password'=>'',
            'api_key'=>'',
            'api_secret_key'=>'',
            'security_pin'=>'',
            'totp'=>'',
            'client_local_ip'=>'',
            'client_public_ip'=>'',
            'mac_address'=>'',
        ];
        $taxData = (object)[
            'fixed'=>'',
            'tax'=>'',
            'other'=>'',
            'debit'=>'',
            'credit'=>'',
        ];
        if(isset($data['fibonaci'])){
            $percentData = json_decode($data['fibonaci']);
        }
        if(isset($data['angel_api'])){
            $angeApiData = json_decode($data['angel_api']);
        }
        if(isset($data['charge_tax'])){
            $taxData = json_decode($data['charge_tax']);
        }
        return view('admin.package.set-fibonaci-variables', compact('pageTitle','percentData','angeApiData','taxData' ));
    }

    public function storeFibonaciVariables(Request $request){
        $request->validate([
            'percentage_one'=>'required',
            'percentage_two'=>'required',
            'percentage_three'=>'required',
        ]);
        $checkExist = SiteVariable::select('id')->where('value','fibonaci')->first();
        if($checkExist){
            SiteVariable::where('id',$checkExist->id)->update([
                'content'=> json_encode([
                    'percentage_one'=>$request->percentage_one,
                    'percentage_two'=>$request->percentage_two,
                    'percentage_three'=>$request->percentage_three,
                ])
            ]);
        }else{
            $obj = new SiteVariable();
            $obj->value = 'fibonaci';
            $obj->content = json_encode([
                'percentage_one'=>$request->percentage_one,
                'percentage_two'=>$request->percentage_two,
                'percentage_three'=>$request->percentage_three,
            ]);
            $obj->save();
        }
        $notify[] = ['success', 'Fibonaci variables updated successfully'];
        return redirect('admin/package/set-fibonaci-variables')->withNotify($notify);
    }

    public function storeAngelApiVariables(Request $request){
        $request->validate([
            // 'broker_name'=>'required',
            'account_user_name'=>'required',
            'account_password'=>'required',
            'api_key'=>'required',
            'api_secret_key'=>'required',
            'security_pin'=>'required',
            'totp'=>'required',
            'client_local_ip'=>'required',
            'client_public_ip'=>'required',
            'mac_address'=>'required',
        ]);
        $checkExist = SiteVariable::select('id')->where('value','angel_api')->first();
        if($checkExist){
            SiteVariable::where('id',$checkExist->id)->update([
                'content'=> json_encode([
                    // 'broker_name'=>$request->broker_name,
                    'account_user_name'=>$request->account_user_name,
                    'account_password'=>$request->account_password,
                    'api_key'=>$request->api_key,
                    'api_secret_key'=>$request->api_secret_key,
                    'security_pin'=>$request->security_pin,
                    'totp'=>$request->totp,
                    'client_local_ip'=>$request->client_local_ip,
                    'client_public_ip'=>$request->client_public_ip,
                    'mac_address'=>$request->mac_address,
                ])
            ]);
        }else{
            $obj = new SiteVariable();
            $obj->value = 'angel_api';
            $obj->content = json_encode([
                // 'broker_name'=>$request->broker_name,
                'account_user_name'=>$request->account_user_name,
                'account_password'=>$request->account_password,
                'api_key'=>$request->api_key,
                'api_secret_key'=>$request->api_secret_key,
                'security_pin'=>$request->security_pin,
                'totp'=>$request->totp,
                'client_local_ip'=>$request->client_local_ip,
                'client_public_ip'=>$request->client_public_ip,
                'mac_address'=>$request->mac_address,
            ]);
            $obj->save();
        }
        $notify[] = ['success', 'Angel Api variables updated successfully'];
        return redirect('admin/package/set-fibonaci-variables')->withNotify($notify);
    }

    public function storeChargeTaxVariables(Request $request){
        $request->validate([
            'fixed'=>'required',
            'tax'=>'required',
            'other'=>'required',
            'debit'=>'required',
            'credit'=>'required',
        ]);
        $checkExist = SiteVariable::select('id')->where('value','charge_tax')->first();
        if($checkExist){
            SiteVariable::where('id',$checkExist->id)->update([
                'content'=> json_encode([
                    'fixed'=>$request->fixed,
                    'tax'=>$request->tax,
                    'other'=>$request->other,
                    'debit'=>$request->debit,
                    'credit'=>$request->credit,
                ])
            ]);
        }else{
            $obj = new SiteVariable();
            $obj->value = 'charge_tax';
            $obj->content = json_encode([
                'fixed'=>$request->fixed,
                'tax'=>$request->tax,
                'other'=>$request->other,
                'debit'=>$request->debit,
                'credit'=>$request->credit,
            ]);
            $obj->save();
        }
        $notify[] = ['success', 'Charge Tax variables updated successfully'];
        return redirect('admin/package/set-fibonaci-variables')->withNotify($notify);
    }

}

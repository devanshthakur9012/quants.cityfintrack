@extends('admin.layouts.app')

@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card b-radius--10 ">
            <div class="card-header">
                <h4>Fibonaci Variables</h4>
            </div>
            <div class="card-body p-5">
               
                <form action="{{url('admin/package/store-fibonaci-variables')}}" method="post" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="percentage_one">First (In percent)</label>
                                <input type="text" name="percentage_one" id="percentage_one" class="form-control" value="{{$percentData->percentage_one}}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="percentage_two">Second (In percent)</label>
                                <input type="text" name="percentage_two" id="percentage_two" class="form-control" value="{{$percentData->percentage_two}}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="percentage_three">Third (In percent)</label>
                                <input type="text" name="percentage_three" id="percentage_three" class="form-control" value="{{$percentData->percentage_three}}" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
           
        </div>
    </div>

    <div class="col-lg-12 mt-3">
        <div class="card b-radius--10 ">
            <div class="card-header">         
                       <h4>Angel Api</h4>
            </div>
            <div class="card-body ">
                <form action="{{url('admin/package/store-angel-api-variables')}}" method="post" method="post">
                    @csrf
                    <div class="row">
                       
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="account_user_name">Account User Name</label>
                                <input type="text" name="account_user_name" id="account_user_name" class="form-control" value="{{$angeApiData->account_user_name}}" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="account_password">Account Password</label>
                                <input type="text" name="account_password" id="account_password" value="{{$angeApiData->account_password}}" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="api_key">API Key</label>
                                <input type="text" name="api_key" id="api_key" class="form-control" required value="{{$angeApiData->api_key}}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="api_secret_key">API Secret Key</label>
                                <input type="text" name="api_secret_key" id="api_secret_key" class="form-control" required value="{{$angeApiData->api_secret_key}}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="security_pin">Security Pin</label>
                                <input type="text" name="security_pin" id="security_pin" class="form-control" required value="{{$angeApiData->security_pin}}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="totp">TOTP</label>
                                <input type="text" name="totp" id="totp" class="form-control" required value="{{$angeApiData->totp}}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="client_local_ip">Client Local IP</label>
                                <input type="text" name="client_local_ip" id="client_local_ip" class="form-control" required value="{{$angeApiData->client_local_ip}}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="client_public_ip">Client Public IP</label>
                                <input type="text" name="client_public_ip" id="client_public_ip" class="form-control" required value="{{$angeApiData->client_public_ip}}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="mac_address">MAC Address</label>
                                <input type="text" name="mac_address" id="mac_address" class="form-control" required value="{{$angeApiData->mac_address}}">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
           
        </div>
    </div>


    <div class="col-lg-12 mt-3">
        <div class="card b-radius--10 ">
            <div class="card-header">         
                       <h4>Charge and Tax Calculations</h4>
            </div>
            <div class="card-body ">
                <form action="{{url('admin/package/store-charge-tax-variables')}}" method="post" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="fixed">Fixed</label>
                                <input type="text" name="fixed" id="fixed" class="form-control" required value="{{$taxData->fixed}}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="tax">Tax(%)</label>
                                <input type="text" name="tax" id="tax" class="form-control" required value="{{$taxData->tax}}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="other">Other(%)</label>
                                <input type="text" name="other" id="other" class="form-control" required value="{{$taxData->other}}">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="debit">Debit(%)</label>
                                <input type="text" name="debit" id="debit" class="form-control" required value="{{$taxData->debit}}">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="credit">Credit(%)</label>
                                <input type="text" name="credit" id="credit" class="form-control" required value="{{$taxData->credit}}">
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
           
        </div>
    </div>

</div>

@endsection


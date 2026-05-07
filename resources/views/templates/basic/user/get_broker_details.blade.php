<form action="{{route('user.portfolio.update-broker-details',$broker_data->id)}}" class="transparent-form" method="post">
    @csrf
    <div class="modal-header">
    <h5 class="modal-title" id="editModalLabel">Edit Client Details</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
        <div class="row">

            <div class="col-lg-6 form-group">
                <label for="client_type" class="required">Client<sup class="text--danger">*</sup></label>
                <div class="custom-icon-field">
                    <i class="las la-user"></i>
                    <select name="client_type" class="form--control" required="" id="client_type">
                        <option value="">Select Client</option>
                        @foreach (clientList() as $item)
                            <option value="{{$item}}" {{$item==$broker_data->client_type ? 'selected':''}}>{{$item}}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-lg-6 form-group">
                <label for="client_name" class="required">Client Name <sup class="text--danger">*</sup></label>
                <div class="custom-icon-field">
                    <i class="las la-user"></i>
                    <input type="text" name="client_name" value="{{$broker_data->client_name}}" class="form--control" placeholder="Enter Client Name" required="" id="client_name">
                </div>
            </div>

            <div class="col-lg-6 form-group">
                <label for="broker_name" class="required">Broker Name <sup class="text--danger">*</sup></label>
                <div class="custom-icon-field">
                    <i class="las la-user"></i>
                    <input type="text" name="broker_name" value="{{$broker_data->broker_name}}" class="form--control" placeholder="Enter Broker Name" required="" id="broker_name">
                </div>
            </div>
            
            <div class="col-lg-6 form-group">
                <label for="account_user_name" class="required">Account User Name <sup class="text--danger">*</sup></label>
                <div class="custom-icon-field">
                    <i class="las la-user"></i>
                    <input type="text" name="account_user_name" value="{{$broker_data->account_user_name}}" class="form--control" placeholder="Enter Account User Name" required="" id="account_user_name">
                </div>
            </div>

            <div class="col-lg-6 form-group">
                <label for="account_password" class="required">Account Password <sup class="text--danger">*</sup></label>
                <div class="custom-icon-field">
                    <i class="las la-key"></i>
                    <input type="password" name="account_password" autocomplete="new-password" value="{{$broker_data->account_password}}" class="form--control" placeholder="Enter Account Password" required="" id="account_password">
                </div>
            </div>

            <div class="col-lg-6 form-group">
                <label for="api_key" class="required">API Key <sup class="text--danger">*</sup></label>
                <div class="custom-icon-field">
                    <i class="las la-broadcast-tower"></i>
                    <input type="text" name="api_key" value="{{$broker_data->api_key}}" class="form--control" placeholder="Enter API Key" required="" id="api_key">
                </div>
            </div>

            <div class="col-lg-6 form-group">
                <label for="api_secret_key" class="required">API Secret Key <sup class="text--danger">*</sup></label>
                <div class="custom-icon-field">
                    <i class="las la-broadcast-tower"></i>
                    <input type="text" name="api_secret_key" value="{{$broker_data->api_secret_key}}" class="form--control" placeholder="Enter API Secret Key" required="" id="api_secret_key">
                </div>
            </div>

            <div class="col-lg-6 form-group">
                <label for="security_pin" class="required">Security Pin <sup class="text--danger">*</sup></label>
                <div class="custom-icon-field">
                    <i class="las la-mobile"></i>
                    <input type="text" name="security_pin" value="{{$broker_data->security_pin}}" class="form--control" placeholder="Enter Security Pin" id="security_pin">
                </div>
            </div>

            <div class="col-lg-6 form-group">
                <label for="totp" class="required">TOTP <sup class="text--danger">*</sup></label>
                <div class="custom-icon-field">
                    <i class="las la-mobile"></i>
                    <input type="text" name="totp" value="{{$broker_data->totp}}" class="form--control" placeholder="TOTP" required="" id="totp">
                </div>
            </div>
            
            <div class="col-lg-6 form-group">
                <label for="request_token" class="required">Request Token <sup class="text--danger">*</sup></label>
                <div class="custom-icon-field">
                    <i class="las la-mobile"></i>
                    <input type="text" name="request_token" value="{{$broker_data->request_token}}" class="form--control" placeholder="TOTP" required="" id="request_token">
                </div>
            </div>

        </div>
    </div>
    <div class="modal-footer">
    <button type="submit" class="btn btn-md btn--base">Update</button>
    </div>
</form>
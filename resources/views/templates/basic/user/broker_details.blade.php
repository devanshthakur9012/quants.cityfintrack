@extends($activeTemplate.'layouts.master')
@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
       
        <div class="row mb-5">
            <div class="col-lg-12 mb-3">
                <button type="button" class="btn btn-md btn--base" data-bs-toggle="modal" data-bs-target="#clientModal"><i class="la la-plus-circle"></i> Add Client</button>
            </div>
            <div class="col-lg-12">
                <div class="custom--card card">
                    
                  
                    <div class="card-body p-0">
                        <div class="table-responsive--md table-responsive">
                            <table class="table custom--table text-nowrap">
                                <thead>
                                    <tr>
                                        <th class="text-nowrap">Client Name</th>
                                        <th>Broker Name</th>
                                        <th>Account User Name</th>
                                        <th>Account Password</th>
                                        <th>API Key</th>
                                        <th>API Secret Key</th>
                                        <th>Security PIN</th>
                                        <th>TOTP</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                               <tbody>

                                @forelse ($broker_data as $item)
                                    <tr>
                                        <td>{{$item->client_name}}</td>
                                        <td>{{$item->broker_name}}</td>
                                        <td>{{$item->account_user_name}}</td>
                                        <td>{{$item->account_password}}</td>
                                        <td>{{$item->api_key}}</td>
                                        <td>{{$item->api_secret_key}}</td>
                                        <td>{{$item->security_pin}}</td>
                                        <td>{{$item->totp}}</td>
                                        <td>
                                            <div>
                                                <a href="javascript:void(0)" class="btn btn-sm btn-secondary me-2 edit_details" data-id="{{$item->id}}"><i class="las la-pencil-alt"></i></a>
                                                <a href="#" class="btn btn-sm btn-danger remove_details" data-id="{{$item->id}}"><i class="las la-trash-alt"></i></a>
                                                
                                            </div>
                                        </td>
                                    </tr>
                                @empty

                                    <tr>
                                        <td colspan=9>
                                            <h5 class="text-center text-white">NO DATA</h5>
                                        </td>
                                    </tr>
                                    
                                @endforelse

                               </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form action="{{route('user.portfolio.store-broker-details')}}" class="transparent-form" method="post">
            @csrf
            <div class="modal-header">
            <h5 class="modal-title" id="clientModalLabel">Add Client</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-6 form-group">
                        <label for="client_type" class="required">Broker<sup class="text--danger">*</sup></label>
                        <div class="custom-icon-field">
                            <i class="las la-user"></i>
                            <select name="client_type" class="form--control" required="" id="client_type">
                                <option value="">Select Client</option>
                                @foreach (clientList() as $item)
                                    <option value="{{$item}}">{{$item}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="col-lg-6 form-group">
                        <label for="client_name" class="required">Client Name <sup class="text--danger">*</sup></label>
                        <div class="custom-icon-field">
                            <i class="las la-user"></i>
                            <input type="text" name="client_name" value="" class="form--control" placeholder="Enter Client Name" required="" id="client_name">
                        </div>
                    </div>

                    <div class="col-lg-6 form-group">
                        <label for="broker_name" class="required">Broker Name <sup class="text--danger">*</sup></label>
                        <div class="custom-icon-field">
                            <i class="las la-user"></i>
                            <input type="text" name="broker_name" value="" class="form--control" placeholder="Enter Broker Name" required="" id="broker_name">
                        </div>
                    </div>
                    
                    <div class="col-lg-6 form-group">
                        <label for="account_user_name" class="required">Account User Name <sup class="text--danger">*</sup></label>
                        <div class="custom-icon-field">
                            <i class="las la-user"></i>
                            <input type="text" name="account_user_name" value="" class="form--control" placeholder="Enter Account User Name" required="" id="account_user_name">
                        </div>
                    </div>

                    <div class="col-lg-6 form-group">
                        <label for="account_password" class="required">Account Password <sup class="text--danger">*</sup></label>
                        <div class="custom-icon-field">
                            <i class="las la-key"></i>
                            <input type="password" name="account_password" autocomplete="new-password" value="" class="form--control" placeholder="Enter Account Password" required="" id="account_password">
                        </div>
                    </div>

                    <div class="col-lg-6 form-group">
                        <label for="api_key" class="required">API Key <sup class="text--danger">*</sup></label>
                        <div class="custom-icon-field">
                            <i class="las la-broadcast-tower"></i>
                            <input type="text" name="api_key" value="" class="form--control" placeholder="Enter API Key" required="" id="api_key">
                        </div>
                    </div>

                    <div class="col-lg-6 form-group">
                        <label for="api_secret_key" class="required">API Secret Key <sup class="text--danger">*</sup></label>
                        <div class="custom-icon-field">
                            <i class="las la-broadcast-tower"></i>
                            <input type="text" name="api_secret_key" value="" class="form--control" placeholder="Enter API Secret Key" required="" id="api_secret_key">
                        </div>
                    </div>

                    <div class="col-lg-6 form-group">
                        <label for="security_pin" class="required">Security Pin <sup class="text--danger">*</sup></label>
                        <div class="custom-icon-field">
                            <i class="las la-mobile"></i>
                            <input type="text" name="security_pin" value="" class="form--control" placeholder="Enter Security Pin" id="security_pin">
                        </div>
                    </div>

                    <div class="col-lg-6 form-group">
                        <label for="totp" class="required">TOTP <sup class="text--danger">*</sup></label>
                        <div class="custom-icon-field">
                            <i class="las la-mobile"></i>
                            <input type="text" name="totp" value="" class="form--control" placeholder="TOTP" required="" id="totp">
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-md btn--base">Save</button>
            </div>
        </form>
      </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content" id="editModalContent">
       
      </div>
    </div>
</div>

@endsection

@push('script')
    <script>
        $(".edit_details").on('click',function(){
            var id = $(this).data('id');
            var url = '{{ route("user.portfolio.get-broker-details", ":id") }}';
            url = url.replace(':id', id);
            $("#editModal").modal('show');
            $("#editModalContent").html('<h3 class="text-center mt-4">Loading Data...</h3>')
            $.get(url,function(data){
                $("#editModalContent").html(data);
            });
        });
    </script>

    <script>
        $(".remove_details").on('click',function(){
            var id = $(this).data('id');
            var url = '{{ route("user.portfolio.remove-broker-details", ":id") }}';
            url = url.replace(':id', id);
            $("#editModal").modal('show');
            $("#editModalContent").html(`
            <form action="${url}" class="transparent-form" method="post">
                @csrf
                <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Delete Client Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <p>Are you sure you want to delete this broker details ?</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                <button type="submi" class="btn btn-primary">Yes,Delete</button>
                </div>
            </form>
            `)
        });
    </script>


@endpush



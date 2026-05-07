@extends($activeTemplate.'layouts.master')
@section('content')
@push('style')
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<link rel="stylesheet" href="{{asset('assets/admin/css/vendor/select2.min.css')}}">
<style>
    .select2-container {
    width: 100% !important;
}
.select2-container .selection{
    width: 100% !important;
}
.select2-container--default .select2-selection--single {
    border-radius: 5px;
    border: 0.0625rem solid #31434B;
    height: 3rem;
    background: #ffffff00;
}
.select2-container--default .select2-selection--single:hover,
.select2-container--default .select2-selection--single:focus,
.select2-container--default .select2-selection--single.active {
    box-shadow: none;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 3rem;
    color: #969BA0;
    padding-left: 0.9375rem;
    min-height: 3rem;
}
.select2-container--default .select2-selection--multiple {
    border-color: #F5F5F5;
    border-radius: 0;
}
.select2-dropdown {
    border-radius: 0;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color:#3bb143;
    color: #fff;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    top: 0.575rem;
    right: 0.9375rem;
}
.select2-container .select2-selection--multiple {
    min-height: 2.5rem;
    color: #969BA0;
    border-radius: 1.75rem;
    border: 0.0625rem solid #C8C8C8;
}
.select2-results__option {
    padding: 6px;
    user-select: none;
    color: #31434B;
    -webkit-user-select: none;
}
</style>
@endpush
<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="text-end">
            <button class="btn btn--base" type="button" data-bs-toggle="modal" data-bs-target="#clientModal"><i class="las la-plus"></i> @lang('Add New Row')</button>
        </div>
        <div class="row mt-3">
            <div class="col-lg-12">
                <div class="custom--card">
                    <div class="card-body p-0">
                        <div class="table-responsive--md table-responsive transparent-form">
                            <table class="table custom--table text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Symbol Name</th>
                                        <th>TXN Type</th>
                                        <th>Buildup Type</th>
                                        <th>Order Type</th>
                                        <th>Product</th>
                                        <th>Disc LTP %</th>
                                        <th>Quantity</th>
                                        <th>Pyramid1</th>
                                        <th>Pyramid2</th>
                                        <th>Pyramid3</th>
                                        <th>Pyramid %</th>
                                        <th>Pyramid Freq</th>
                                        <!-- <th>Exit1 Qty</th>
                                        <th>Exit1 Target</th>
                                        <th>Exit2 Qty</th>
                                        <th>Exit2 Target</th> -->
                                        <th>Client Name</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($omsData as $item)
                                        <tr>
                                            <td>{{ date("d M Y H:i", strtotime($item->created_at)) }}</td>
                                            <td>{{ $item->symbol_name }}</td>
                                            <td>{{ $item->txn_type }}</td>
                                            <td>{{ $item->buildup_type }}</td>
                                            <td>{{ $item->order_type }}</td>
                                            <td>{{ $item->product }}</td>
                                            <td>{{ $item->order_type == "LIMIT" ? $item->disc_ltp : '-' }}</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>{{ $item->pyramid_1 }}</td>
                                            <td>{{ $item->pyramid_2 }}</td>
                                            <td>{{ $item->pyramid_3 }}</td>
                                            <td>{{ $item->pyramid_percent }}</td>
                                            <td>{{ $item->pyramid_freq }}</td>
                                            <!-- <td>{{ $item->exit_1_qty }}</td>
                                            <td>{{ $item->exit_1_target }}</td>
                                            <td>{{ $item->exit_2_qty }}</td>
                                            <td>{{ $item->exit_2_target }}</td> -->
                                            <td>{{ $item->broker->client_name }}</td>
                                            <td>
                                                <div>
                                                    <a href="javascript:void(0)" class="btn btn-sm btn-secondary me-2 edit_details" data-id="{{ $item->id }}">
                                                        <i class="las la-pencil-alt"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-sm btn-danger remove_details" data-id="{{ $item->id }}">
                                                        <i class="las la-trash-alt"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="18" class="text-center">NO DATA</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-4 justify-content-center d-flex">
           {{-- pagination links --}}
        </div>
    </div>
</section>

<div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form action="{{route('user.portfolio.new-store-oms-config')}}" class="transparent-form" method="post">
            @csrf
            <div class="modal-header">
            <h5 class="modal-title" id="clientModalLabel">Add New</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">

                    <div class="col-lg-6 form-group">
                        <label for="portfolio_type" class="required">Select Portfolios Type<sup class="text--danger">*</sup></label>
                        <select name="portfolio_type" class="form--control" required="" id="portfolio_type">
                            <option value="">Select Portfolios Type</option>
                            <option value="PF_1">Directional</option> 
                            <option value="PF_2">Bi-Directional</option> 
                            <option value="Portfolio-Futures-Direct">Futures Direct</option> 
                            <option value="Portfolio-Options-Opposite">Options Opposite</option>
                            <option value="Portfolio-Futures-Opposite">Futures Opposite</option>
                        </select>
                    </div>

                    <div class="col-lg-6 form-group">
                        <label for="buildup_type" class="required">Select Buildup Type<sup class="text--danger">*</sup></label>
                        <select name="buildup_type" class="form--control" required="" id="buildup_type">
                            <option value="">Select Buildup Type</option>
                                <option value="all">All</option>  
                                <option value="Long Built Up">Long Built Up</option> 
                                <option value="Short Built Up">Short Built Up</option> 
                                <option value="Short Covering">Short Covering</option> 
                                <option value="Long Unwinding">Long Unwinding</option>
                        </select>
                    </div>

                    <!-- <div class="col-lg-6 form-group ce_pe_symbl_1" style="display: none;">
                        <label for="ce_symbol_name" class="required">CE Symbol Name</label>
                        <select name="ce_symbol_name" class="form--control" id="ce_symbol_name">
                           
                        </select>
                    </div>

                    <div class="col-lg-6 form-group ce_pe_symbl_2" style="display: none;">
                        <label for="pe_symbol_name" class="required">PE Symbol Name</label>
                        <select name="pe_symbol_name" class="form--control" id="pe_symbol_name">
                            
                        </select>
                    </div> -->

                    <div class="col-lg-6 form-group">
                        <label for="client_name" class="required">Client Name <sup class="text--danger">*</sup></label>
                        <select name="client_name" class="form--control" required="" id="client_name">
                            <option value="">Select Client</option>
                            @foreach ($brokers as $item)
                                <option value="{{$item->id}}">{{$item->client_name}}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-6 form-group">
                        <label for="dis_ltp" class="required">Disc LTP % <sup class="text--danger">*</sup></label>
                        <input type="number" name="dis_ltp" max="100" min="0" placeholder="Enter Disc LTP %" class="form--control" required="" id="dis_ltp">
                    </div>
                    
                    <div class="col-lg-6 form-group">
                        <label for="order_type" class="required">Order Type<sup class="text--danger">*</sup></label>
                        <select name="order_type" class="form--control" required="" id="order_type">
                            <option value="">Select Order Type</option>
                            <option value="LIMIT" selected>LIMIT</option>
                            <option value="MARKET">MARKET</option>  
                        </select>
                    </div>

                    <div class="col-lg-6 form-group" id="pyramid_percent_dv">
                        <label for="pyramid_percent" class="required">Pyramid(%)<sup class="text--danger">*</sup></label>
                        <select name="pyramid_percent" class="form--control" id="pyramid_percent">
                            <option value="">Select Pyramid</option>
                            <option value="33">33</option>
                            <option value="50" selected>50</option>  
                            <option value="100">100</option>  
                        </select>
                    </div>

                    <div class="col-lg-6 form-group">
                        <label for="product" class="required">Product<sup class="text--danger">*</sup></label>
                        <select name="product" class="form--control" id="product" required>
                            <option value="">Select Product</option>
                            <option value="NRML" selected>NRML</option>
                            <option value="MIS">MIS</option>  
                        </select>
                    </div>

                    <div class="col-lg-6 form-group">
                        <label for="quantity" class="required">Qty<sup class="text--danger">*</sup></label>
                        <input type="text" name="quantity" placeholder="Enter Qty" id="quantity" class="form--control">
                    </div> 

                    <div class="col-lg-6 form-group">
                        <label for="pyramid_freq" class="required">Pyramid Freq. (In Minutes)<sup class="text--danger">*</sup></label>
                        <input type="number" name="pyramid_freq" placeholder="Enter Pyramid Freq." id="pyramid_freq" class="form--control">
                    </div>
                    
                    <div class="col-lg-6 form-group">
                        <label for="status" class="required">Status<sup class="text--danger">*</sup></label>
                        <select name="status" id="status" class="form--control">
                            <option value="1">Active</option>
                            <option value="0">InActive</option>
                        </select>
                    </div>  
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-md btn--base">Deploy</button>
            </div>
        </form>
      </div>
    </div>
</div>

<div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editclientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content" id="editClientModalBody">
        <h4 class="text-center my-5">Loading data...</h4>
      </div>
    </div>
</div>

<div class="modal fade" id="removeClientModal" tabindex="-1" aria-labelledby="removeclientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
      <div class="modal-content" id="removeClientModalBody">
       
      </div>
    </div>
</div>

@push('script')
<script src="{{asset('assets/admin/js/vendor/select2.min.js')}}"></script>
<script>
    $("#order_type").on('click',function(){
        var vl = $(this).val();
        $("#pyramid_percent_dv").show();
        $("#pyramid_percent").attr('required','required');
    });
</script>

<script>
    $(".edit_details").on('click',function(){
        var id = $(this).data('id');
        $("#editClientModal").modal('show');
        $("#editClientModalBody").html(`<h4 class="text-center my-5">Loading data...</h4>`);
        $.post('{{url("user/get-omg-config-data")}}',{'id':id,'_token':'{{csrf_token()}}'},function(data){
            $("#editClientModalBody").html(data);
        });
    })
</script>

<script>
    $(".remove_details").on('click',function(){
        var id = $(this).data('id');
        $("#removeClientModal").modal('show');
        $("#removeClientModalBody").html(`
        <form action="{{route('user.portfolio.remove-oms-config')}}" class="transparent-form" method="post">
            @csrf
            <div class="modal-header">
            <h5 class="modal-title" id="clientModalLabel">Remove</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <h5 class="text-center">Are you sure you want to remove this data</h5>   
                        <input type="hidden" value="${id}" name="id"> 
                    </div>             
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-md btn--base">Remove</button>
            </div>
        </form>
        `);
    })
</script>
@endpush
@endsection
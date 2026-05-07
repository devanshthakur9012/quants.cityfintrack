<form action="{{route('user.portfolio.update-oms-config')}}" class="transparent-form" method="post">
    @csrf
    <div class="modal-header">
    <h5 class="modal-title" id="editclientModalLabel">Edit</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
        <div class="row">
            <div class="col-lg-6 form-group">
                <label for="symbol_name_up" class="required">Symbol Name<sup class="text--danger">*</sup></label>
                <select name="symbol_name_up" class="form--control" required="" id="symbol_name_up">
                    <option value="">Select Symbol</option>
                    @foreach (allTradeSymbols() as $item)
                        <option value="{{$item}}" {{$omgData->symbol_name==$item ? 'selected' : ''}}>{{$item}}</option>
                    @endforeach
                </select>
                <input type="hidden" name="id" value="{{$omgData->id}}">
            </div>

            <div class="col-lg-6 form-group">
                <label for="signal_tf_up" class="required">Signal TF<sup class="text--danger">*</sup></label>
                <select name="signal_tf_up" class="form--control" required="" id="signal_tf_up">
                    <option value="">Select Signal TF</option>
                    @foreach (allTradeTimeFrames() as $item)
                        <option value="{{$item}}" {{$omgData->signal_tf==$item ? 'selected' : ''}}>{{$item}}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-lg-6 form-group">
                <label for="strategy_name_up" class="required">Strategy Name<sup class="text--danger">*</sup></label>
                <select name="strategy_name_up" class="form--control" required="" id="strategy_name_up">
                    <option value="">Select Strategy</option>
                    @foreach (strategyNames() as $item=>$val)
                        @php
                            $selVl = $item;
                            if($omgData->strategy_name=="Bullish"){
                                $selVl = $omgData->pe_symbol_name!=null ? 'Bullish PE': 'Bullish CE';
                            }
                            if($omgData->strategy_name=="Bearish"){
                                $selVl = $omgData->pe_symbol_name!=null ? 'Bearish PE': 'Bearish CE';
                            }
                        @endphp
                        <option value="{{$item}}" {{$item==$selVl ? 'selected':''}}>{{$val}}</option>    
                    @endforeach
                </select>
            </div>


            <div class="col-lg-6 form-group ce_pe_symbl_1" style="display: {{$omgData->ce_symbol_name!=null ? 'block':'none'}};">
                <label for="ce_symbol_name_up" class="required">CE Symbol Name</label>
                <select name="ce_symbol_name_up" class="form--control select2" id="ce_symbol_name_up">
                    @if($omgData->ce_symbol_name!=null)
                        <option value="{{$omgData->ce_symbol_name}}">{{$omgData->ce_symbol_name}}</option>
                        
                    @endif
                    @foreach ($fData as $item)
                            <option value="{{$item['ce']}}">{{$item['ce']}}</option>
                        @endforeach
                </select>
            </div>

            <div class="col-lg-6 form-group ce_pe_symbl_2" style="display: {{$omgData->pe_symbol_name!=null ? 'block':'none'}};">
                <label for="pe_symbol_name_up" class="required">PE Symbol Name</label>
                <select name="pe_symbol_name_up" class="form--control select2" id="pe_symbol_name_up">
                    @if($omgData->pe_symbol_name!=null)
                        <option value="{{$omgData->pe_symbol_name}}">{{$omgData->pe_symbol_name}}</option>
                    @endif
                    @foreach ($fData as $item)
                        <option value="{{$item['pe']}}">{{$item['pe']}}</option>
                    @endforeach
                    
                </select>
            </div>



            <div class="col-lg-6 form-group">
                <label for="client_name_up" class="required">Client Name <sup class="text--danger">*</sup></label>
                <select name="client_name_up" class="form--control" required="" id="client_name_up">
                    <option value="">Select Client</option>
                    @foreach ($brokers as $item)
                        <option value="{{$item->id}}" {{$item->id==$omgData->broker_api_id ? 'selected':''}}>{{$item->client_name}}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-lg-6 form-group">
                <label for="entry_point_up" class="required">Entry Point <sup class="text--danger">*</sup></label>
                <select name="entry_point_up" class="form--control" required="" id="entry_point_up">
                    <option value="Fibonacci">Fibonacci</option>
                </select>
            </div>

            
            
            <div class="col-lg-6 form-group">
                <label for="order_type_up" class="required">Order Type<sup class="text--danger">*</sup></label>
                <select name="order_type_up" class="form--control" required="" id="order_type_up">
                    <option value="">Select Order Type</option>
                    <option value="LIMIT" {{$omgData->order_type=="LIMIT" ? 'selected':''}}>LIMIT</option>
                    <option value="MARKET" {{$omgData->order_type=="MARKET" ? 'selected':''}}>MARKET</option>  
                </select>
            </div>

            <div class="col-lg-6 form-group" id="pyramid_percent_dv_up">
                <label for="pyramid_percent_up" class="required">Pyramid(%)<sup class="text--danger">*</sup></label>
                <select name="pyramid_percent_up" class="form--control" id="pyramid_percent_up">
                    <option value="">Select Pyramid</option>
                    <option value="33" {{$omgData->pyramid_percent=="33" ? 'selected':''}}>33</option>
                    <option value="50" {{$omgData->pyramid_percent=="50" ? 'selected':''}}>50</option>  
                    <option value="100" {{$omgData->pyramid_percent=="100" ? 'selected':''}}>100</option>  
                </select>
            </div>

            <div class="col-lg-6 form-group">
                <label for="product_up" class="required">Product<sup class="text--danger">*</sup></label>
                <select name="product_up" class="form--control" id="product_up" required>
                    <option value="">Select Product</option>
                    <option value="NRML" {{$omgData->product=="NRML" ? 'selected':''}}>NRML</option>
                    <option value="MIS" {{$omgData->product=="MIS" ? 'selected':''}}>MIS</option>  
                </select>
            </div>

            <div class="col-lg-6 form-group">
                <label for="ce_quantity_up" class="required">CE Qty<sup class="text--danger">*</sup></label>
                <input type="text" name="ce_quantity_up" id="ce_quantity_up" class="form--control" value="{{$omgData->ce_quantity}}">
            </div>

            <div class="col-lg-6 form-group">
                <label for="pe_quantity_up" class="required">PE Qty<sup class="text--danger">*</sup></label>
                <input type="text" name="pe_quantity_up" id="pe_quantity_up" class="form--control" value="{{$omgData->pe_quantity}}">
            </div>

            <div class="col-lg-6 form-group">
                <label for="pyramid_freq_up" class="required">Pyramid Freq. (In Minutes)<sup class="text--danger">*</sup></label>
                <input type="number" name="pyramid_freq_up" id="pyramid_freq_up" class="form--control" value="{{$omgData->pyramid_freq}}">
            </div>

            <div class="col-lg-6 form-group">
                <label for="status_up" class="required">Status<sup class="text--danger">*</sup></label>
                <select name="status" id="status_up" class="form--control">
                    <option value="1" {{$omgData->status==1 ? 'selected':''}}>Active</option>
                    <option value="0" {{$omgData->status==0 ? 'selected':''}}>InActive</option>
                </select>
            </div>  

            <div class="col-lg-6 form-group" style="display: none;">
                <label for="exit_1_qty_up" class="required">Exit 1 Qty<sup class="text--danger">*</sup></label>
                <input type="text" name="exit_1_qty_up" id="exit_1_qty_up" class="form--control">
            </div>

            <div class="col-lg-6 form-group"  style="display: none;">
                <label for="exit_1_target_up" class="required">Exit 1 Target<sup class="text--danger">*</sup></label>
                <input type="text" name="exit_1_target_up" id="exit_1_target_up" class="form--control">
            </div>

            <div class="col-lg-6 form-group"  style="display: none;">
                <label for="exit_2_qty_up" class="required">Exit 2 Qty<sup class="text--danger">*</sup></label>
                <input type="text" name="exit_2_qty_up" id="exit_2_qty_up" class="form--control">
            </div>

            <div class="col-lg-6 form-group"  style="display: none;">
                <label for="exit_2_target_up" class="required">Exit 2 Target<sup class="text--danger">*</sup></label>
                <input type="text" name="exit_2_target_up" id="exit_2_target_up" class="form--control">
            </div>                   
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-md btn--base">Deploy</button>
    </div>
</form>


<script src="{{asset('assets/admin/js/vendor/select2.min.js')}}"></script>
<script>
    $(".select2").select2({
        dropdownParent:$("#editClientModal"),
        tags:true
    });
</script>
<script>
    $("#order_type_up").on('click',function(){
        var vl = $(this).val();
        // if(vl=='LIMIT'){
            $("#pyramid_percent_dv_up").show();
            $("#pyramid_percent_up").attr('required','required');
        // }else{
        //     $("#pyramid_percent_dv").hide();
        //     $("#pyramid_percent").removeAttr('required');
        // }
    });
</script>
<script>
    $("#symbol_name_up,#signal_tf_up").on('change',function(){
        var symbl = $("#symbol_name_up option:selected").val();
        var signl = $("#signal_tf_up option:selected").val();
        if(symbl!='' && signl!=''){
            $("#ce_symbol_name_up").html('<option value="">Loading...</option>');
            $("#pe_symbol_name_up").html('<option value="">Loading...</option>');
            $.post('{{url("user/get-pe-ce-symbol-names-order")}}',{'_token':'{{csrf_token()}}','symbol':symbl,'signal':signl},function(data){
                var cestr = '<option value="">Select</option>';
                var pestr = '<option value="">Select</option>';
                if(data.data.length){
                    var dataA = data.data;
                    for(var i in dataA){
                        cestr+=`<option value="${dataA[i].ce}">${dataA[i].ce}</option>`;
                        pestr+=`<option value="${dataA[i].pe}">${dataA[i].pe}</option>`;
                    }
                }
                $("#ce_symbol_name_up").html(cestr);
                $("#pe_symbol_name_up").html(pestr);
            });
        }else{
            $("#ce_symbol_name_up").html('<option value="">Select</option>');
            $("#pe_symbol_name_up").html('<option value="">Select</option>');
        }
        $("#ce_symbol_name_up,#pe_symbol_name_up").select2({
            dropdownParent:$("#editClientModal"),
            tags:true
        });
    })
</script>

<script>
    $("#strategy_name_up").on('change',function(){
        switch($("#strategy_name_up option:selected").val()){
            case 'Short Straddle':
                $(".ce_pe_symbl_1").show();
                $(".ce_pe_symbl_2").show();
                $("#ce_symbol_name_up").attr('required','required');
                $("#pe_symbol_name_up").attr('required','required');
                $("#ce_quantity_up").show().removeAttr('readonly');
                $("#pe_quantity_up").show().removeAttr('readonly');
                break;
            case 'Long Straddle':
                $(".ce_pe_symbl_1").show();
                $(".ce_pe_symbl_2").show();
                $("#ce_quantity_up").show().removeAttr('readonly');
                $("#pe_quantity_up").show().removeAttr('readonly');
                $("#ce_symbol_name_up").attr('required','required');
                $("#pe_symbol_name_up").attr('required','required');
                break;
            case 'Buy CE':
            case 'Bullish CE':
                $(".ce_pe_symbl_1").show();
                $(".ce_pe_symbl_2").hide();
                $("#ce_symbol_name_up").attr('required','required');
                $("#pe_symbol_name_up").removeAttr('required');
                $("#ce_quantity_up").show().removeAttr('readonly');
                $("#pe_quantity_up").attr('readonly','readonly').val(0);
                break;
            case 'Buy PE':
            case 'Bullish PE':
                $(".ce_pe_symbl_1").hide();
                $(".ce_pe_symbl_2").show();
                $("#pe_symbol_name_up").attr('required','required');
                $("#ce_symbol_name_up").removeAttr('required');
                $("#pe_quantity_up").show().removeAttr('readonly');
                $("#ce_quantity_up").attr('readonly','readonly').val(0);
                break;
            case 'Sell CE':
            case 'Bearish CE':
                $(".ce_pe_symbl_1").show();
                $(".ce_pe_symbl_2").hide();
                $("#ce_symbol_name_up").attr('required','required');
                $("#pe_symbol_name_up").removeAttr('required');
                $("#ce_quantity_up").show().removeAttr('readonly');
                $("#pe_quantity_up").attr('readonly','readonly').val(0);
                break;
            case 'Sell PE':
            case 'Bearish PE':
                $(".ce_pe_symbl_1").hide();
                $(".ce_pe_symbl_2").show();
                $("#pe_symbol_name_up").attr('required','required');
                $("#ce_symbol_name_up").removeAttr('required');
                $("#pe_quantity_up").show().removeAttr('readonly');
                $("#ce_quantity_up").attr('readonly','readonly').val(0);
                break;
            default:
                $(".ce_pe_symbl_1").hide();
                $(".ce_pe_symbl_2").hide();
                $("#ce_symbol_name_up").removeAttr('required');
                $("#pe_symbol_name_up").removeAttr('required');

        }
    });
</script>

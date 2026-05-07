<div class="row">
    <div class="col-lg-12">
        <div class="custom--card mb-3">
            <div class="card-body">
                <div id="heatmap-1"></div>
            </div>
        </div>
       
    </div>
</div>
<div class="custom--card">
    <div class="card-body p-0">
        <div class="table-responsive--md">
            <table class="table custom--table">
                <thead>
                    <tr>
                        <th>Stock Name</th>
                        <th>Buy Date</th>
                        <th>Buy Price</th>
                        <th>Quantity</th>
                        <th>Sold Date</th>
                        <th>Sell Price</th>
                        <th>PNL</th>
                    </tr>
                </thead>
                <tbody>
                    @isset($Ledger)
                        @if (count($Ledger))
                            @foreach ($Ledger as $item)
                            <tr>
                                <td>{{$item['stock_name']}}</td>
                                <td>{{showDate($item['bought_date'])}}</td>
                                <td>{{$item['buy_price']}}</td>
                                <td>{{$item['quantity']}}</td>
                                <td> @if (isset($item['sold_date']))
                                    {{showDate($item['sold_date'])}}
                                @else
                                    {{"---"}}
                                @endif</td>
                                <td>  @if (isset($item['sell_price']))
                                    {{$item['sell_price']}}
                                @else
                                    {{"---"}}
                                @endif</td>
                                <td><span class="{{$item['profit_loss'] > 0 ? "text-success" : "text-danger"}}">{{$item['profit_loss']}}</span> </td>
                            </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="100%"><span class="text--base">NO DATA</span></td> 
                            </tr>
                        @endif
                    @endisset    
                </tbody>
            </table>
        </div>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment-with-locales.min.js"></script>
<script src="{{asset('/assets/templates/basic/js/jquery.CalendarHeatmap.js')}}"></script>

<script>
    var data = {!! $datas !!};

$("#heatmap-1").CalendarHeatmap(data, {
    title: null,
    
    labels: {
        days:true,
        months:true,
    },
    tooltips: {
        show:true,
        options: {}
    }

});
</script>
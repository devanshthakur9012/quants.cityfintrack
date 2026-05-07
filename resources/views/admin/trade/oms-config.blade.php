@extends('admin.layouts.app')

@section('panel')
<div class="row">
    <div class="col-lg-12">
      
        <div class="card b-radius--10 ">
            <div class="card-body p-0">
                <div class="table-responsive--sm table-responsive">
                    <table class="table table--light style--two">
                        <thead>
                            <tr>
                                <th>#</th>
                             <th>Client Name</th>
                             <th>Strategy Name</th>
                             <th>TXN Type</th>
                             <th>Symbol Name</th>
                             <th>CE_Instrument</th>
                             <th>PE_Instrument</th>
                             <th>Buy LTP</th>
                             <th>Sell LTP</th>
                             <th>Order Type</th>
                             <th>Ttotal Qty</th>
                             <th>Pyramiding Lots</th>
                             <th>Pyramid_Freq</th>
                             <th>Pyramid Start Time</th>
                             <th>Pyramid End Time</th>
                             <th>Exit1</th>
                             <th>Exit2</th>
                             <th>Exit1 Ratio</th>
                             <th>Exit2 Ratio</th>
                             <th>Buy Price</th>
                             <th>Wait Time</th>
                            </tr>

  
                        </thead>
                        <tbody>
                            @for ($i = 1; $i < 15; $i++)
                            <tr>
                                <td>{{$i}}</td>
                                <td>PANKAJ1</td>
                                <td>Short Straddle</td>
                                <td>SELL</td>
                                <td>Nifty</td>
                                <td>TCS</td>
                                <td>NIFTY240422000CE</td>
                                <td>2</td>
                                <td>3</td>
                                <td>MARKET</td>
                                <td>100</td>
                                <td>10</td>
                                <td>1</td>
                                <td>09:20</td>
                                <td>9:30 Auto Populated</td>
                                <td>R1</td>
                                <td>R1+R2/2</td>
                                <td>50%</td>
                                <td>50%</td>
                                <td>95</td>
                                <td>5 Min.</td>
                            </tr>
                            @endfor
                                                

                        </tbody>
                    </table><!-- table end -->
                </div>
            </div>
            <div class="card-footer py-4">
                <nav class="d-flex justify-content-end">
                    <ul class="pagination">

                        <li class="page-item disabled" aria-disabled="true" aria-label="« Previous">
                            <span class="page-link" aria-hidden="true"><</span>
                        </li>





                        <li class="page-item active" aria-current="page"><span class="page-link">1</span></li>
                        <li class="page-item"><a class="page-link"
                                href="">2</a>
                        </li>
                        <li class="page-item"><a class="page-link"
                                href="">3</a>
                        </li>
                        <li class="page-item"><a class="page-link"
                                href="">4</a>
                        </li>
                        <li class="page-item"><a class="page-link"
                                href="">5</a>
                        </li>
                        <li class="page-item"><a class="page-link"
                                href="">6</a>
                        </li>
                  

                        <li class="page-item disabled" aria-disabled="true"><span class="page-link">...</span></li>

                        <li class="page-item"><a class="page-link"
                                href="8">18</a>
                        </li>
                        <li class="page-item"><a class="page-link"
                                href="9">19</a>
                        </li>


                        <li class="page-item">
                            <a class="page-link"
                                href=""
                                rel="next" aria-label="Next »">></a>
                        </li>
                    </ul>
                </nav>

            </div>
        </div><!-- card end -->
    </div>
</div>
@endsection

@push('breadcrumb-plugins')

@endpush
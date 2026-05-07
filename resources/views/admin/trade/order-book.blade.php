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
                               <th>Instrument</th>
                               <th>Entry Price</th>
                               <th>Entry Time</th>
                               <th>Exit Price</th>
                               <th>Exit Time</th>
                               <th>Order Type</th>
                               <th>Quantity</th>
                               <th>Product Type</th>
                               <th>Stoploss</th>
                               <th>Target</th>
                               <th>Order Status</th>
                               <th>Instrument_df_key</th>
                               <th>Close Position?</th>
                                

                            </tr>
                        </thead>
                        <tbody>
                            @for ($i = 1; $i < 5; $i++)
                            <tr>
                                <td>{{$i}}</td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
                               <td></td>
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
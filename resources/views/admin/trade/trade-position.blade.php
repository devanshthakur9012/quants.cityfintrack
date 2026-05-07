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
                               <th>TRADINGSYMBOL</th>
                               <th>EXCHANGE</th>
                               <th>QUANTITY</th>
                               <th>TIMEFRAME</th>
                               <th>ENTRY</th>
                               <th>PRICE</th>
                               <th>ENTRY TIME</th>
                               <th>EXIT PRICE</th>
                               <th>EXIT TIME</th>
                               <th>TARGET PRICE</th>
                               <th>SL PRICE</th>
                               <th>ROW TYPE</th>
                               <th>PROFIT</th>
                               <th>LTP</th>
                               <th>TREND</th>
                               <th>USER ID</th>
              
                            </tr>
                        </thead>
                        <tbody>
                            @for ($i = 1; $i < 15; $i++)
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
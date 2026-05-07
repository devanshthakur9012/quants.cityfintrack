@extends($activeTemplate . 'layouts.frontend')

@section('content')
  <section class="pt-100 pb-100 content-section">
      <div class="container">
          <div class="row justify-content-center">
              <div class="col-xxl-12 col-xl-12 col-lg-12">
                  @php echo $policy->data_values->details; @endphp
              </div>
          </div>
      </div>
  </section>
@endsection

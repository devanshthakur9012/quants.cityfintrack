@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
    .custom--table thead th,
    .custom--table tbody td {
        text-align: left !important;
        padding: 5px !important;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container content-container">
        <h4 class="mb-4">{{ $pageTitle }}</h4>

        @php $types = ['all' => 'All', 'long' => 'Long', 'short' => 'Short', 'covering' => 'Short Covering', 'unwinding' => 'Long Unwinding']; @endphp

        <ul class="nav nav-tabs mb-4" id="oiTab" role="tablist">
            @foreach($types as $key => $label)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }}" style="color: #00bf63;" data-bs-toggle="tab"
                        data-type="{{ $key }}" type="button" role="tab">
                        {{ $label }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="row" id="oi-section">
            @foreach(['long', 'short', 'covering', 'unwinding'] as $type)
            <div class="col-lg-12 mb-4 oi-card" id="table-{{ $type }}">
                <div class="custom--card card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-capitalize">OI - {{ $type }}</span>
                        <button class="btn btn-sm btn--base reload-btn rounded-pill" data-type="{{ $type }}">
                            <i class="las la-sync"></i>
                        </button>
                    </div>
                    <div class="card-body oi-table-body" data-type="{{ $type }}">
                        <div class="text-center">Loading...</div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endsection

@push('script')
<script>
    const allTypes = ['long', 'short', 'covering', 'unwinding'];

    function fetchOiData(type) {
        const container = $(`.oi-table-body[data-type="${type}"]`);
        container.html('<div class="text-center">Loading...</div>');

        $.post("{{ route('user.oi.buildup.fetch') }}", {
            _token: '{{ csrf_token() }}',
            type: type
        }, function (res) {
            container.html(res.html);
        });
    }

    function showTables(selectedType) {
        if (selectedType === 'all') {
            allTypes.forEach(type => {
                $(`#table-${type}`).show();
                fetchOiData(type);
            });
        } else {
            allTypes.forEach(type => {
                if (type === selectedType) {
                    $(`#table-${type}`).show();
                    fetchOiData(type);
                } else {
                    $(`#table-${type}`).hide();
                }
            });
        }
    }

    $(document).ready(function () {
        showTables('all'); // default load

        $('.nav-link').on('click', function () {
            $('.nav-link').removeClass('active');
            $(this).addClass('active');
            const selectedType = $(this).data('type');
            showTables(selectedType);
        });

        $('.reload-btn').on('click', function () {
            const type = $(this).data('type');
            fetchOiData(type);
        });
    });
</script>
@endpush

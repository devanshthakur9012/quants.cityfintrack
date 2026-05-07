{{-- Update your existing transition.blade.php --}}
@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-50 pb-50">
    <div class="container content-container">
        <h4 class="mb-3">{{ $pageTitle }}</h4>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="symbol">Select Symbol</label>
                <select id="symbol" class="form-control">
                    <option value="">-- Please select symbol --</option>
                    @foreach($symbols as $sym)
                        <option value="{{ $sym }}" {{ $sym == $symbol ? 'selected' : '' }}>{{ $sym }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label>&nbsp;</label>
                <button id="refreshData" class="btn btn-primary form-control">
                    <i class="fa fa-refresh"></i> Refresh Data
                </button>
            </div>
        </div>
        
        <div id="transitionsContainer">
            <div class="alert alert-info text-center">Please select a symbol to view transitions.</div>
        </div>
    </div>
</section>
@endsection

@push('script')
<script>
$(document).ready(function() {
    function loadTransitions() {
        let symbol = $('#symbol').val();
        
        if (!symbol) {
            $('#transitionsContainer').html(
                '<div class="alert alert-info text-center">Please select a symbol to view transitions.</div>'
            );
            return;
        }
        
        $('#transitionsContainer').html(
            '<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>'
        );
        
        $.ajax({
            url: "{{ route('user.oi-transitions-fetch') }}",
            type: "GET", 
            data: { symbol: symbol },
            success: function(res) {
                $('#transitionsContainer').html(res.html);
            },
            error: function() {
                $('#transitionsContainer').html(
                    '<div class="alert alert-danger text-center">Error loading data. Please try again.</div>'
                );
            }
        });
    }
    
    $('#symbol').change(function() {
        loadTransitions();
    });
    
    $('#refreshData').click(function() {
        loadTransitions();
    });
});
</script>
@endpush


@push('style')
<style>
/* Updated CSS with time badge styles */

.transition-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    overflow: hidden;
    /* border: 1px solid #e3e6f0; */
}

.transition-header {
    background: #00bf63;
    color: white;
    padding: 15px 25px;
    font-weight: 600;
    font-size: 16px;
}

.transition-table {
    width: 100%;
    margin: 0;
    border-collapse: collapse;
}

.transition-table th {
    background: #f8f9fc;
    padding: 15px 12px;
    font-weight: 600;
    font-size: 13px;
    border-bottom: 2px solid #e3e6f0;
    text-align: center;
    vertical-align: middle;
    color: #5a5c69;
}

.transition-table td {
    padding: 10px 8px;
    font-size: 12px;
    text-align: center;
    vertical-align: middle;
    border-bottom: 1px solid #6666666b;
    background: #0d222b;
    color: #fff;
}

.signal-long-buildup {
    background-color: #0222daff !important;
    color: #0c5460;
    font-weight: 400;
    /* border-left: 4px solid #17a2b8; */
}

.signal-short-buildup {
    background-color: #df0013ff !important;
    color: #721c24;
    font-weight: 600;
    /* border-left: 4px solid #dc3545; */
}

.signal-short-covering {
    background-color: #e3ae00ff !important;
    color: #856404;
    font-weight: 600;
    /* border-left: 4px solid #ffc107; */
}

.signal-long-unwinding {
    background-color: #00bf63 !important;
    color: #383d41;
    font-weight: 600;
    /* border-left: 4px solid #6c757d; */
}

.date-cell {
    font-weight: 500;
    color: #495057;
    font-size: 11px;
    white-space: nowrap;
    background-color: #f8f9fc;
}

.time-badge {
    background-color: rgba(0,0,0,0.1);
    color: #333;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    margin-bottom: 4px;
    display: inline-block;
}

.signal-long-buildup .time-badge {
    background-color: rgba(23, 162, 184, 0.2);
    color: #0c5460;
}

.signal-short-buildup .time-badge {
    background-color: rgba(220, 53, 69, 0.2);
    color: #721c24;
}

.signal-short-covering .time-badge {
    background-color: rgba(255, 193, 7, 0.2);
    color: #856404;
}

.signal-long-unwinding .time-badge {
    background-color: rgba(108, 117, 125, 0.2);
    color: #383d41;
}

.ltp-value {
    font-weight: 600;
    color: #007bff;
    margin-bottom: 3px;
    font-size: 11px;
}

.oi-info {
    font-size: 10px;
    color: #6c757d;
    margin-bottom: 2px;
}

.signal-label {
    font-size: 9px;
    color: #495057;
    font-weight: 500;
    display: block;
    margin-top: 2px;
    font-style: italic;
}

.empty-cell {
    color: #adb5bd;
    font-style: italic;
    font-size: 14px;
}

@media (max-width: 768px) {
    .transition-table {
        font-size: 10px;
    }
    
    .transition-table th,
    .transition-table td {
        padding: 8px 4px;
    }
    
    .date-cell {
        font-size: 9px;
    }
    
    .time-badge {
        font-size: 8px;
        padding: 1px 4px;
    }
    
    .signal-label {
        font-size: 8px;
    }
    
    .ltp-value {
        font-size: 10px;
    }
}
</style>
@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <h3>{{ $pageTitle }}</h3>
        <div class="mb-3">
            <label>Select Symbol:</label>
            <select id="symbolSelect" class="form-control">
                @foreach ($symbols as $symbol)
                    <option value="{{ $symbol }}">{{ $symbol }}</option>
                @endforeach
            </select>
        </div>

        <!-- Chart 1: Open Interest -->
        <div class="mb-5">
            <canvas id="oiChart" height="100"></canvas>
        </div>

        <!-- Chart 2: Price -->
        <div>
            <canvas id="priceChart" height="100"></canvas>
        </div>
    </div>
</section>
@endsection

@push('script')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.getElementById('symbolSelect').addEventListener('change', loadData);
    window.addEventListener('load', loadData);

    function loadData() {
        let symbol = document.getElementById('symbolSelect').value;
        fetch('{{ route('user.analysis.data') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ symbol })
        })
        .then(res => res.json())
        .then(data => {
            if (!data || data.length === 0) {
                alert('No data found for this symbol');
                return;
            }

            let labels = data.map(d => d.date);

            // OI data
            let futureOI = data.map(d => d.future_oi);
            let ceOI = data.map(d => d.ce_oi);
            let peOI = data.map(d => d.pe_oi);

            // Price data
            let futurePrice = data.map(d => d.future_close);
            let cePrice = data.map(d => d.ce_close);
            let pePrice = data.map(d => d.pe_close);

            // get last row's symbols to show in legend (they are same for all rows)
            let last = data[data.length - 1] || {};
            let futSymbol = last.future_symbol || 'Future';
            let ceSymbol = last.ce_symbol || 'CE';
            let peSymbol = last.pe_symbol || 'PE';

            // destroy old charts if exist
            if (window.oiChartInstance) window.oiChartInstance.destroy();
            if (window.priceChartInstance) window.priceChartInstance.destroy();

            // Chart 1: OI Flow
            window.oiChartInstance = new Chart(document.getElementById('oiChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: futSymbol + ' OI',
                            data: futureOI,
                            borderColor: 'blue',
                            fill: false,
                            tension: 0.2
                        },
                        {
                            label: ceSymbol + ' OI',
                            data: ceOI,
                            borderColor: 'green',
                            fill: false,
                            tension: 0.2
                        },
                        {
                            label: peSymbol + ' OI',
                            data: peOI,
                            borderColor: 'red',
                            fill: false,
                            tension: 0.2
                        },
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Open Interest Flow for ' + symbol
                        },
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Open Interest'
                            }
                        }
                    }
                }
            });

            // Chart 2: Price Flow
            window.priceChartInstance = new Chart(document.getElementById('priceChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: futSymbol + ' Price',
                            data: futurePrice,
                            borderColor: 'purple',
                            fill: false,
                            tension: 0.2
                        },
                        {
                            label: ceSymbol + ' Price',
                            data: cePrice,
                            borderColor: 'orange',
                            fill: false,
                            tension: 0.2
                        },
                        {
                            label: peSymbol + ' Price',
                            data: pePrice,
                            borderColor: 'brown',
                            fill: false,
                            tension: 0.2
                        },
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Price Flow for ' + symbol
                        },
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Price'
                            }
                        }
                    }
                }
            });
        });
    }
</script>
@endpush
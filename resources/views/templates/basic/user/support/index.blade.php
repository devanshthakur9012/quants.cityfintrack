@extends($activeTemplate.'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">
        <div class="row mt-5">
            <div class="col-lg-12">
                <div class="custom--card">
                    <div class="card-body p-0">
                        <div class="table-responsive--md">
                            <table class="table custom--table">
                                <thead>
                                    <tr>
                                        <th>@lang('Subject')</th>
                                        <th>@lang('Status')</th>
                                        <th>@lang('Priority')</th>
                                        <th>@lang('Last Reply')</th>
                                        <th>@lang('Action')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($supports as $key => $support)
                                    <tr>
                                        <td>
                                            <a href="{{ route('ticket.view', $support->ticket) }}" class="fw-bold text--base">
                                                [@lang('Ticket')#{{ $support->ticket }}]
                                                <br>
                                                <span class="text-white">{{ __($support->subject) }}</span>
                                            </a>
                                        </td>
                                        <td>
                                            @php echo $support->statusBadge; @endphp
                                        </td>
                                        <td>
                                            @if($support->priority == Status::PRIORITY_LOW)
                                                <span class="badge badge--dark">@lang('Low')</span>
                                            @elseif($support->priority == Status::PRIORITY_MEDIUM)
                                                <span class="badge  badge--warning">@lang('Medium')</span>
                                            @elseif($support->priority == Status::PRIORITY_HIGH)
                                                <span class="badge badge--danger">@lang('High')</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ showDateTime($support->last_reply) }}<br>{{ diffForHumans($support->last_reply) }}
                                        </td>

                                        <td>
                                            <a href="{{ route('ticket.view', $support->ticket) }}" class="icon-btn bg--base" data-bs-toggle="tooltip" data-bs-position="top" title="@lang('View')">
                                                <i class="las la-desktop"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="100%" class="text-center justify-content-center">@lang('Data Not Found')!</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="pt-50 justify-content-center d-flex">
            {{ paginateLinks($supports) }}
        </div>
    </div>
</section>
@endsection

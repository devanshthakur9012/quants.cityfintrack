@extends($activeTemplate . 'layouts.master')

@section('content')
    <section class="pt-50 pb-50">
        <div class="container content-container">
            <!-- Header -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4>{{ $pageTitle }}</h4>
                        <p class="text-muted">Manage your Zerodha broker accounts for automated trading</p>
                    </div>
                    <div>
                        <button type="button" class="btn btn--base" data-bs-toggle="modal" data-bs-target="#addBrokerModal">
                            <i class="las la-plus-circle"></i> Add Zerodha Broker
                        </button>
                    </div>
                </div>
            </div>

            <!-- Brokers Table -->
            <div class="custom--card">
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table custom--table">
                            <thead>
                                <tr>
                                    <th>Client Name</th>
                                    <th>Account Username</th>
                                    <th>API Key</th>
                                    <th>Token Status</th>
                                    <th>Symbols Assigned</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($brokers as $broker)
                                    <tr>
                                        <td><strong>{{ $broker->client_name }}</strong></td>
                                        <td>{{ $broker->account_user_name }}</td>
                                        <td><code class="small">{{ substr($broker->api_key, 0, 15) }}...</code></td>
                                        <td>
                                            @if ($broker->hasValidToken())
                                                <span class="badge badge--success">
                                                    <i class="las la-check-circle"></i> Valid
                                                </span>
                                                <br>
                                                <small class="text-muted">{{ $broker->token_expiry_remaining }}</small>
                                            @elseif($broker->access_token)
                                                <span class="badge badge--warning">
                                                    <i class="las la-exclamation-triangle"></i> Expired
                                                </span>
                                            @else
                                                <span class="badge badge--danger">
                                                    <i class="las la-times-circle"></i> Not Set
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge--primary">
                                                {{ $broker->monitored_symbols_count }} symbols
                                            </span>
                                        </td>
                                        <td>
                                            @if ($broker->last_login_at)
                                                {{ $broker->last_login_at->diffForHumans() }}
                                            @else
                                                <span class="text-muted">Never</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <!-- Login Button -->
                                                <a href="{{ route('zerodha-broker.login', $broker->id) }}"
                                                    class="btn btn-sm btn-success" 
                                                    title="Login to Zerodha"
                                                    target="_blank">
                                                    <i class="las la-sign-in-alt"></i> Login
                                                </a>

                                                <!-- Update Token -->
                                                <button class="btn btn-sm btn-warning update-token-btn"
                                                    data-id="{{ $broker->id }}" title="Update Access Token">
                                                    <i class="las la-key"></i> Update Token
                                                </button>

                                                <!-- Edit -->
                                                <button class="btn btn-sm btn-info edit-broker-btn"
                                                    data-id="{{ $broker->id }}" title="Edit Broker">
                                                    <i class="las la-pencil-alt"></i>
                                                </button>

                                                <!-- Delete -->
                                                <button class="btn btn-sm btn-danger delete-broker-btn"
                                                    data-id="{{ $broker->id }}" title="Delete Broker">
                                                    <i class="las la-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <i class="las la-inbox text-muted" style="font-size: 3rem;"></i>
                                            <h5 class="text-muted">No Zerodha Brokers Found</h5>
                                            <p class="text-muted">Add your first Zerodha broker to get started</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="mt-4 d-flex justify-content-center">
                {{ $brokers->links() }}
            </div>
        </div>
    </section>

    <!-- Add Broker Modal -->
    <div class="modal fade" id="addBrokerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="{{ route('zerodha-broker.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Zerodha Broker</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-lg-6 form-group">
                                <label class="required">Client Name <sup class="text--danger">*</sup></label>
                                <input type="text" name="client_name" class="form--control" required>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">Broker Name <sup class="text--danger">*</sup></label>
                                <input type="text" name="broker_name" value="Zerodha" class="form--control" required>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">Account Username <sup class="text--danger">*</sup></label>
                                <input type="text" name="account_user_name" class="form--control" required>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">Account Password <sup class="text--danger">*</sup></label>
                                <input type="password" name="account_password" class="form--control" required>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">API Key <sup class="text--danger">*</sup></label>
                                <input type="text" name="api_key" class="form--control" required>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">API Secret <sup class="text--danger">*</sup></label>
                                <input type="text" name="api_secret_key" class="form--control" required>
                            </div>

                            <div class="col-lg-6 form-group">
                                <label>Security PIN</label>
                                <input type="text" name="security_pin" class="form--control">
                            </div>

                            <div class="col-lg-6 form-group">
                                <label class="required">TOTP Secret <sup class="text--danger">*</sup></label>
                                <input type="text" name="totp" class="form--control" required>
                                <small class="text-muted">Base32 encoded TOTP secret from Zerodha 2FA</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn--base">Add Broker</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Token Modal -->
    <div class="modal fade" id="updateTokenModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="updateTokenForm" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Update Access Token</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert--info">
                            <strong>Steps:</strong>
                            <ol class="mb-0 mt-2">
                                <li>Click <strong>"Login"</strong> button (opens in new tab)</li>
                                <li>Complete Zerodha authentication</li>
                                <li>After successful login, copy the <strong>entire URL</strong> from browser address bar</li>
                                <li>Paste the complete URL in the field below</li>
                            </ol>
                            <p class="mt-3 mb-0"><strong>Example URL format:</strong></p>
                            <code class="d-block mt-2 small">https://kite.zerodha.com/connect/login?type=login&status=success&request_token=XXXXX&action=login</code>
                        </div>
                        <div class="form-group">
                            <label>Callback URL <sup class="text--danger">*</sup></label>
                            <textarea name="callback_url" class="form--control" rows="3" required placeholder="Paste the complete callback URL here"></textarea>
                            <small class="text-muted">Paste the entire URL from your browser after Zerodha login</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn--base">
                            <i class="las la-key"></i> Generate & Save Token
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal Container -->
    <div class="modal fade" id="editBrokerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" id="editModalContent">
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this broker?</p>
                    <p class="text-danger"><strong>Warning:</strong> All associated symbols and configurations will be
                        deleted!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Yes, Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).ready(function() {
            // Update Token
            $('.update-token-btn').click(function() {
                const brokerId = $(this).data('id');
                const url = "{{ route('zerodha-broker.update-token', ':id') }}".replace(':id', brokerId);
                $('#updateTokenForm').attr('action', url);
                $('#updateTokenModal').modal('show');
            });

            // Edit Broker
            $('.edit-broker-btn').click(function() {
                const brokerId = $(this).data('id');
                const url = "{{ route('zerodha-broker.edit', ':id') }}".replace(':id', brokerId);

                $('#editBrokerModal').modal('show');
                $('#editModalContent').html(
                    '<div class="text-center p-5"><div class="spinner-border text-primary"></div><p class="mt-3">Loading...</p></div>'
                );

                $.get(url, function(data) {
                    $('#editModalContent').html(data);
                });
            });

            // Delete Broker
            $('.delete-broker-btn').click(function() {
                const brokerId = $(this).data('id');
                const url = "{{ route('zerodha-broker.destroy', ':id') }}".replace(':id', brokerId);
                $('#deleteForm').attr('action', url);
                $('#deleteModal').modal('show');
            });
        });
    </script>
@endpush

@push('style')
    <style>
        .badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .badge--success {
            background: #10b981;
            color: white;
        }

        .badge--danger {
            background: #ef4444;
            color: white;
        }

        .badge--warning {
            background: #f59e0b;
            color: white;
        }

        .badge--primary {
            background: #3b82f6;
            color: white;
        }

        .btn-group .btn {
            margin-right: 2px;
        }

        .btn-outline-success {
            border-color: #10b981;
            color: #10b981;
        }

        .btn-outline-success:hover {
            background: #10b981;
            color: white;
        }

        code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.875rem;
        }

        .alert--info ol {
            padding-left: 20px;
        }

        .alert--info code {
            display: block;
            word-break: break-all;
            white-space: normal;
        }
    </style>
@endpush
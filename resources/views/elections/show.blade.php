{{-- PIC: Low Wen Kai - Election Management Module --}}

@extends('layouts.app')

@section('title', $election->title)

@section('content')
@php
    $backRoute = $isAdminView ? route('admin.elections.index') : route('elections.index');
    $transitionRoute = $isAdminView ? route('admin.elections.transition', $election) : route('elections.transition', $election);
    $eligibility = \App\ValueObjects\EligibilityRules::fromStored($election->eligibility_rules);
    $isPrivateElection = $eligibility->mode() === \App\ValueObjects\EligibilityRules::PRIVATE;
    $displayHostingFee = $election->hosting_fee ?? \App\Models\Election::hostingFeeForMode($eligibility->mode());
    $displayCurrency = $election->currency ?: strtoupper((string) config('election.hosting_fee.currency', 'MYR'));
    $nominationStart = \Carbon\CarbonImmutable::parse($election->nomination_start);
    $nominationEnd = \Carbon\CarbonImmutable::parse($election->nomination_end);
    $votingStart = \Carbon\CarbonImmutable::parse($election->voting_start);
    $votingEnd = \Carbon\CarbonImmutable::parse($election->voting_end);
    $canManagePrivateVoters = $canManageParticipants && $isPrivateElection;
    $canAssignPrivateCandidates = $canManageParticipants && $isPrivateElection;
    $canReviewCandidates = ! $isAdminView
        && $election->status === \App\States\ElectionStatus::NOMINATION_OPEN->value;
    $hostingPaymentConfirmed = (bool) ($hostingPaymentReadiness['confirmed'] ?? false);
@endphp
<div class="election-shell election-management">
    <a class="election-link election-back-link" href="{{ $backRoute }}">&larr; {{ $isAdminView ? 'Approval workspace' : 'All elections' }}</a>

    <section class="election-workspace-hero">
        <div class="election-workspace-hero-copy">
            <div class="election-workspace-hero-meta">
                <span class="election-status election-status-{{ strtolower(str_replace('_', '-', $election->status)) }}">{{ $availability['lifecycleLabel'] }}</span>
                <span>Election #{{ $election->getKey() }}</span>
            </div>
            <h1>{{ $election->title }}</h1>
            <p>{{ $election->description ?: 'Manage the election setup, private voter access, candidates, and lifecycle from one workspace.' }}</p>
            <div class="election-workspace-tags">
                <span>{{ $isPrivateElection ? 'Private voter list' : 'Public eligibility' }}</span>
                <span>
                    {{ $displayCurrency }} {{ number_format((float) $displayHostingFee, 2) }} hosting fee
                </span>
            </div>
        </div>

        <div class="election-workspace-hero-side">
            <div class="election-workspace-hero-actions">
                @if ($election->status === \App\States\ElectionStatus::CLOSED->value)
                    <a class="election-button election-button-light" href="{{ route('voting.results', $election) }}">View results</a>
                @endif
                @if (! $isAdminView && $election->status === \App\States\ElectionStatus::DRAFT->value)
                    <a class="election-button election-button-light" href="{{ route('elections.edit', $election) }}">Edit election</a>
                @endif
                @if ($canManagePrivateVoters)
                    <a class="election-button election-button-light" href="#private-voters">Add voters</a>
                @endif
                @if ($canAssignPrivateCandidates)
                    <a class="election-button election-button-light" href="#candidates">Assign candidate</a>
                @endif
            </div>
            <dl class="election-workspace-hero-facts">
                <div><dt>Nomination closes</dt><dd>{{ \Carbon\CarbonImmutable::parse($election->nomination_end)->format('d M, H:i') }}</dd></div>
                <div><dt>Voting opens</dt><dd>{{ \Carbon\CarbonImmutable::parse($election->voting_start)->format('d M, H:i') }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="election-workspace-grid">
        <article class="election-card election-workspace-panel election-lifecycle-panel">
            <div class="election-panel-heading">
                <div>
                    <span class="election-eyebrow">Election lifecycle</span>
                    <h2>Move the election forward</h2>
                    <p>Complete the next available step to keep the election on schedule.</p>
                </div>
                <span class="election-stage-indicator">{{ $availability['lifecycleLabel'] }}</span>
            </div>

            @forelse ($actions as $action)
                <form class="election-action election-stage-action" method="post" action="{{ $transitionRoute }}">
                    @csrf
                    <input type="hidden" name="action" value="{{ $action['action'] }}">
                    <input type="hidden" name="version" value="{{ $election->version }}">
                    <div class="election-action-copy">
                        <h3>{{ $action['label'] }}</h3>
                        <p>{{ $action['description'] }}</p>
                    </div>
                    @if ($action['action'] === 'submit_payment')
                        <div class="election-payment-checkout{{ $hostingPaymentConfirmed ? ' is-confirmed' : '' }}">
                            <div class="election-payment-checkout-copy">
                                <span class="election-step-badge">Step 1</span>
                                <div>
                                    <strong>Pay the hosting fee</strong>
                                    <span class="election-payment-status{{ $hostingPaymentConfirmed ? ' is-confirmed' : '' }}">{{ $hostingPaymentReadiness['label'] ?? 'Awaiting payment' }}</span>
                                    <p>{{ $hostingPaymentReadiness['message'] ?? 'Complete the secure checkout with IDontPay99. Your payment is linked automatically to this election.' }}</p>
                                </div>
                            </div>
                            <a class="election-button election-button-payment" href="{{ route('payments.checkout.election', $election) }}">
                                {{ $hostingPaymentConfirmed ? 'View confirmed payment' : 'Pay '.$displayCurrency.' '.number_format((float) $displayHostingFee, 2).' hosting fee' }}
                            </a>
                        </div>
                    @endif
                    @if ($action['requiresReason'])
                        <label>Reason<input name="reason" maxlength="250" required></label>
                    @endif
                    <label>
                        @if ($action['action'] === 'submit_payment')
                            <span class="election-field-heading"><span class="election-step-badge">Step 2</span> Confirm with current password</span>
                        @else
                            Current password
                        @endif
                        <input name="current_password" type="password" autocomplete="current-password" @required($action['action'] !== 'submit_payment' || $hostingPaymentConfirmed) @disabled($action['action'] === 'submit_payment' && ! $hostingPaymentConfirmed)>
                    </label>
                    <button class="election-button {{ $action['tone'] === 'danger' ? 'election-button-danger' : ($action['tone'] === 'primary' ? 'election-button-primary' : '') }}" type="submit" @disabled($action['action'] === 'submit_payment' && ! $hostingPaymentConfirmed)>{{ $action['label'] }}</button>
                    @if ($action['action'] === 'submit_payment' && ! $hostingPaymentConfirmed)
                        <p class="election-payment-gate-note">This action unlocks only after the Payment module confirms the hosting fee.</p>
                    @endif
                </form>
            @empty
                <div class="election-panel-empty">
                    <strong>No action is due right now.</strong>
                    <p>This election is currently {{ str($election->status)->replace('_', ' ')->lower() }}.</p>
                </div>
            @endforelse
        </article>

        <aside class="election-card election-workspace-panel election-configuration-panel">
            <div class="election-panel-heading">
                <div>
                    <span class="election-eyebrow">Configuration</span>
                    <h2>Election at a glance</h2>
                </div>
            </div>
            <dl class="election-facts election-facts-stacked election-configuration-facts">
                <div><dt>Voter eligibility</dt><dd>{{ $eligibility->summary() }}</dd></div>
                <div><dt>Position</dt><dd>{{ $election->position_sought ?: 'Not recorded' }}</dd></div>
                @if ($isPrivateElection)
                    <div><dt>Approved voters</dt><dd>{{ $election->private_voter_count }} assigned{{ $election->private_voter_unmatched_count ? ', '.$election->private_voter_unmatched_count.' unmatched' : '' }}</dd></div>
                @endif
                <div class="election-configuration-period election-configuration-period-nomination">
                    <dt><span aria-hidden="true"></span>Nomination period</dt>
                    <dd>
                        <time datetime="{{ $nominationStart->toIso8601String() }}">
                            <small>Starts</small>
                            <strong>{{ $nominationStart->format('d M Y') }}</strong>
                            <span>{{ $nominationStart->format('H:i') }}</span>
                        </time>
                        <time datetime="{{ $nominationEnd->toIso8601String() }}">
                            <small>Ends</small>
                            <strong>{{ $nominationEnd->format('d M Y') }}</strong>
                            <span>{{ $nominationEnd->format('H:i') }}</span>
                        </time>
                    </dd>
                </div>
                <div class="election-configuration-period election-configuration-period-voting">
                    <dt><span aria-hidden="true"></span>Voting period</dt>
                    <dd>
                        <time datetime="{{ $votingStart->toIso8601String() }}">
                            <small>Starts</small>
                            <strong>{{ $votingStart->format('d M Y') }}</strong>
                            <span>{{ $votingStart->format('H:i') }}</span>
                        </time>
                        <time datetime="{{ $votingEnd->toIso8601String() }}">
                            <small>Ends</small>
                            <strong>{{ $votingEnd->format('d M Y') }}</strong>
                            <span>{{ $votingEnd->format('H:i') }}</span>
                        </time>
                    </dd>
                </div>
                <div class="election-configuration-split">
                    <div class="election-configuration-split__item">
                        <dt>Hosting fee</dt>
                        <dd>{{ $displayCurrency }} {{ number_format((float) $displayHostingFee, 2) }}</dd>
                    </div>
                    <div class="election-configuration-split__item">
                        <dt>Payment</dt>
                        <dd>
                            @if ($availability['hostingPaymentVerified'])
                                Confirmed by Payment module
                            @elseif ($election->status === \App\States\ElectionStatus::PENDING_ADMIN_APPROVAL->value)
                                Submitted for verification
                            @elseif ($election->status === \App\States\ElectionStatus::PENDING_PAYMENT->value)
                                Ready for payment
                            @else
                                Not yet available
                            @endif
                        </dd>
                    </div>
                    <div class="election-configuration-split__item">
                        <dt>Approval</dt>
                        <dd>{{ $availability['administratorApproved'] ? 'Approved' : 'Required' }}</dd>
                    </div>
                </div>
            </dl>
        </aside>
    </section>

    @if (! $isAdminView && $isPrivateElection)
        <section id="private-voters" class="election-card election-workspace-panel election-voter-panel {{ $canManagePrivateVoters ? '' : 'election-voter-panel-locked' }}">
            <div class="election-panel-heading election-voter-panel-heading">
                <div>
                    <span class="election-eyebrow">Private voter access</span>
                    <h2>{{ $canManagePrivateVoters ? 'Add voters to this election' : 'Private voter list' }}</h2>
                    <p>
                        @if ($canManagePrivateVoters)
                            Add registered voters one at a time, or import a spreadsheet. Candidate and voter management stay available together until voting begins.
                        @else
                            {{ $election->private_voter_count }} approved voter(s) are assigned. The list is locked after the nomination stage to protect the ballot.
                        @endif
                    </p>
                </div>
                <div class="election-voter-count">
                    <strong data-private-voter-count>{{ $election->private_voter_count }}</strong>
                    <span>approved voters</span>
                </div>
            </div>

            @if ($election->private_voter_list_uploaded_at)
                <p class="election-roster-update">Last updated {{ $election->private_voter_list_uploaded_at->format('d M Y, H:i') }}. {{ $election->private_voter_unmatched_count }} spreadsheet row(s) did not match a registered user.</p>
            @endif

            @if ($canManagePrivateVoters)
                <div class="election-private-voter-tools">
                    <section
                        class="election-roster-method election-voter-directory"
                        data-private-voter-directory
                        data-participant-directory
                        data-participant-singular="voter"
                        data-participant-role="voter access"
                        data-result-label="active registered user"
                        data-count-selector="[data-private-voter-count]"
                        data-empty-message="No registered users are available to assign as voters."
                        data-no-match-message="No registered user matches that IC or passport number."
                        data-directory-url="{{ route('elections.private-voters.directory', $election) }}"
                        data-selection-url="{{ route('elections.private-voters.selection', ['election' => $election, 'user' => '__USER_ID__']) }}"
                    >
                        <span class="election-roster-method-kicker">Registered user directory</span>
                        <h3>Choose voters from your members</h3>
                        <p>Search by Malaysian IC or passport number, then tick an active member to assign voter access. Unverified members can be assigned now but must verify their profile before they can access the ballot and vote.</p>
                        <label class="election-directory-search" for="private-voter-search">
                            <span>Search IC or passport number</span>
                            <input id="private-voter-search" data-participant-search data-voter-search type="search" maxlength="30" autocomplete="off" placeholder="e.g. 050729145232">
                        </label>
                        <div class="election-directory-summary" data-voter-directory-summary aria-live="polite">Loading registered users…</div>
                        <div class="election-directory-list" data-participant-directory-list data-voter-directory-list aria-live="polite" aria-busy="true"></div>
                        <button class="election-directory-load-more" data-participant-directory-more data-voter-directory-more type="button" hidden>Load more users</button>
                        <p class="election-help">Disabled accounts are excluded. Unticking a user removes only their voter assignment, and importing a spreadsheet never removes existing voters.</p>
                    </section>

                    <form class="election-roster-method" method="post" action="{{ route('elections.private-voters.import', $election) }}" enctype="multipart/form-data">
                        @csrf
                        <span class="election-roster-method-kicker">Spreadsheet import</span>
                        <h3>Import a voter list</h3>
                        <p>Upload a CSV, XLSX, or XLS file with the exact headers <code>full_name</code> and <code>ic_number</code>. The identity column can contain IC or passport numbers.</p>
                        <div class="election-roster-template">
                            <div>
                                <strong>Start with the voter template</strong>
                                <p>Open it in Excel, replace the example rows, and keep both column headers unchanged.</p>
                            </div>
                            <a class="election-button election-button-small" href="{{ route('elections.private-voters.template') }}" download>Download CSV template</a>
                        </div>
                        <div class="election-file-upload" data-file-upload>
                            <span class="election-file-upload__title">Voter spreadsheet</span>
                            <input id="voter_list" class="election-file-upload__native" name="voter_list" type="file" accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" aria-required="true" hidden>
                            <div class="election-file-upload__control">
                                <label for="voter_list" tabindex="0" data-file-trigger>Choose file</label>
                                <span data-file-name>No file chosen</span>
                            </div>
                        </div>
                        <p class="election-help">For Excel files, set the <code>ic_number</code> column to <strong>Text</strong> so IC leading zeroes are retained. Active unverified users may be imported but must verify before voting. Existing voters are skipped, and omitted users remain assigned.</p>
                        @error('voter_list') <small class="election-field-error">{{ $message }}</small> @enderror
                        <button class="election-button election-button-primary" type="submit">Import voters</button>
                    </form>
                </div>
            @endif
        </section>
    @endif

    <section id="candidates" class="election-card election-workspace-panel election-candidate-panel">
            <div class="election-panel-heading">
                <div>
                    <span class="election-eyebrow">{{ $isPrivateElection ? 'Candidate assignment' : 'Candidate review' }}</span>
                    <h2>{{ $isPrivateElection ? 'Assign candidates to the ballot' : 'Build a complete ballot' }}</h2>
                    <p><span data-private-candidate-count>{{ $candidateSummary['approved'] }}</span> approved, {{ $candidateSummary['pending'] }} pending, {{ $candidateSummary['rejected'] }} rejected.</p>
                </div>
                <span class="election-status {{ $candidateSummary['ready'] ? 'election-status-nomination-open' : 'election-status-pending-payment' }}">{{ $candidateSummary['ready'] ? 'Ballot ready' : 'Review required' }}</span>
            </div>

            @if ($canAssignPrivateCandidates)
                <div class="election-private-voter-tools election-private-candidate-tools">
                    <section
                        class="election-roster-method election-voter-directory"
                        data-private-candidate-directory
                        data-participant-directory
                        data-participant-singular="candidate"
                        data-participant-role="candidate assignment"
                        data-result-label="eligible verified member"
                        data-count-selector="[data-private-candidate-count]"
                        data-empty-message="No eligible verified members are available to assign as candidates."
                        data-no-match-message="No eligible member matches that name, email, IC, or passport number."
                        data-directory-url="{{ route('elections.private-candidates.directory', $election) }}"
                        data-selection-url="{{ route('elections.private-candidates.selection', ['election' => $election, 'user' => '__USER_ID__']) }}"
                    >
                        <span class="election-roster-method-kicker">Registered user directory</span>
                        <h3>Choose candidates from your members</h3>
                        <p>Search by name, email, Malaysian IC, or passport number, then tick a member to assign them. Changes save immediately.</p>
                        <label class="election-directory-search" for="private-candidate-search">
                            <span>Search registered members</span>
                            <input id="private-candidate-search" data-participant-search type="search" maxlength="100" autocomplete="off" placeholder="Name, email, IC, or passport">
                        </label>
                        <div class="election-directory-summary" data-participant-directory-summary aria-live="polite">Loading registered users&hellip;</div>
                        <div class="election-directory-list" data-participant-directory-list aria-live="polite" aria-busy="true"></div>
                        <button class="election-directory-load-more" data-participant-directory-more type="button" hidden>Load more users</button>
                        <p class="election-help">Unticking a candidate marks the assignment rejected without deleting its audit history. Candidate decisions remain editable during nominations.</p>
                    </section>

                    <form class="election-roster-method" method="post" action="{{ route('elections.private-candidates.import', $election) }}" enctype="multipart/form-data">
                        @csrf
                        <span class="election-roster-method-kicker">Spreadsheet import</span>
                        <h3>Import a candidate list</h3>
                        <p>Upload a CSV, XLSX, or XLS file with the exact headers <code>full_name</code> and <code>ic_number</code>. The identity column can contain IC or passport numbers.</p>
                        <div class="election-roster-template">
                            <div>
                                <strong>Start with the candidate template</strong>
                                <p>Open it in Excel, replace the example rows, and keep both column headers unchanged.</p>
                            </div>
                            <a class="election-button election-button-small" href="{{ route('elections.private-candidates.template') }}" download>Download CSV template</a>
                        </div>
                        <div class="election-file-upload" data-file-upload>
                            <span class="election-file-upload__title">Candidate spreadsheet</span>
                            <input id="candidate_list" class="election-file-upload__native" name="candidate_list" type="file" accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" aria-required="true" hidden>
                            <div class="election-file-upload__control">
                                <label for="candidate_list" tabindex="0" data-file-trigger>Choose file</label>
                                <span data-file-name>No file chosen</span>
                            </div>
                        </div>
                        <p class="election-help">For Excel files, set the <code>ic_number</code> column to <strong>Text</strong> so leading zeroes are retained. Existing candidates are skipped, and omitted candidates remain assigned.</p>
                        @error('candidate_list') <small class="election-field-error">{{ $message }}</small> @enderror
                        <button class="election-button election-button-primary" type="submit">Import candidates</button>
                    </form>
                </div>
            @endif

            @if ($candidateSummary['applications']->isEmpty())
                <div class="election-panel-empty">
                    <strong>{{ $isPrivateElection ? 'Assign at least one candidate.' : 'No candidate applications yet.' }}</strong>
                    <p>{{ $isPrivateElection ? 'A private election needs an approved candidate before it can continue to hosting payment. Voters may be assigned later.' : 'Applications submitted during nominations will appear here for review.' }}</p>
                </div>
            @elseif ($canReviewCandidates)
                <form class="election-candidate-form" method="post" action="{{ route('elections.candidates.review', $election) }}">
                    @csrf
                    <input type="hidden" name="version" value="{{ $election->version }}">
                    <div class="election-table-wrap"><table class="election-table"><thead><tr><th>Candidate</th><th>Registered</th><th>Decision</th></tr></thead><tbody>
                    @foreach ($candidateSummary['applications'] as $application)
                        @php $candidateName = $application->user?->display_name ?? 'Candidate #'.$application->user_id; @endphp
                        <tr><td>{{ $candidateName }}</td><td>{{ \Carbon\CarbonImmutable::parse($application->registered_at)->format('d M Y, H:i') }}</td><td><select name="decisions[{{ $application->getKey() }}]">@foreach (['Pending', 'Approved', 'Rejected'] as $status)<option value="{{ $status }}" @selected(strcasecmp($application->status, $status) === 0)>{{ $status }}</option>@endforeach</select></td></tr>
                    @endforeach
                    </tbody></table></div>
                    <div class="election-candidate-actions"><label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label><button class="election-button election-button-primary" type="submit">Save candidate decisions</button></div>
                </form>
            @else
                <div class="election-table-wrap"><table class="election-table"><thead><tr><th>Candidate</th><th>Assigned</th><th>Status</th></tr></thead><tbody>
                @foreach ($candidateSummary['applications'] as $application)
                    @php $candidateName = $application->user?->display_name ?? 'Candidate #'.$application->user_id; @endphp
                    <tr><td>{{ $candidateName }}</td><td>{{ \Carbon\CarbonImmutable::parse($application->registered_at)->format('d M Y, H:i') }}</td><td><span class="election-status election-status-{{ strtolower($application->status) }}">{{ $application->status }}</span></td></tr>
                @endforeach
                </tbody></table></div>
            @endif
    </section>

    <section class="election-card election-workspace-panel election-audit-panel">
        <div class="election-panel-heading">
            <div><span class="election-eyebrow">Activity</span><h2>Election audit trail</h2><p>Every configuration and lifecycle change is recorded here.</p></div>
            <span class="election-audit-version">Version {{ $election->version }}</span>
        </div>
        @if ($logs === [])
            <div class="election-panel-empty"><strong>No lifecycle events recorded.</strong><p>The first election action will appear here.</p></div>
        @else
            <div class="election-table-wrap"><table class="election-table"><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Transition</th></tr></thead><tbody>
            @foreach ($logs as $log)
                @php $actor = $log->actor(); @endphp
                <tr><td>{{ \Carbon\CarbonImmutable::parse($log->get('created_at'))->format('d M Y H:i') }}</td><td>{{ $actor?->name ?? $actor?->email ?? 'User #'.$log->get('actor_user_id') }}</td><td>{{ str($log->get('action'))->replace('_', ' ')->headline() }}</td><td><code>{{ $log->get('from_state') }}</code> &rarr; <code>{{ $log->get('to_state') }}</code></td></tr>
            @endforeach
            </tbody></table></div>
        @endif
    </section>
</div>
@endsection

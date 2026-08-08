{{-- PIC: Low Wen Kai - Election Management Module --}}

@extends('layouts.app')

@section('title', 'Your elections')

@section('content')
<div class="election-shell">
    <header class="election-page-header">
        <div>
            <p class="election-eyebrow">Organiser workspace</p>
            <h1>Your elections</h1>
            <p>Configure election periods, verify hosting payment, and track independent approval.</p>
        </div>
        <a class="election-button election-button-primary" href="{{ route('elections.create') }}">Create election</a>
    </header>

    @if ($elections->isEmpty())
        <section class="election-card election-empty">
            <h2>No elections yet</h2>
            <p>Create a draft to begin the payment and approval workflow.</p>
        </section>
    @else
        <section class="election-grid" aria-label="Election list">
            @foreach ($elections as $election)
                <article class="election-card">
                    <div class="election-card-meta">
                        <span class="election-status election-status-{{ strtolower(str_replace('_', '-', $election->status)) }}">
                            {{ str($election->status)->replace('_', ' ')->lower()->headline() }}
                        </span>
                        <span>#{{ $election->getKey() }}</span>
                    </div>
                    <h2>{{ $election->title }}</h2>
                    <p>{{ $election->description }}</p>
                    <dl class="election-facts">
                        <div><dt>Nominations</dt><dd>{{ \Carbon\CarbonImmutable::parse($election->nomination_start)->format('d M') }} - {{ \Carbon\CarbonImmutable::parse($election->nomination_end)->format('d M Y') }}</dd></div>
                        <div><dt>Voting</dt><dd>{{ \Carbon\CarbonImmutable::parse($election->voting_start)->format('d M') }} - {{ \Carbon\CarbonImmutable::parse($election->voting_end)->format('d M Y') }}</dd></div>
                    </dl>
                    <a class="election-link" href="{{ route('elections.show', $election) }}">Open management console &rarr;</a>
                </article>
            @endforeach
        </section>
        {{ $elections->links() }}
    @endif
</div>
@endsection

{{-- PIC: Goh Chun Wen — Voting Process module. --}}
@extends('layouts.app')

@section('title', 'Voting portal')

@section('content')
<section class="voting-page">
    <header class="page-heading">
        <p class="eyebrow">Secure voting</p>
        <h1>Choose an open election</h1>
        <p>Only elections that are open and approved for voting appear here. Each approved voter may submit one final choice.</p>
    </header>

    @if ($elections->isEmpty())
        <article class="card empty-state">
            <span class="empty-state__icon" aria-hidden="true">✓</span>
            <h2>No elections are open</h2>
            <p>Return when an organiser opens an approved election.</p>
        </article>
    @else
        <div class="card-grid">
            @foreach ($elections as $election)
                <article class="card election-card">
                    <div class="card__topline">
                        <span class="status status--open">Voting open</span>
                        <span class="muted">#{{ $election['id'] }}</span>
                    </div>
                    <h2>{{ $election['title'] }}</h2>
                    <p>{{ $election['description'] ?: 'No description supplied.' }}</p>
                    <dl class="detail-list">
                        <div>
                            <dt>Closes</dt>
                            <dd>
                                {{ data_get($election, 'votingPeriod.end')
                                    ? \Illuminate\Support\Carbon::parse(data_get($election, 'votingPeriod.end'))->format('d M Y, H:i')
                                    : 'Scheduled by organiser' }}
                            </dd>
                        </div>
                        <div><dt>Your status</dt><dd>{{ $election['hasVoted'] ? 'Vote recorded' : 'Ready to vote' }}</dd></div>
                    </dl>
                    @if ($election['hasVoted'])
                        <span class="status status--complete">Completed</span>
                    @else
                        <a class="button button--primary" href="{{ route('voting.ballot', $election['id']) }}">Open secure ballot</a>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection

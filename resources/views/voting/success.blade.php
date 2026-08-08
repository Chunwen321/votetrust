{{-- PIC: Goh Chun Wen — Voting Process module. --}}
@extends('layouts.app')

@section('title', 'Vote recorded')

@section('content')
<section class="voting-page voting-page--narrow">
    <article class="card confirmation-card">
        <span class="confirmation-mark" aria-hidden="true">✓</span>
        <p class="eyebrow">Submission complete</p>
        <h1>Vote recorded successfully</h1>
        <p>Your candidate selection is not shown here or returned by the voting-status API, protecting ballot privacy.</p>
        <dl class="receipt-grid">
            <div><dt>Receipt ID</dt><dd><code>VT-{{ str_pad((string) $vote->id, 8, '0', STR_PAD_LEFT) }}</code></dd></div>
            <div><dt>Recorded at</dt><dd>{{ $vote->cast_at->format('d M Y, H:i:s') }}</dd></div>
        </dl>
        <a class="button button--primary" href="{{ route('voting.index') }}">Return to voting portal</a>
    </article>
</section>
@endsection

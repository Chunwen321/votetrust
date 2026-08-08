{{-- PIC: Goh Chun Wen — Voting Process module. --}}
@extends('layouts.app')

@section('title', 'Unable to vote')

@section('content')
<section class="voting-page voting-page--narrow">
    <article class="card error-card">
        <span class="error-card__mark" aria-hidden="true">!</span>
        <h1>Unable to process the vote</h1>
        <p>{{ $message ?? 'The voting service is temporarily unavailable.' }}</p>
        <a class="button button--primary" href="{{ route('voting.index') }}">Return to voting portal</a>
    </article>
</section>
@endsection

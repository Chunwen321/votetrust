@extends('layouts.app')

@section('title', 'Page unavailable')

@section('content')
@php
    $returnUrl = auth()->check()
        ? route(auth()->user()->isAdmin() ? 'admin.dashboard' : 'elections.index')
        : route('home');
    $returnLabel = auth()->check()
        ? (auth()->user()->isAdmin() ? 'Return to dashboard' : 'Return to elections')
        : 'Return home';
@endphp

<section class="error-page" aria-labelledby="error-page-title">
    <article class="error-page__card">
        <div class="error-page__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" role="img">
                <path d="M12 3 5.5 5.8v5.4c0 4.2 2.7 7.9 6.5 9.8 3.8-1.9 6.5-5.6 6.5-9.8V5.8L12 3Z" />
                <path d="M9.5 11.5h5M12 9v5" />
            </svg>
        </div>
        <p class="error-page__code">Error 404</p>
        <h1 id="error-page-title">Page unavailable</h1>
        <p class="error-page__message">We couldn't find this page, or your account does not have permission to view it.</p>
        <p class="error-page__note">Protected voting records are only available to their owner.</p>
        <a class="button button--primary error-page__action" href="{{ $returnUrl }}">
            {{ $returnLabel }} <span aria-hidden="true">&rarr;</span>
        </a>
    </article>
</section>
@endsection

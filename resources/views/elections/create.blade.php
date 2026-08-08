{{-- PIC: Low Wen Kai - Election Management Module --}}

@extends('layouts.app')

@section('title', 'Create election')

@section('content')
<div class="election-shell election-form-shell">
    <a class="election-link" href="{{ route('elections.index') }}">&larr; All elections</a>
    <header class="election-page-header">
        <div><p class="election-eyebrow">New election</p><h1>Create a draft</h1><p>Dates and eligibility can be edited until the draft is submitted for payment.</p></div>
    </header>
    @include('elections._form', [
        'election' => null,
        'eligibility' => \App\ValueObjects\EligibilityRules::publicElection(),
        'formAction' => route('elections.store'),
        'submitLabel' => 'Create draft',
        'defaults' => $defaults,
    ])
</div>
@endsection

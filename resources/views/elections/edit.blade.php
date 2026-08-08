{{-- PIC: Low Wen Kai - Election Management Module --}}

@extends('layouts.app')

@section('title', 'Edit election')

@section('content')
<div class="election-shell election-form-shell">
    <a class="election-link" href="{{ route('elections.show', $election) }}">&larr; Election details</a>
    <header class="election-page-header">
        <div><p class="election-eyebrow">Draft #{{ $election->getKey() }}</p><h1>Edit election</h1><p>Only drafts can be changed.</p></div>
    </header>
    @include('elections._form', [
        'formAction' => route('elections.update', $election),
        'submitLabel' => 'Save configuration',
        'defaults' => [],
    ])
</div>
@endsection

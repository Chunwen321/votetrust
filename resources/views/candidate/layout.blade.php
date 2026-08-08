{{-- PIC: Wong Shun Bin — Candidate Registration module layout. --}}
@extends('layouts.app')

@section('content')
    <div class="candidate-module">
        <div class="candidate-subnav">
            <strong>Candidate Registration</strong>
            <nav aria-label="Candidate navigation">
                <a href="{{ route('candidate.applications.create') }}" @class(['is-active' => request()->routeIs('candidate.applications.create')])>Apply</a>
                <a href="{{ route('candidate.profile.show') }}" @class(['is-active' => request()->routeIs('candidate.profile.*')])>My profile</a>
            </nav>
        </div>

        @yield('candidate-content')
    </div>
@endsection

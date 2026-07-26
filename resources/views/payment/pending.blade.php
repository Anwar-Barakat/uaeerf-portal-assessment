@extends('payment.layout')

@section('title', 'Processing Payment')

@section('head')
    {{-- Bounded polling: the controller stops supplying a refresh URL once the
         attempt cap is reached, so this page cannot spin forever. --}}
    @if (($shouldRefresh ?? true) && !empty($refreshUrl))
        <meta http-equiv="refresh" content="3; url={{ $refreshUrl }}">
    @endif
@endsection

@section('content')
    <div class="flex flex-col items-center gap-4">
        <div class="flex size-16 items-center justify-center rounded-full bg-amber-500/15">
            @if ($shouldRefresh ?? true)
                <svg class="size-8 animate-spin text-amber-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            @else
                <svg class="size-8 text-amber-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            @endif
        </div>
        <div class="space-y-1">
            <h1 class="text-2xl font-bold tracking-tight">
                {{ ($shouldRefresh ?? true) ? 'Processing Payment' : 'Still Processing' }}
            </h1>
            <p class="text-sm text-muted-foreground">
                @if ($shouldRefresh ?? true)
                    {{ $message ?? 'Payment processing... Please wait.' }}
                @else
                    This is taking longer than usual. Your payment is safe &mdash; the confirmation is still being
                    processed. Check your dashboard shortly, or contact support if it does not appear.
                @endif
            </p>
        </div>
    </div>

    @if ($shouldRefresh ?? true)
        <p class="text-xs text-muted-foreground">This page will refresh automatically&hellip;</p>
    @endif

    <a href="{{ route('dashboard') }}"
       class="inline-flex w-full items-center justify-center rounded-md border border-border bg-background px-6 py-2.5 text-sm font-medium shadow-sm transition-colors hover:bg-muted">
        Back to Dashboard
    </a>
@endsection

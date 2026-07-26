@extends('payment.layout')

@section('title', 'Action Required')

@section('content')
    {{-- The payment succeeded but the federation rejected the submission. Never
         present this as success: the customer has been charged and is not
         registered. --}}
    <div class="flex flex-col items-center gap-4">
        <div class="flex size-16 items-center justify-center rounded-full bg-amber-500/15">
            <svg class="size-8 text-amber-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path>
            </svg>
        </div>
        <div class="space-y-1">
            <h1 class="text-2xl font-bold tracking-tight">Payment Received &mdash; {{ $fulfilment['type'] }} Incomplete</h1>
            <p class="text-sm text-muted-foreground">
                We have taken your payment, but the federation could not complete your
                {{ strtolower($fulfilment['type']) }}. Your payment has been recorded and will not be taken again.
                Please contact support quoting the reference below so it can be completed or refunded.
            </p>
            @if ($supportEmail)
                <p class="text-sm">
                    <a class="font-medium underline underline-offset-4" href="mailto:{{ $supportEmail }}?subject={{ urlencode('Payment ' . $transaction->tran_ref) }}">
                        {{ $supportEmail }}
                    </a>
                </p>
            @endif
        </div>
    </div>

    <div class="rounded-lg border border-border/60 bg-muted/40 p-4 text-left">
        <dl class="divide-y divide-border/60 text-sm">
            <div class="flex items-center justify-between gap-4 py-2">
                <dt class="text-muted-foreground">Transaction Reference</dt>
                <dd class="font-mono text-xs font-medium">{{ $transaction->tran_ref }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-2">
                <dt class="text-muted-foreground">Amount</dt>
                <dd class="font-semibold">{{ $transaction->currency }} {{ number_format($transaction->amount, 2) }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-2">
                <dt class="text-muted-foreground">Payment</dt>
                <dd>
                    <span class="inline-flex items-center rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        Captured
                    </span>
                </dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-2">
                <dt class="text-muted-foreground">{{ $fulfilment['type'] }}</dt>
                <dd>
                    <span class="inline-flex items-center rounded-full bg-amber-500/15 px-2.5 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                        Needs review
                    </span>
                </dd>
            </div>
            @if (!empty($fulfilment['error']))
                <div class="flex items-start justify-between gap-4 py-2">
                    <dt class="shrink-0 text-muted-foreground">Reason</dt>
                    <dd class="max-w-[16rem] text-right font-medium">{{ $fulfilment['error'] }}</dd>
                </div>
            @endif
        </dl>
    </div>

    <a href="{{ route('dashboard') }}"
       class="inline-flex w-full items-center justify-center rounded-md bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90">
        Go to Dashboard
    </a>
@endsection

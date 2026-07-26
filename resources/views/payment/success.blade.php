@extends('payment.layout')

@section('title', 'Payment Successful')

@section('content')
    <div class="flex flex-col items-center gap-4">
        <div class="flex size-16 items-center justify-center rounded-full bg-emerald-500/15">
            <svg class="size-8 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <div class="space-y-1">
            <h1 class="text-2xl font-bold tracking-tight">{{ $fulfilment['type'] }} Confirmed</h1>
            <p class="text-sm text-muted-foreground">
                Your payment went through and your {{ strtolower($fulfilment['type']) }} has been submitted to the
                federation successfully. You will receive a confirmation email shortly.
            </p>
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
                    <span class="inline-flex items-center rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        Completed
                    </span>
                </dd>
            </div>
        </dl>
    </div>

    <a href="{{ route('dashboard') }}"
       class="inline-flex w-full items-center justify-center rounded-md bg-primary px-6 py-2.5 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90">
        Go to Dashboard
    </a>
@endsection

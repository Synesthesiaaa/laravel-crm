<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>{{ $completed ? 'Unsubscribed' : 'Unsubscribe from marketing email' }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-[var(--color-surface)] text-[var(--color-on-surface)]">
<main class="mx-auto max-w-xl px-6 py-16">
    <h1 class="text-2xl font-semibold">{{ $completed ? 'You have been unsubscribed' : 'Unsubscribe from marketing email' }}</h1>
    @if($completed)
        <p class="mt-4 text-sm leading-6">You will no longer receive marketing campaigns from this CRM. Authorized transactional notices follow a separate policy.</p>
    @else
        <p class="mt-4 text-sm leading-6">Confirm to stop future marketing campaigns. This does not change authorized transactional notices.</p>
        <form method="post" action="{{ route('email-tracking.opt-out', ['token' => $token]) }}" class="mt-6">
            <input type="hidden" name="List-Unsubscribe" value="One-Click">
            <button type="submit" class="btn-primary">Unsubscribe</button>
        </form>
    @endif
</main>
</body>
</html>

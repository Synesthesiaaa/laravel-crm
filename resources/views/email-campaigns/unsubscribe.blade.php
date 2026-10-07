<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribe from emails</title>
</head>
<body style="margin:3rem auto;max-width:32rem;padding:0 1.5rem;font-family:system-ui,sans-serif;color:#20242a">
    <h1>Unsubscribe from emails</h1>
    <p>Stop receiving future campaign emails at <strong>{{ $recipient->email }}</strong>?</p>
    <form method="POST" action="{{ request()->fullUrl() }}">
        @csrf
        <button type="submit" style="padding:.7rem 1rem;border:0;border-radius:.4rem;background:#222;color:white;cursor:pointer">Unsubscribe</button>
    </form>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Authorize MCP access · CVortex</title>
    <style>
        body { font: 16px system-ui, sans-serif; color: #17202a; background: #f6f8fa; margin: 0; }
        main { max-width: 30rem; margin: 8vh auto; padding: 2rem; background: white; border: 1px solid #d8dee4; border-radius: 12px; }
        h1 { font-size: 1.5rem; margin-top: 0; }
        .actions { display: flex; gap: 1rem; margin-top: 1.5rem; }
        button { padding: .7rem 1rem; cursor: pointer; border-radius: 6px; border: 1px solid #8c959f; background: white; }
        .approve { color: white; background: #0969da; border-color: #0969da; }
    </style>
</head>
<body>
<main>
    <h1>Authorize {{ $client->name }}?</h1>
    <p>Signed in as {{ $user->email }}.</p>
    <p>This client can read bounded CVortex vacancy and career context and submit application drafts for review. It cannot approve or send applications or confirm Career Facts.</p>
    <ul>
        @foreach ($scopes as $scope)
            <li>{{ $scope->description }}</li>
        @endforeach
    </ul>
    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit">Deny</button>
        </form>
        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="state" value="{{ $request->state }}">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button class="approve" type="submit">Authorize</button>
        </form>
    </div>
</main>
</body>
</html>

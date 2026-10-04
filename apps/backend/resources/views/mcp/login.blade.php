<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Вход для подключения · CVortex</title>
    <style>
        body { font: 16px system-ui, sans-serif; color: #17202a; background: #f6f8fa; margin: 0; }
        main { max-width: 30rem; margin: 8vh auto; padding: 2rem; background: white; border: 1px solid #d8dee4; border-radius: 12px; }
        h1 { font-size: 1.5rem; margin-top: 0; }
        li { margin-block: 1rem; }
        a { color: #0969da; }
    </style>
</head>
<body>
<main>
    <h1>Войдите в CVortex</h1>
    <p>Чтобы подключить приложение, сначала войдите в свой аккаунт CVortex.</p>
    <ol>
        <li><a href="{{ $loginUrl }}" target="_blank" rel="noopener noreferrer">Открыть CVortex и войти</a> в новой вкладке.</li>
        <li>После входа вернитесь сюда и <a href="{{ $authorizationUrl }}">продолжите подключение</a>.</li>
    </ol>
    <p>На следующем экране будут показаны запрошенные разрешения: доступ только для чтения и, при наличии отдельного разрешения, создание неутверждённых vacancy-analysis drafts. Вы сможете разрешить или отклонить доступ. Вход сам по себе не предоставляет приложению доступ.</p>
</main>
</body>
</html>

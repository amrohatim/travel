<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $parentCompany->name }} | Safriat</title>
    <style>
        :root {
            --bg: #f6f3ee;
            --panel: #ffffff;
            --text: #161616;
            --muted: #6e6e6e;
            --line: #ded7cb;
            --accent: #fa9746;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                radial-gradient(circle at top left, #ffe1c5 0, transparent 35%),
                radial-gradient(circle at bottom right, #f8cbb1 0, transparent 30%),
                var(--bg);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }
        .card {
            width: min(100%, 520px);
            background: var(--panel);
            border: 1px solid var(--line);
            padding: 28px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.08);
        }
        .eyebrow {
            font-size: 12px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 12px;
        }
        .hero {
            display: flex;
            gap: 16px;
            align-items: center;
            margin-bottom: 20px;
        }
        .hero img,
        .hero .placeholder {
            width: 96px;
            height: 96px;
            object-fit: cover;
            border: 1px solid var(--line);
            background: #f4f4f4;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 30px;
            line-height: 1.1;
        }
        p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 18px;
            text-decoration: none;
            border: 1px solid var(--text);
            color: var(--text);
            font-weight: 700;
        }
        .btn-primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #111;
        }
        .hint {
            margin-top: 18px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="eyebrow">Safriat Company Link</div>
        <div class="hero">
            @if ($parentCompany->imageUrl())
                <img src="{{ $parentCompany->imageUrl() }}" alt="{{ $parentCompany->name }}">
            @else
                <div class="placeholder"></div>
            @endif
            <div>
                <h1>{{ $parentCompany->name }}</h1>
                <p>Open this company in the Safriat app to browse offices and continue to available flights.</p>
            </div>
        </div>

        <div class="actions">
            <a class="btn btn-primary" href="{{ $appDeepLinkUrl }}">Open In App</a>
            <a class="btn" href="{{ $androidStoreUrl }}">Get The App</a>
            <a class="btn" href="{{ url('/') }}">Visit Website</a>
        </div>

        <p class="hint">If the app does not open automatically on Android, Google Play will open shortly. Other devices will stay on this page, where you can use "Open In App" manually.</p>
    </div>

    <script>
        (function () {
            var appDeepLinkUrl = @json($appDeepLinkUrl);
            var androidStoreUrl = @json($androidStoreUrl);
            var isAndroid = /Android/i.test(window.navigator.userAgent || '');
            var didHide = false;

            function markHidden() {
                didHide = true;
            }

            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'hidden') {
                    markHidden();
                }
            });

            window.addEventListener('pagehide', markHidden);
            window.addEventListener('blur', markHidden);

            window.setTimeout(function () {
                window.location.href = appDeepLinkUrl;
            }, 350);

            if (!isAndroid) {
                return;
            }

            window.setTimeout(function () {
                if (!didHide && document.visibilityState === 'visible') {
                    window.location.href = androidStoreUrl;
                }
            }, 1600);
        })();
    </script>
</body>
</html>

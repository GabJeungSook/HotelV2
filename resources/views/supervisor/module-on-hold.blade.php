<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Supervisor Module · On Hold · HOMI</title>
    <link rel="icon" type="image/png" href="/images/homiLogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Alkatra:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --brand: #009EF5;
            --brand-soft: #E6F5FE;
            --brand-deep: #0078BB;
            --ink: #1f2937;
            --ink-soft: #4b5563;
            --line: #e5e7eb;
        }

        html, body {
            height: 100%;
            background: #ffffff;
            color: var(--ink);
            font-family: 'DM Sans', system-ui, -apple-system, sans-serif;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        .bg-grid {
            position: fixed; inset: 0; z-index: 0;
            background-image:
                linear-gradient(to right, rgba(17,24,39,0.06) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(17,24,39,0.06) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 32rem 32rem at center, black 40%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse 32rem 32rem at center, black 40%, transparent 75%);
        }

        .polygon {
            position: fixed; z-index: 0;
            width: 50rem; height: 50rem;
            filter: blur(64px); opacity: 0.32;
            background: linear-gradient(to top right, var(--brand), #d1d5db);
            clip-path: polygon(63.1% 29.5%, 100% 17.1%, 76.6% 3%, 48.4% 0%, 44.6% 4.7%, 54.5% 25.3%, 59.8% 49%, 55.2% 57.8%, 44.4% 57.2%, 27.8% 47.9%, 35.1% 81.5%, 0% 97.7%, 39.2% 100%, 35.2% 81.4%, 97.2% 52.8%, 63.1% 29.5%);
            pointer-events: none;
        }
        .polygon.left  { top: -10rem; left: -16rem; animation: floatA 18s ease-in-out infinite; }
        .polygon.right { top: -8rem;  right: -16rem; transform: scaleX(-1); animation: floatB 22s ease-in-out infinite; }
        @keyframes floatA { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(40px, 30px); } }
        @keyframes floatB { 0%, 100% { transform: translate(0, 0) scaleX(-1); } 50% { transform: translate(-40px, 30px) scaleX(-1); } }

        .stage {
            position: relative; z-index: 2;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
        }

        .card {
            width: min(560px, 100%);
            padding: 56px 40px 36px;
            background: #fff;
            border-radius: 24px;
            border: 1px solid var(--line);
            box-shadow:
                0 1px 0 rgba(255,255,255,0.6) inset,
                0 30px 60px -20px rgba(0, 158, 245, 0.18),
                0 12px 24px -12px rgba(17, 24, 39, 0.10);
            text-align: center;
            animation: cardIn 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(28px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }

        .icon-wrap {
            width: 80px; height: 80px;
            margin: 0 auto 24px;
            border-radius: 50%;
            background: var(--brand-soft);
            display: flex; align-items: center; justify-content: center;
        }
        .icon-wrap svg { width: 40px; height: 40px; color: var(--brand); }

        .eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 14px; border-radius: 999px;
            background: var(--brand-soft);
            border: 1px solid rgba(0, 158, 245, 0.2);
            color: var(--brand-deep);
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px; font-weight: 500; letter-spacing: 2px; text-transform: uppercase;
            margin-bottom: 18px;
        }
        .eyebrow .dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--brand);
            box-shadow: 0 0 8px var(--brand);
            animation: blink 1.4s ease-in-out infinite;
        }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

        h1 {
            font-family: 'Alkatra', 'DM Sans', sans-serif;
            font-size: clamp(26px, 4vw, 36px);
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.2;
            margin-bottom: 14px;
            color: var(--ink);
        }
        h1 .accent {
            background: linear-gradient(120deg, var(--brand), var(--brand-deep));
            -webkit-background-clip: text;
                    background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: var(--ink-soft);
            font-size: 15px; line-height: 1.6;
            max-width: 420px;
            margin: 0 auto 32px;
        }

        .btn-logout {
            display: inline-flex; align-items: center; gap: 12px;
            padding: 14px 28px;
            border-radius: 12px;
            background: var(--brand);
            color: #fff;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: 0.02em;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow:
                0 10px 28px -8px rgba(0, 158, 245, 0.55),
                0 2px 6px rgba(0, 158, 245, 0.18);
            transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
            font-family: inherit;
        }
        .btn-logout:hover {
            transform: translateY(-2px);
            background: var(--brand-deep);
            box-shadow:
                0 16px 36px -8px rgba(0, 158, 245, 0.72),
                0 4px 10px rgba(0, 158, 245, 0.25);
        }
        .btn-logout svg { transition: transform 0.25s ease; }
        .btn-logout:hover svg { transform: translateX(4px); }

        .footer {
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid var(--line);
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: #9ca3af;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .footer .brand {
            color: var(--brand-deep);
            font-weight: 500;
            font-family: 'Alkatra', sans-serif;
            letter-spacing: 0;
            text-transform: none;
            font-size: 13px;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; }
        }
    </style>
</head>
<body>

    <div class="bg-grid"></div>
    <div class="polygon left"></div>
    <div class="polygon right"></div>

    <main class="stage">
        <section class="card">

            <div class="icon-wrap" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                </svg>
            </div>

            <div class="eyebrow">
                <span class="dot"></span>
                <span>Module on Hold</span>
            </div>

            <h1>Supervisor Module is <span class="accent">Paused</span></h1>

            <p class="subtitle">
                The supervisor approval workflow is temporarily on hold per administrative decision.
                Frontdesk staff are processing transfers and cancellations directly. This module can
                be re-activated at any time without data loss.
            </p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-logout">
                    <span>Log Out</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                         stroke="currentColor" width="18" height="18">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                </button>
            </form>

            <div class="footer">
                <span class="brand">HOMI</span>
                &middot;
                <span>Hotel Management System</span>
            </div>

        </section>
    </main>

</body>
</html>

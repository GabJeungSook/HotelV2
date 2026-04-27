<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Error') · HOMI</title>
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
            --brand-cyan: #06b6d4;
            --ink: #1f2937;
            --ink-soft: #4b5563;
            --ink-mute: #9ca3af;
            --line: #e5e7eb;
            --bg: #ffffff;
            --card: #ffffff;
        }

        html, body {
            height: 100%;
            background: var(--bg);
            color: var(--ink);
            font-family: 'DM Sans', system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ---------- Background: dotted grid + drifting polygons ---------- */
        .bg-grid {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image:
                linear-gradient(to right, rgba(17,24,39,0.06) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(17,24,39,0.06) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 32rem 32rem at center, black 40%, transparent 75%);
            -webkit-mask-image: radial-gradient(ellipse 32rem 32rem at center, black 40%, transparent 75%);
        }

        .polygon {
            position: fixed;
            z-index: 0;
            width: 50rem;
            height: 50rem;
            filter: blur(64px);
            opacity: 0.32;
            background: linear-gradient(to top right, var(--brand), #d1d5db);
            clip-path: polygon(63.1% 29.5%, 100% 17.1%, 76.6% 3%, 48.4% 0%, 44.6% 4.7%, 54.5% 25.3%, 59.8% 49%, 55.2% 57.8%, 44.4% 57.2%, 27.8% 47.9%, 35.1% 81.5%, 0% 97.7%, 39.2% 100%, 35.2% 81.4%, 97.2% 52.8%, 63.1% 29.5%);
            pointer-events: none;
            will-change: transform;
        }
        .polygon.left  { top: -10rem; left: -16rem; animation: floatA 18s ease-in-out infinite; }
        .polygon.right { top: -8rem;  right: -16rem; transform: scaleX(-1); animation: floatB 22s ease-in-out infinite; }

        @keyframes floatA {
            0%, 100% { transform: translate(0, 0); }
            50%      { transform: translate(40px, 30px); }
        }
        @keyframes floatB {
            0%, 100% { transform: translate(0, 0) scaleX(-1); }
            50%      { transform: translate(-40px, 30px) scaleX(-1); }
        }

        /* ---------- Floating particles ---------- */
        .particles { position: fixed; inset: 0; z-index: 1; pointer-events: none; }
        .particle {
            position: absolute;
            width: 4px; height: 4px;
            border-radius: 50%;
            background: var(--brand);
            opacity: 0;
            box-shadow: 0 0 8px rgba(0,158,245,0.6);
            animation: rise 14s linear infinite;
        }
        @keyframes rise {
            0%   { transform: translateY(110vh) scale(0); opacity: 0; }
            10%  { opacity: 0.55; }
            90%  { opacity: 0.55; }
            100% { transform: translateY(-10vh) scale(1.3); opacity: 0; }
        }

        /* ---------- Stage ---------- */
        .stage {
            position: relative;
            z-index: 2;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            position: relative;
            width: min(580px, 100%);
            padding: 56px 40px 36px;
            background: var(--card);
            border-radius: 28px;
            box-shadow:
                0 1px 0 rgba(255,255,255,0.6) inset,
                0 30px 60px -20px rgba(0, 158, 245, 0.22),
                0 12px 24px -12px rgba(17, 24, 39, 0.10);
            text-align: center;
            animation: cardIn 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(28px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }

        /* Animated conic gradient border */
        .card::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 30px;
            padding: 2px;
            background: conic-gradient(
                from 0deg,
                var(--brand) 0%,
                var(--brand-cyan) 25%,
                var(--brand-deep) 50%,
                var(--brand-cyan) 75%,
                var(--brand) 100%
            );
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
                    mask-composite: exclude;
            animation: rotateBorder 8s linear infinite;
            z-index: -1;
            opacity: 0.7;
        }
        @keyframes rotateBorder {
            to { transform: rotate(360deg); }
        }

        /* ---------- Status code with rings + glitch ---------- */
        .code-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            padding: 24px 8px;
        }
        .code-wrap::before,
        .code-wrap::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            border: 1.5px solid rgba(0, 158, 245, 0.18);
            pointer-events: none;
        }
        .code-wrap::before {
            width: 280px; height: 280px;
            animation: pulseRing 3s ease-out infinite;
        }
        .code-wrap::after {
            width: 200px; height: 200px;
            animation: pulseRing 3s ease-out infinite 1s;
        }
        @keyframes pulseRing {
            0%   { transform: scale(0.7); opacity: 0.9; }
            100% { transform: scale(1.4); opacity: 0; }
        }

        .code {
            position: relative;
            font-family: 'Alkatra', 'DM Sans', sans-serif;
            font-weight: 700;
            font-size: clamp(96px, 16vw, 180px);
            line-height: 1;
            letter-spacing: -0.05em;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-deep) 60%, var(--brand-cyan) 100%);
            background-size: 200% 200%;
            -webkit-background-clip: text;
                    background-clip: text;
            -webkit-text-fill-color: transparent;
            user-select: none;
            animation: shimmerCode 5s ease-in-out infinite;
            text-shadow: 0 0 40px rgba(0, 158, 245, 0.15);
        }
        @keyframes shimmerCode {
            0%, 100% { background-position: 0% 50%; }
            50%      { background-position: 100% 50%; }
        }

        /* Subtle "RGB-split" glitch overlay */
        .code::before,
        .code::after {
            content: attr(data-code);
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            -webkit-background-clip: text;
                    background-clip: text;
            -webkit-text-fill-color: transparent;
            mix-blend-mode: screen;
            opacity: 0;
        }
        .code::before {
            background: linear-gradient(135deg, #ec4899, #ec4899);
            -webkit-background-clip: text;
                    background-clip: text;
            animation: glitch1 6s steps(1) infinite;
        }
        .code::after {
            background: linear-gradient(135deg, var(--brand-cyan), var(--brand-cyan));
            -webkit-background-clip: text;
                    background-clip: text;
            animation: glitch2 6s steps(1) infinite;
        }
        @keyframes glitch1 {
            0%, 96%   { opacity: 0; transform: translate(0, 0); }
            97%       { opacity: 0.55; transform: translate(-3px, 1px); }
            98%       { opacity: 0; }
            99%       { opacity: 0.55; transform: translate(2px, -2px); }
            100%      { opacity: 0; }
        }
        @keyframes glitch2 {
            0%, 96%   { opacity: 0; transform: translate(0, 0); }
            97%       { opacity: 0.55; transform: translate(3px, -1px); }
            98%       { opacity: 0; }
            99%       { opacity: 0.55; transform: translate(-2px, 2px); }
            100%      { opacity: 0; }
        }

        /* ---------- Eyebrow chip ---------- */
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            background: var(--brand-soft);
            border: 1px solid rgba(0, 158, 245, 0.2);
            color: var(--brand-deep);
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }
        .eyebrow .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--brand);
            box-shadow: 0 0 8px var(--brand);
            animation: blink 1.4s ease-in-out infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0.3; }
        }

        h1 {
            font-family: 'Alkatra', 'DM Sans', sans-serif;
            font-size: clamp(24px, 3.5vw, 32px);
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.2;
            margin-bottom: 12px;
            color: var(--ink);
        }

        .subtitle {
            color: var(--ink-soft);
            font-size: 15px;
            line-height: 1.6;
            max-width: 380px;
            margin: 0 auto 28px;
        }

        /* ---------- Button ---------- */
        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 14px 28px;
            border-radius: 12px;
            background: var(--brand);
            color: #fff;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: 0.02em;
            text-decoration: none;
            box-shadow:
                0 10px 28px -8px rgba(0, 158, 245, 0.55),
                0 2px 6px rgba(0, 158, 245, 0.18);
            transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-home::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }
        .btn-home:hover::before {
            left: 100%;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            background: var(--brand-deep);
            box-shadow:
                0 16px 36px -8px rgba(0, 158, 245, 0.72),
                0 4px 10px rgba(0, 158, 245, 0.25);
        }
        .btn-home svg {
            transition: transform 0.25s ease;
            position: relative;
            z-index: 1;
        }
        .btn-home:hover svg {
            transform: translateX(4px);
        }
        .btn-home span { position: relative; z-index: 1; }

        /* ---------- Footer ---------- */
        .footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--ink-mute);
            letter-spacing: 1px;
            text-transform: uppercase;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        .footer .brand {
            color: var(--brand-deep);
            font-weight: 500;
            font-family: 'Alkatra', sans-serif;
            letter-spacing: 0;
            text-transform: none;
            font-size: 13px;
        }

        @media (max-width: 480px) {
            .card { padding: 40px 24px 24px; }
            .code-wrap::before { width: 220px; height: 220px; }
            .code-wrap::after  { width: 160px; height: 160px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>
</head>
<body>

    <div class="bg-grid"></div>
    <div class="polygon left"></div>
    <div class="polygon right"></div>
    <div class="particles" id="particles"></div>

    <main class="stage">
        <section class="card">

            <div class="code-wrap" aria-hidden="true">
                <div class="code" data-code="@yield('code', 'OOPS')">@yield('code', 'OOPS')</div>
            </div>

            <div class="eyebrow">
                <span class="dot"></span>
                <span>@yield('message', 'Something went wrong')</span>
            </div>

            <h1>@yield('title', 'Something went wrong')</h1>

            <p class="subtitle">
                @yield('description', "We hit an unexpected snag. Try heading back, or refreshing the page.")
            </p>

            <a href="/" class="btn-home">
                <span>Back to Home</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                     stroke="currentColor" width="18" height="18">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
            </a>

            <div class="footer">
                <span class="brand">HOMI</span>
                <span>·</span>
                <span>Hotel Management System</span>
            </div>

        </section>
    </main>

    <script>
        // Spawn brand-blue particles
        (function () {
            const layer = document.getElementById('particles');
            const count = 24;
            for (let i = 0; i < count; i++) {
                const p = document.createElement('span');
                p.className = 'particle';
                p.style.left = Math.random() * 100 + 'vw';
                p.style.animationDelay = (Math.random() * 14) + 's';
                p.style.animationDuration = (10 + Math.random() * 10) + 's';
                layer.appendChild(p);
            }
        })();
    </script>
</body>
</html>

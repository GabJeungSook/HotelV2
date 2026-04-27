<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page Not Found · HOMI</title>
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

        /* ---------- Background: dotted grid + drifting polygon (login DNA) ---------- */
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
            width: min(560px, 100%);
            padding: 56px 40px 36px;
            background: var(--card);
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

        /* ---------- Giant 404 with HOMI logo as the "0" ---------- */
        .four-oh-four {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 8px;
            line-height: 1;
            user-select: none;
        }
        .four-oh-four .digit {
            font-family: 'Alkatra', 'DM Sans', sans-serif;
            font-weight: 700;
            font-size: clamp(96px, 16vw, 168px);
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-deep) 100%);
            -webkit-background-clip: text;
                    background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.05em;
        }
        .four-oh-four .logo-zero {
            position: relative;
            width: clamp(96px, 16vw, 168px);
            height: clamp(96px, 16vw, 168px);
            border-radius: 50%;
            background: #fff;
            border: 6px solid;
            border-image: linear-gradient(135deg, var(--brand), var(--brand-deep)) 1;
            border-image-slice: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow:
                inset 0 0 0 4px var(--brand-soft),
                0 6px 18px -6px rgba(0, 158, 245, 0.35);
            animation: spinSlow 18s linear infinite;
        }
        /* Fallback because border-image + border-radius doesn't always render perfectly */
        .four-oh-four .logo-zero {
            border: none;
            background:
                linear-gradient(#fff, #fff) padding-box,
                linear-gradient(135deg, var(--brand), var(--brand-deep)) border-box;
            border: 6px solid transparent;
        }
        .four-oh-four .logo-zero img {
            width: 65%;
            height: auto;
            animation: spinSlowReverse 18s linear infinite;
        }
        @keyframes spinSlow        { to { transform: rotate(360deg); } }
        @keyframes spinSlowReverse { to { transform: rotate(-360deg); } }

        /* ---------- Type ---------- */
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
        h1 .accent {
            background: linear-gradient(120deg, var(--brand) 0%, var(--brand-deep) 60%, var(--brand) 100%);
            background-size: 200% 200%;
            -webkit-background-clip: text;
                    background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmerText 5s ease-in-out infinite;
        }
        @keyframes shimmerText {
            0%, 100% { background-position: 0% 50%; }
            50%      { background-position: 100% 50%; }
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
        }
        .btn-home:hover svg {
            transform: translateX(4px);
        }

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

    <main class="stage">
        <section class="card">

            <div class="four-oh-four" aria-label="404">
                <span class="digit">4</span>
                <span class="logo-zero">
                    <img src="/images/homiLogo.png" alt="HOMI"
                         onerror="this.style.display='none'; this.parentNode.style.fontFamily='Alkatra'; this.parentNode.style.fontWeight='700'; this.parentNode.style.fontSize='clamp(56px, 9vw, 96px)'; this.parentNode.style.color='#009EF5'; this.parentNode.innerHTML='0';">
                </span>
                <span class="digit">4</span>
            </div>

            <div class="eyebrow">
                <span class="dot"></span>
                <span>Page Not Found</span>
            </div>

            <h1>Lost in the <span class="accent">Hallway</span>?</h1>

            <p class="subtitle">
                We can't find the page you're looking for. It may have been moved,
                or you might have followed an old link. Let's get you back to the front desk.
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
                <span style="align-self: center">·</span>
                <span style="align-self: center">Hotel Management System</span>
            </div>

        </section>
    </main>

</body>
</html>

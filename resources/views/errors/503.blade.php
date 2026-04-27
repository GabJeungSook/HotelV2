<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <title>System Updating · HOMI</title>
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

        /* ---------- Background: dotted grid, polygon blobs (login-page DNA) ---------- */
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
            opacity: 0.35;
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

        /* ---------- Floating particles (subtle, brand colored) ---------- */
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
            width: min(560px, 100%);
            padding: 48px 40px 32px;
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

        /* ---------- Reactor: HOMI logo with rotating brand rings ---------- */
        .reactor {
            position: relative;
            width: 140px; height: 140px;
            margin: 0 auto 28px;
        }
        .ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 1.5px dashed rgba(0, 158, 245, 0.35);
        }
        .ring.r2 { inset: 12px; border-style: solid; border-color: rgba(0, 158, 245, 0.18); }
        .ring.r3 { inset: 24px; border-style: dashed; border-color: rgba(0, 158, 245, 0.22); }

        .ring.spin1 { animation: spin 14s linear infinite; }
        .ring.spin2 { animation: spin  9s linear infinite reverse; }
        .ring.spin3 { animation: spin  6s linear infinite; }

        .ring::before {
            content: '';
            position: absolute;
            top: -4px; left: 50%;
            width: 8px; height: 8px;
            margin-left: -4px;
            border-radius: 50%;
            background: var(--brand);
            box-shadow: 0 0 12px var(--brand);
        }

        .core {
            position: absolute;
            inset: 36px;
            border-radius: 50%;
            background: #ffffff;
            border: 1px solid var(--line);
            box-shadow:
                inset 0 0 0 4px var(--brand-soft),
                0 6px 18px -6px rgba(0, 158, 245, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            animation: coreBreathe 3s ease-in-out infinite;
        }
        .core img { width: 70%; height: auto; display: block; }

        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes coreBreathe {
            0%, 100% { box-shadow: inset 0 0 0 4px var(--brand-soft), 0 6px 18px -6px rgba(0,158,245,0.45); }
            50%      { box-shadow: inset 0 0 0 6px var(--brand-soft), 0 10px 26px -6px rgba(0,158,245,0.65); }
        }

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
            margin-bottom: 20px;
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
            font-size: clamp(30px, 4.5vw, 42px);
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.15;
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
            max-width: 400px;
            margin: 0 auto 28px;
        }

        /* ---------- Progress ---------- */
        .progress {
            position: relative;
            height: 6px;
            border-radius: 999px;
            background: #f1f5f9;
            overflow: hidden;
            margin-bottom: 22px;
        }
        .progress::before {
            content: '';
            position: absolute;
            top: 0; left: -40%;
            width: 40%;
            height: 100%;
            background: linear-gradient(90deg, transparent, var(--brand) 30%, var(--brand-deep) 70%, transparent);
            border-radius: 999px;
            box-shadow: 0 0 16px rgba(0, 158, 245, 0.55);
            animation: slide 1.8s ease-in-out infinite;
        }
        @keyframes slide {
            0%   { left: -40%; }
            100% { left: 100%; }
        }

        /* ---------- Status ---------- */
        .status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            color: var(--ink-soft);
        }
        .status .live {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--brand);
            box-shadow: 0 0 0 0 rgba(0, 158, 245, 0.6);
            animation: live 1.6s ease-out infinite;
        }
        @keyframes live {
            0%   { box-shadow: 0 0 0 0 rgba(0, 158, 245, 0.6); }
            70%  { box-shadow: 0 0 0 10px rgba(0, 158, 245, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 158, 245, 0); }
        }
        .dots::after {
            content: '';
            display: inline-block;
            width: 1.2em;
            text-align: left;
            animation: dots 1.6s steps(4, end) infinite;
        }
        @keyframes dots {
            0%   { content: ''; }
            25%  { content: '.'; }
            50%  { content: '..'; }
            75%  { content: '...'; }
            100% { content: ''; }
        }

        /* ---------- Footer ---------- */
        .footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--ink-mute);
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .footer .countdown { color: var(--brand-deep); font-weight: 500; }
        .footer .brand     { color: var(--brand-deep); font-weight: 500; font-family: 'Alkatra', sans-serif; letter-spacing: 0; text-transform: none; font-size: 13px; }

        @media (max-width: 480px) {
            .card { padding: 36px 24px 24px; }
            .footer { flex-direction: column; gap: 8px; }
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

            <div class="reactor" aria-hidden="true">
                <div class="ring r1 spin1"></div>
                <div class="ring r2 spin2"></div>
                <div class="ring r3 spin3"></div>
                <div class="core">
                    <img src="/images/homiLogo.png" alt="HOMI" onerror="this.style.display='none'; this.parentNode.innerHTML='<span style=&quot;font-family:Alkatra;font-weight:700;font-size:22px;color:#009EF5&quot;>HOMI</span>'">
                </div>
            </div>

            <div class="eyebrow">
                <span class="dot"></span>
                <span>Maintenance Mode</span>
            </div>

            <h1>System is <span class="accent">Updating</span></h1>

            <p class="subtitle">
                We're rolling out improvements to make HOMI faster and smoother.
                Hang tight — we'll be back online shortly.
            </p>

            <div class="progress" aria-hidden="true"></div>

            <div class="status">
                <span class="live"></span>
                <span>Deploying updates</span>
                <span class="dots"></span>
            </div>

            <div class="footer">
                <span class="brand">HOMI</span>
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

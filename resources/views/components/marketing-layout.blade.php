<!DOCTYPE html>
<html lang="en" style="overflow-x: hidden">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Veloquent - The Open Source Laravel Backend' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #ffffff;
            background-image: radial-gradient(circle at 1px 1px, rgba(0, 0, 0, 0.08) 1.5px, transparent 0);
            background-size: 30px 30px;
            color: #000;
            overflow-x: hidden;
            width: 100%;
            position: relative;
        }

        .brutalist-border {
            border: 2px solid #000;
        }

        @media (min-width: 768px) {
            .brutalist-border {
                border-width: 4px;
            }
        }

        .brutalist-shadow {
            box-shadow: 8px 8px 0px 0px #000;
        }

        @media (min-width: 768px) {
            .brutalist-shadow {
                box-shadow: 16px 16px 0px 0px #000;
            }
        }

        .brutalist-shadow-blue {
            box-shadow: 8px 8px 0px 0px #3b82f6;
        }

        @media (min-width: 768px) {
            .brutalist-shadow-blue {
                box-shadow: 16px 16px 0px 0px #3b82f6;
            }
        }

        .no-round {
            border-radius: 0 !important;
        }

        .brutalist-button {
            border: 2px solid #000;
            box-shadow: 6px 6px 0px 0px #000;
            padding: 0.75rem 1.5rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: -0.05em;
            transition: all 0.1s;
            background-color: #fff;
            display: inline-block;
            cursor: pointer;
        }

        @media (min-width: 768px) {
            .brutalist-button {
                border-width: 4px;
                box-shadow: 12px 12px 0px 0px #000;
                padding: 1.25rem 2.5rem;
            }
        }

        .brutalist-button:hover {
            transform: translate(2px, 2px);
            box-shadow: 4px 4px 0px 0px #000;
        }

        @media (min-width: 768px) {
            .brutalist-button:hover {
                transform: translate(8px, 8px);
                box-shadow: 4px 4px 0px 0px #000;
            }
        }

        .brutalist-button:active {
            transform: translate(4px, 4px);
            box-shadow: 0px 0px 0px 0px #000;
        }

        @media (min-width: 768px) {
            .brutalist-button:active {
                transform: translate(12px, 12px);
                box-shadow: 0px 0px 0px 0px #000;
            }
        }

        .ocean-texture {
            background-image: repeating-linear-gradient(45deg, rgba(0, 0, 0, 0.03) 0px, rgba(0, 0, 0, 0.03) 1px, transparent 1px, transparent 15px);
        }

        .mono {
            font-family: 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', monospace;
        }

        .detail-label {
            font-size: 0.65rem;
            font-weight: 900;
            line-height: normal;
            background: #000;
            color: #fff;
            padding: 2px 5px;
            text-transform: uppercase;
        }

        .brutalist-card {
            background: #fff;
            border: 2px solid #000;
            box-shadow: 8px 8px 0px 0px #000;
        }

        @media (min-width: 768px) {
            .brutalist-card {
                border-width: 4px;
                box-shadow: 16px 16px 0px 0px #000;
            }
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="p-4 sm:p-8 md:p-12">
    <!-- Navbar -->
    <nav class="max-w-350 mx-auto flex flex-col sm:flex-row justify-between items-center gap-8 mb-16 sm:mb-24">
        <a href="/" class="p-4 sm:p-5 brutalist-border bg-white brutalist-shadow transition-transform hover:-translate-y-1">
            <img src="{{ asset('logo.svg') }}" alt="VeloquentLogo" class="h-12 sm:h-16 md:h-20 w-auto">
        </a>
        <div class="flex flex-wrap justify-center gap-4 sm:gap-8">
            <a href="/faq"
                class="brutalist-button text-lg sm:text-xl {{ Request::is('faq') ? 'bg-blue-500 text-white' : '' }}">FAQ</a>
            <a href="https://github.com/kevintherm/veloquent" target="_blank"
                class="brutalist-button bg-black text-white border-black text-lg sm:text-xl">GITHUB</a>
        </div>
    </nav>

    <main class="max-w-[1400px] mx-auto">
        {{ $slot }}

        <!-- Footer -->
        <footer class="border-t-4 sm:border-t-8 border-black pt-20 pb-20 mt-32 sm:mt-56">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-16 mb-24">
                <div class="md:col-span-4">
                    <div class="inline-block p-4 brutalist-border bg-white brutalist-shadow mb-8">
                        <img src="{{ asset('logo.svg') }}" alt="VeloquentLogo" class="h-12 w-auto">
                    </div>
                    <p class="text-xl font-bold uppercase tracking-tight leading-tight mb-8">
                        The open-source Laravel backend for developers who want speed without compromise.
                    </p>
                    <div class="flex gap-4">
                        <a href="https://github.com/kevintherm/veloquent" class="p-3 brutalist-border hover:bg-black hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.744.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                        </a>
                    </div>
                </div>
                
                <div class="md:col-span-2">
                    <h4 class="detail-label mb-6 inline-block">Product</h4>
                    <ul class="flex flex-col gap-4 mono font-bold uppercase text-lg">
                        <li><a href="#" class="hover:underline">Features</a></li>
                        <li><a href="#" class="hover:underline">Realtime</a></li>
                        <li><a href="#" class="hover:underline">Auth</a></li>
                        <li><a href="#" class="hover:underline">Dashboard</a></li>
                    </ul>
                </div>

                <div class="md:col-span-2">
                    <h4 class="detail-label mb-6 inline-block">Resources</h4>
                    <ul class="flex flex-col gap-4 mono font-bold uppercase text-lg">
                        <li><a href="/faq" class="hover:underline">FAQ</a></li>
                        <li><a href="/llms.txt" class="hover:underline">LLMS.txt</a></li>
                        <li><a href="/llms-full.txt" class="hover:underline">LLMS-Full</a></li>
                    </ul>
                </div>

                <div class="md:col-span-4">
                    <h4 class="detail-label mb-6 inline-block">Stay Updated</h4>
                    <div class="flex flex-col gap-4">
                        <div class="flex">
                            <input type="email" placeholder="YOUR@EMAIL.COM" class="brutalist-border p-4 grow mono font-bold focus:outline-none">
                            <button class="bg-black text-white p-4 font-black uppercase brutalist-border border-l-0">JOIN</button>
                        </div>
                        <p class="mono text-xs font-bold opacity-50 uppercase mt-4">No spam. Only major updates and architecture releases.</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col md:flex-row justify-between items-center gap-8 border-t-4 border-black pt-12">
                <div class="mono text-sm opacity-50 uppercase font-black tracking-[0.2em]">
                    &copy; 2026 VELOPHP - BUILT WITH LARAVEL
                </div>
                <div class="flex gap-8 mono text-sm font-black uppercase">
                    <a href="#" class="hover:underline">Privacy</a>
                    <a href="#" class="hover:underline">Terms</a>
                    <a href="https://github.com/kevintherm/veloquent" class="hover:underline">GitHub</a>
                </div>
            </div>
        </footer>
    </main>
</body>

</html>

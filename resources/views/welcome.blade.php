<x-app-layout>
    <section class="mb-48 relative">
        <div
            class="brutalist-card py-10 px-4 sm:py-16 sm:px-8 md:py-20 md:px-10 ocean-texture relative z-10 text-center border-blue-500 overflow-hidden">
            <h1
                class="text-4xl sm:text-6xl md:text-7xl lg:text-9xl font-extrabold tracking-tighter leading-[0.85] mb-8 sm:mb-10 uppercase text-black break-words">
                OPEN SOURCE<br>BACKEND
            </h1>

            <p
                class="text-xl sm:text-3xl md:text-4xl lg:text-5xl font-black max-w-6xl mx-auto mb-10 sm:mb-16 px-6 sm:px-10 py-4 sm:py-6 bg-black text-white brutalist-border uppercase tracking-tight leading-none break-words">
                PHP NATURE. CHEAP HOSTING. INFINITE OPTIONS.
            </p>

            <div class="flex justify-center">
                <a href="#explore"
                    class="brutalist-button text-2xl sm:text-3xl md:text-4xl px-8 sm:px-12 md:px-16 py-4 sm:py-6 md:py-8 bg-blue-500 text-white border-black">EXPLORE
                    ARCHITECTURE</a>
            </div>
        </div>

        <div class="absolute -top-12 -left-12 w-64 h-64 border-2 border-black/10 opacity-10"></div>
        <div class="absolute -bottom-16 -right-16 w-80 h-80 border-2 border-black/10 opacity-10"></div>
    </section>

    <section class="mb-56 relative">
        <div class="bg-white brutalist-border brutalist-shadow overflow-hidden">
            <div class="bg-black text-white px-10 py-5 flex justify-between items-center">
                <div class="flex items-center gap-6">
                    <div class="w-5 h-5 bg-blue-500"></div>
                    <span class="mono text-base font-bold tracking-widest uppercase">VeloPHP - Dashboard</span>
                </div>
            </div>
            <div class="bg-blue-500/10 p-4 relative">
                <img src="{{ asset('assets/dashboard.png') }}" alt="VeloquentDashboard" class="w-full brutalist-border">
                <div class="absolute bottom-8 right-8 bg-white p-4 brutalist-border">
                    <a href="/" class="mono text-base font-bold tracking-widest uppercase">Try Demo</a>
                </div>
            </div>
        </div>
    </section>

    <section id="explore" class="mb-32 sm:mb-56">
        <div class="flex items-center gap-4 sm:gap-8 mb-12 sm:mb-20">
            <h2 class="text-6xl sm:text-8xl md:text-9xl font-black uppercase tracking-tighter">WHY</h2>
            <div class="h-2 bg-black grow"></div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-8 sm:gap-16">
            <div
                class="sm:col-span-2 lg:col-span-8 brutalist-card p-8 sm:p-12 md:p-16 relative flex flex-col justify-between overflow-hidden">
                <div>
                    <div
                        class="w-16 h-16 sm:w-24 sm:h-24 bg-white brutalist-border mb-6 sm:mb-10 flex items-center justify-center">
                        <svg class="w-10 h-10 sm:w-14 sm:h-14 text-black" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="3"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011-1v5m-4 0h4">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-4xl sm:text-6xl md:text-7xl font-black mb-6 sm:mb-8 uppercase tracking-tighter">
                        HOST EVERYWHERE</h3>
                    <p class="text-lg sm:text-2xl md:text-3xl font-bold leading-[1.1] mb-8 sm:mb-12">Leverage the
                        true nature of PHP.
                        From cheap shared hosting to dedicated VPS, Veloquent runs wherever PHP lives.</p>
                </div>
                <div
                    class="mono text-sm sm:text-base font-black text-black/40 border-t-2 sm:border-t-4 border-black pt-6 sm:pt-8">
                    + PHP RUNTIME: 8.3+ REQUIRED
                </div>
            </div>

            <div
                class="sm:col-span-1 lg:col-span-4 bg-black text-white brutalist-border brutalist-shadow p-8 sm:p-12 md:p-16 relative border-blue-500">
                <div
                    class="w-16 h-16 sm:w-24 sm:h-24 bg-white brutalist-border mb-6 sm:mb-10 flex items-center justify-center">
                    <svg class="w-10 h-10 sm:w-14 sm:h-14 text-black" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="3"
                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z">
                        </path>
                    </svg>
                </div>
                <h3
                    class="text-3xl sm:text-5xl md:text-6xl font-black mb-6 sm:mb-10 uppercase tracking-tighter leading-none">
                    NO JWT
                    NONSENSE</h3>
                <p class="text-lg sm:text-xl md:text-2xl font-bold leading-tight opacity-90">Simple, secure, and
                    fast. Veloquent uses 64-char token that you attach on every requests. No JWT complex signing, overhead, or
                    revocation
                    difficuly.</p>
            </div>

            <div class="sm:col-span-1 lg:col-span-5 brutalist-card p-8 sm:p-12 md:p-16 relative border-dashed">
                <div
                    class="w-12 h-12 sm:w-16 sm:h-16 bg-blue-500 brutalist-border flex items-center justify-center mb-6 sm:mb-10">
                    <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="3"
                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <h3 class="text-3xl sm:text-5xl md:text-6xl font-black mb-6 sm:mb-8 uppercase tracking-tighter">
                    REALTIME READY</h3>
                <p class="text-lg sm:text-xl md:text-2xl font-bold leading-tight opacity-80">Built on top of
                    Laravel's
                    real-time broadcasting system. Pusher, Reverb, or whatever you like.
                </p>
            </div>

            <div
                class="sm:col-span-2 lg:col-span-7 bg-blue-500 text-white brutalist-border brutalist-shadow p-8 sm:p-12 md:p-16 ocean-texture relative">
                <div
                    class="w-16 h-16 sm:w-24 sm:h-24 bg-black brutalist-border mb-6 sm:mb-10 flex items-center justify-center">
                    <svg class="w-10 h-10 sm:w-14 sm:h-14 text-white" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="3"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                        </path>
                    </svg>
                </div>
                <h3
                    class="text-4xl sm:text-6xl md:text-7xl font-black mb-6 sm:mb-8 uppercase tracking-tighter leading-none">
                    LARAVEL
                    NATIVE</h3>
                <p class="text-lg sm:text-2xl md:text-3xl font-bold leading-[1.1]">Utilize
                    the tools
                    and patterns you already know and love.</p>
            </div>
    </section>

    <section class="mb-32 relative">
        <div class="brutalist-card bg-white text-black p-12 sm:p-20 md:p-24 text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-full ocean-texture opacity-10"></div>
            <div class="relative z-10">
                <h2 class="text-5xl sm:text-7xl md:text-8xl font-black mb-10 uppercase tracking-tighter leading-none">
                    START BUILDING<br>WITHOUT LIMITS
                </h2>
                <p
                    class="text-xl sm:text-2xl md:text-3xl font-bold mb-16 max-w-4xl mx-auto opacity-80 uppercase tracking-tight">
                    Deploy your own BaaS in minutes. Own whats yours.
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-8">
                    <a href="https://github.com/kevintherm/veloquent" target="_blank"
                        class="brutalist-button bg-black text-white text-2xl px-12 py-6">
                        GET STARTED
                    </a>
                    <a href="https://github.com/kevintherm/veloquent" target="_blank"
                        class="brutalist-button bg-blue-500 text-white border-black text-2xl px-12 py-6">
                        VIEW ON GITHUB
                    </a>
                </div>
            </div>
        </div>
    </section>
</x-app-layout>

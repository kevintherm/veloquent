<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Documentation' }} - Velo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <link rel="stylesheet" href="https://unpkg.com/highlightjs-copy/dist/highlightjs-copy.min.css" />
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif;background-color: #ffffff;background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.08) 1.5px, transparent 0);background-size: 25px 25px;color: #000;}

        .brutalist-border { border: 4px solid #000; }
        .brutalist-shadow { box-shadow: 8px 8px 0px 0px #000; }
        
        /* Markdown Styling Overrides */
        .content-area h1, .content-area h2, .content-area h3 { position: relative; }
        .heading-permalink { position: absolute;left: -2rem; top: 50%;transform: translateY(-50%);color: #3b82f6 !important; border: none !important; font-size: 0.8em;font-weight: 900;text-decoration: none !important;}
        .heading-permalink:hover { color: #fff !important;background: #3b82f6; }
        .content-area h1 .heading-permalink { left: -4rem; }

        .content-area h1 { font-size: 4rem; font-weight: 900; margin-bottom: 2rem; text-transform: uppercase; letter-spacing: -0.0125em; line-height: 1; }
        .content-area h2 { font-size: 2.25rem; font-weight: 900; margin-top: 4rem; margin-bottom: 1.5rem; text-transform: uppercase; border-bottom: 8px solid #000; padding-bottom: 1rem; }
        .content-area h3 { font-size: 1.5rem; font-weight: 800; margin-top: 2.5rem; margin-bottom: 1rem; text-transform: uppercase; }
        .content-area h4 { font-size: 1.25rem; font-weight: 700; margin-top: 2.5rem; margin-bottom: 1rem; text-transform: uppercase; }
        .content-area p { font-size: 1.25rem; font-weight: 500; line-height: 1.6; margin-bottom: 1.5rem; color: rgba(0,0,0,0.8); }
        
        .content-area ul { list-style: none; margin-bottom: 2.5rem; padding: 0; }
        .content-area ol { list-style: none; margin-bottom: 2.5rem; padding: 0; }
        .content-area ul li { position: relative; padding-left: 2.5rem; font-size: 1.25rem; font-weight: 700; margin-bottom: 0.75rem; }
        .content-area ul li::before { content: ''; position: absolute; left: 0; top: 0.5rem; width: 1.25rem; height: 1.25rem; background: #3b82f6; border: 2px solid #000; }
        .content-area ol li { position: relative; padding-left: 2.5rem; font-size: 1.25rem; font-weight: 700; margin-bottom: 0.75rem; }
        .content-area ol li::before { content: ''; position: absolute; left: 0; top: 0.5rem; width: 1.25rem; height: 1.25rem; background: #3b82f6; border: 2px solid #000; }
        
        .content-area a { color: #2563eb; font-weight: 800; border-bottom: 4px solid #3b82f6; text-decoration: none; transition: all 0.2s; }
        .content-area a:hover { background: #3b82f6; color: #fff; }
        
        .content-area table { width: 100%; border: 4px solid #000; margin-bottom: 3rem; border-collapse: collapse; background: #fff; }
        .content-area th { background: #000; color: #fff; padding: 1.25rem; text-align: left; text-transform: uppercase; font-weight: 900; border-right: 2px solid #fff; }
        .content-area td { padding: 1.25rem; border-bottom: 4px solid #000; border-right: 2px solid #000; font-weight: 700; font-size: 1.125rem; }
        .content-area tr:last-child td { border-bottom: 0; }
        .content-area th:last-child, .content-area td:last-child { border-right: 0; }
        
        .content-area code:not(pre code) { background: rgba(59,130,246,0.1); padding: 0.25rem 0.5rem; border: 2px solid #000; font-size: 0.875rem; font-weight: 800; }
        .content-area pre { border: 4px solid #000; box-shadow: 12px 12px 0px 0px #000; margin-bottom: 2.5rem; overflow: hidden; }
        .content-area pre code { padding: 2rem !important; background: #0d1117 !important; display: block; }
        .content-area pre code.hljs { font-weight: 500 !important; }

        /* Sidebar Styling */
        #sidebar { background-color: #fff; color: #000; }
        .sidebar-category-title { font-size: 0.7rem; font-weight: 900; letter-spacing: 0.2em; color: rgba(0,0,0,0.4); padding: 2rem 2rem 0.75rem; text-transform: uppercase; }
        .sidebar-link { display: block; width: 100%; padding: 0.875rem 2rem; font-size: 1rem; font-weight: 700; text-transform: uppercase; text-decoration: none; color: rgba(0,0,0,0.7); position: relative; transition: all 0.2s; border: none; background: transparent; }
        .sidebar-link:hover { color: #000; background: rgba(59,130,246,0.05); padding-left: 2.25rem; }
        .sidebar-link.active { color: #2563eb; background: rgba(59,130,246,0.1); padding-left: 2.25rem; font-weight: 900; }
        .sidebar-link.active::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 6px; background: #3b82f6; }

        ::-webkit-scrollbar { width: 12px; }
        ::-webkit-scrollbar-track { background: #f8fafc; border-left: 1px solid rgba(0,0,0,0.05); }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border: 3px solid #f8fafc; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="min-h-screen flex flex-col md:flex-row scroll-smooth">
    <!-- Mobile Nav Toggle -->
    <div class="md:hidden p-6 bg-white border-b-8 border-black sticky top-0 z-50 flex justify-between items-center">
        <a href="/docs"><img src="/docs/logo.svg" alt="Velo" class="h-10"></a>
        <button id="menu-toggle" class="px-6 py-3 bg-blue-500 text-white font-black border-4 border-black shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] active:translate-x-1 active:translate-y-1 active:shadow-none transition-all">MENU</button>
    </div>

    <!-- Sidebar -->
    <aside id="sidebar" class="hidden md:block w-full md:w-96 border-r-4 border-black/5 shrink-0 sticky top-0 h-screen overflow-y-auto z-40">
        <div class="px-12 py-16 flex justify-center bg-blue-500 border-b-8 border-black">
            <a href="/{{ config('velo.docs.path', 'docs') }}">
                <div class="bg-white p-6 border-4">
                    <img src="/{{ config('velo.docs.path', 'docs') }}/logo.svg" alt="VeloquentLogo" class="h-16 w-auto">
                </div>
            </a>
        </div>
        
        <!-- Search Box -->
        <div class="p-8 border-b border-black/5 bg-white">
            <form action="{{ route('docs.search') }}" method="GET" class="relative">
                <input type="text" name="q" value="{{ $search_query ?? '' }}" placeholder="SEARCH DOCS..." 
                    class="w-full px-6 py-4 bg-slate-50 border-2 border-black/10 text-black font-black uppercase tracking-tighter placeholder-black/20 focus:border-blue-500 focus:bg-white transition-all outline-none">
                <button type="submit" class="absolute right-4 top-1/2 -translate-y-1/2 font-black text-black/30 hover:text-blue-600 transition-colors">↵</button>
            </form>
        </div>

        <nav class="flex flex-col pb-20">
            @foreach($sidebarCategories as $categoryKey => $category)
                <div class="sidebar-category-title">
                    {{ $category['title'] }}
                </div>
                <div class="flex flex-col">
                    @foreach($category['files'] as $doc)
                        @php $isActive = ($activeFile ?? '') === $doc['path']; @endphp
                        <a href="/{{ config('velo.docs.path', 'docs') }}/{{ $doc['path'] }}" class="sidebar-link {{ $isActive ? 'active' : '' }}">
                            {{ $doc['title'] }}
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="grow p-8 md:p-24 overflow-x-hidden">
        <div class="max-w-4xl mx-auto">
            <div class="mb-20 flex items-center gap-8">
                <div class="bg-blue-500 text-white px-6 py-3 font-black uppercase tracking-widest border-4 border-black">1.0 DOC_TYPE: {{ isset($search_results) ? 'SEARCH_QUERY' : 'SYSTEM_REFERENCE' }}</div>
                <div class="h-2 bg-black grow"></div>
            </div>

            <div class="content-area">
                @if(isset($search_results))
                    <h1 class="italic">Search Results for: "{{ $search_query }}"</h1>
                    <p class="mb-12 font-black text-2xl tracking-tighter">{{ count($search_results) }} matches found in core system docs</p>
                    
                    <div class="space-y-8">
                        @foreach($search_results as $result)
                            <div class="bg-white p-10 border-8 border-black shadow-[16px_16px_0px_0px_rgba(0,0,0,1)] -rotate-1 hover:rotate-0 transition-transform cursor-pointer" onclick="window.location='/{{ config('velo.docs.path', 'docs') }}/{{ $result['path'] }}'">
                                <h2 class="text-3xl font-black mb-4 italic !mt-0 !border-0 !p-0 underline">{{ $result['title'] }}</h2>
                                <p class="text-xl font-medium mb-6 leading-relaxed">{{ $result['snippet'] }}</p>
                                <a href="/{{ config('velo.docs.path', 'docs') }}/{{ $result['path'] }}" class="text-blue-600 font-black uppercase tracking-widest border-b-4 border-blue-600 hover:bg-blue-600 hover:text-white transition-all">View Document →</a>
                            </div>
                        @endforeach
                    </div>

                    @if(count($search_results) === 0)
                        <div class="bg-blue-500 p-12 border-8 border-black text-white">
                            <h2 class="text-4xl font-black mb-4">NO MATCHES FOUND</h2>
                            <p class="text-2xl font-bold">Try different keywords or check our roadmap.</p>
                        </div>
                    @endif
                @else
                    {!! $content !!}
                @endif
            </div>

            <!-- Footer -->
            <footer class="mt-40 pt-20 border-t-8 border-black flex flex-col md:flex-row justify-between items-start md:items-center gap-12">
                <div>
                    <img src="/docs/logo.svg" alt="Velo" class="h-8 mb-4">
                    <p class="font-black uppercase tracking-tighter">Open Source Backend</p>
                </div>
                <div class="flex gap-8">
                    <a href="https://github.com/kevintherm/veloquent" target="_blank" class="bg-black text-white px-8 py-4 font-black border-4 border-black hover:bg-white hover:text-black transition-all">GITHUB</a>
                    <a href="https://github.com/kevintherm/veloquent/blob/main/TODO.md" target="_blank" class="bg-blue-500 text-white px-8 py-4 font-black border-4 border-black hover:bg-black transition-all">ROADMAP</a>
                </div>
            </footer>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/languages/dart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/languages/http.min.js"></script>
    <script src="https://unpkg.com/highlightjs-copy/dist/highlightjs-copy.min.js"></script> 
    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            hljs.addPlugin(new CopyButtonPlugin());
            
            document.querySelectorAll('pre code').forEach((el) => {
                hljs.highlightElement(el);
            });

            // Mobile menu toggle
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.getElementById('sidebar');
            
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('hidden');
                    menuToggle.textContent = sidebar.classList.contains('hidden') ? 'MENU' : 'CLOSE';
                });
            }
        });
    </script>
</body>
</html>

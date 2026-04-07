<x-app-layout title="FAQ - Veloquent">
    <section class="mb-24 sm:mb-32">
        <div class="flex items-center gap-4 sm:gap-8 mb-12 sm:mb-20">
            <h1 class="text-6xl sm:text-8xl md:text-9xl font-black uppercase tracking-tighter">FAQ</h1>
            <div class="h-2 bg-black grow"></div>
        </div>

        <div class="grid grid-cols-1 gap-8 sm:gap-12">
            @foreach($faqs as $faq)
                <div class="brutalist-card p-10 sm:p-12">
                    <div class="detail-label mb-6 inline-block">QUESTION</div>
                    <h2 class="text-2xl sm:text-3xl font-black mb-8 uppercase tracking-tight leading-none">
                        {{ $faq['question'] }}
                    </h2>
                    <div class="h-1 bg-black w-12 mb-8"></div>
                    {!! str()->markdown($faq['answer']) !!}
                </div>
            @endforeach
        </div>
    </section>

    <section class="mb-32 relative">
        <div class="bg-white text-black p-12 sm:p-20 md:p-24 brutalist-border brutalist-shadow text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-full ocean-texture opacity-10"></div>
            <div class="relative z-10">
                <h2 class="text-4xl sm:text-6xl font-black mb-10 uppercase tracking-tighter">
                    STILL HAVE QUESTIONS?
                </h2>
                <p class="text-xl sm:text-2xl font-bold mb-12 max-w-3xl mx-auto opacity-80">
                    Reach out on GitHub or join our community discussions. We're here to help you build faster.
                </p>
                <div class="flex justify-center">
                    <a href="https://github.com/kevintherm/veloquent/issues" target="_blank"
                       class="brutalist-button bg-white text-black border-black text-xl px-12 py-5">
                        OPEN AN ISSUE
                    </a>
                </div>
            </div>
        </div>
    </section>
</x-app-layout>

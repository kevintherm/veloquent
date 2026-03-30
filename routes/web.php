<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

if (! function_exists('getMarkdownConverter')) {
    function getMarkdownConverter()
    {
        $config = [
            'heading_permalink' => [
                'html_class' => 'heading-permalink',
                'id_prefix' => 'content',
                'apply_id_to_heading' => true,
                'heading_class' => '',
                'fragment_prefix' => 'content',
                'insert' => 'before',
                'min_heading_level' => 1,
                'max_heading_level' => 6,
                'title' => 'Permalink',
                'symbol' => '#',
            ],
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        return new MarkdownConverter($environment);
    }
}

Route::get('/docs/search', function () {
    $query = request('q');
    $results = [];

    if ($query) {
        $mdFiles = File::files(base_path('docs'));
        foreach ($mdFiles as $f) {
            if ($f->getExtension() !== 'md') {
                continue;
            }

            $rawContent = File::get($f->getPathname());
            if (Str::contains(strtolower($rawContent), strtolower($query))) {
                $pos = strpos(strtolower($rawContent), strtolower($query));
                $start = max(0, $pos - 50);
                $snippet = '...'.substr($rawContent, $start, 150).'...';

                $results[] = [
                    'file' => $f->getFilename(),
                    'title' => Str::headline(str_replace('.md', '', $f->getFilename())),
                    'snippet' => $snippet,
                ];
            }
        }
    }

    $order = [
        'introduction.md',
        'collections.md',
        'records-api.md',
        'api-rules.md',
        'api.md',
        'authentication.md',
        'realtime.md',
        'index.md',
    ];

    $allFiles = collect(File::files(base_path('docs')))
        ->filter(fn ($f) => $f->getExtension() === 'md')
        ->map(fn ($f) => $f->getFilename())
        ->sortBy(function ($filename) use ($order) {
            $pos = array_search($filename, $order);

            return $pos === false ? 999 : $pos;
        })
        ->values();

    return view('docs.viewer', [
        'content' => null,
        'search_results' => $results,
        'search_query' => $query,
        'title' => 'Search Results',
        'files' => $allFiles,
    ]);
});

Route::get('/docs/{file?}', function ($file = 'index.html') {
    if ($file === 'index.html') {
        return response()->file(base_path('docs/index.html'));
    }

    $path = base_path("docs/{$file}");
    if (! file_exists($path)) {
        abort(404);
    }

    if (Str::endsWith($file, '.md')) {
        $content = str_replace(["\r\n", "\r"], "\n", File::get($path));
        $converter = getMarkdownConverter();

        $order = [
            'introduction.md',
            'collections.md',
            'records-api.md',
            'api-rules.md',
            'api.md',
            'authentication.md',
            'realtime.md',
            'index.md',
        ];

        $files = collect(File::files(base_path('docs')))
            ->filter(fn ($f) => $f->getExtension() === 'md')
            ->map(fn ($f) => $f->getFilename())
            ->sortBy(function ($filename) use ($order) {
                $pos = array_search($filename, $order);

                return $pos === false ? 999 : $pos;
            })
            ->values();

        return view('docs.viewer', [
            'content' => (string) $converter->convert($content),
            'title' => Str::headline(str_replace('.md', '', $file)),
            'files' => $files,
        ]);
    }

    return response()->file($path);
})->where('file', '.*');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!(api|docs)(/|$)).*');

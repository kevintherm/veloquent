<?php

namespace App\Domain\Docs;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

class DocsManager
{
    /**
     * Get the markdown converter instance.
     */
    public function getConverter(): MarkdownConverter
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

    /**
     * Get the sidebar navigation structure.
     */
    public function getSidebar(): Collection
    {
        $docsPath = base_path('docs');
        $order = [
            'getting-started',
            'architecture-concepts',
            'the-basics',
            'security',
            'database',
            'api-documentation',
            'realtime',
            'changelog',
        ];

        $categories = collect(File::directories($docsPath))
            ->mapWithKeys(function ($directory) {
                $folderName = basename($directory);
                $files = collect(File::files($directory))
                    ->filter(fn ($file) => $file->getExtension() === 'md')
                    ->map(fn ($file) => [
                        'name' => basename($file, '.md'),
                        'title' => $this->formatTitle(basename($file, '.md')),
                        'path' => $folderName.'/'.basename($file, '.md'),
                    ])
                    ->sortBy('name')
                    ->values();

                if ($files->isEmpty()) {
                    return [];
                }

                return [$folderName => [
                    'title' => $this->formatTitle($folderName),
                    'files' => $files,
                ]];
            })
            ->sortBy(function ($item, $key) use ($order) {
                $pos = array_search($key, $order);

                return $pos === false ? 999 : $pos;
            });

        return $categories;
    }

    /**
     * Search documentation files.
     */
    public function search(string $query): Collection
    {
        $docsPath = base_path('docs');
        $results = collect();

        if (empty($query)) {
            return $results;
        }

        $files = File::allFiles($docsPath);
        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $content = File::get($file->getPathname());
            if (Str::contains(strtolower($content), strtolower($query))) {
                $pos = strpos(strtolower($content), strtolower($query));
                $start = max(0, $pos - 50);
                $snippet = '...'.substr($content, $start, 150).'...';

                $relativePath = str_replace([$docsPath.DIRECTORY_SEPARATOR, '.md'], '', $file->getPathname());

                $results->push([
                    'path' => $relativePath,
                    'title' => $this->formatTitle(basename($file, '.md')),
                    'snippet' => $snippet,
                ]);
            }
        }

        return $results;
    }

    /**
     * Format a string into a title.
     */
    protected function formatTitle(string $value): string
    {
        return Str::headline(str_replace('-', ' ', $value));
    }
}

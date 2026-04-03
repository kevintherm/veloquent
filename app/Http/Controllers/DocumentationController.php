<?php

namespace App\Http\Controllers;

use App\Domain\Docs\DocsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DocumentationController extends Controller
{
    public function __construct(protected DocsManager $docsManager) {}

    public function show(Request $request, string $file = 'getting-started/introduction')
    {
        // Strip .md if present to handle both link formats
        $file = Str::replaceLast('.md', '', $file);

        $path = base_path("docs/{$file}.md");

        if (! File::exists($path)) {
            // Check for index.html or other assets if needed
            $assetPath = base_path("docs/{$file}");
            if (File::exists($assetPath) && ! File::isDirectory($assetPath)) {
                return response()->file($assetPath);
            }
            abort(404);
        }

        $rawContent = str_replace(["\r\n", "\r"], "\n", File::get($path));
        $converter = $this->docsManager->getConverter();

        return view('docs.viewer', [
            'content' => (string) $converter->convert($rawContent),
            'title' => Str::headline(basename($file)),
            'activeFile' => $file,
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->query('q', '');
        $results = $this->docsManager->search($query);

        return view('docs.viewer', [
            'content' => null,
            'search_results' => $results,
            'search_query' => $query,
            'title' => 'Search Results',
            'activeFile' => null,
        ]);
    }
}

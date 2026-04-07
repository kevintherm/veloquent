<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class CompileLlmsFullCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'llms:full';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile all documentation into a single llms-full.txt file for LLM context';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $docsPath = base_path('docs');
        $outputPath = public_path('llms-full.txt');

        if (! File::isDirectory($docsPath)) {
            $this->error("Documentation directory not found at {$docsPath}");

            return;
        }

        $this->info('Compiling documentation...');

        $finder = new Finder;
        $finder->files()->in($docsPath)->name('*.md')->sortByName();

        $content = "# Veloquent Full Documentation Context\n\n";
        $content .= "This file contains the complete documentation for Veloquent, compiled into a single document for AI consumption.\n";
        $content .= "Generated on: ".now()->toDateTimeString()."\n\n";

        foreach ($finder as $file) {
            $relativePath = str_replace(base_path().'/', '', $file->getRealPath());
            $this->line("Found: {$relativePath}");

            $content .= "--- FILE: {$relativePath} ---\n";
            $content .= $file->getContents();
            $content .= "\n\n";
        }

        File::put($outputPath, $content);

        $this->info("Compiled documentation saved to: {$outputPath}");
    }
}

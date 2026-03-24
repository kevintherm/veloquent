<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogViewerRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class LogViewerController extends Controller
{
    public function getDates()
    {
        $logPath = storage_path('logs');
        if (! File::isDirectory($logPath)) {
            return response()->json([]);
        }

        $files = File::files($logPath);
        $dates = [];

        foreach ($files as $file) {
            if (preg_match('/laravel-(\d{4}-\d{2}-\d{2})\.log/', $file->getFilename(), $matches)) {
                $dates[] = $matches[1];
            }
        }

        // Sort dates descending
        rsort($dates);

        return response()->json($dates);
    }

    public function index(LogViewerRequest $request)
    {
        $validated = $request->validated();
        $date = $validated['date'] ?? date('Y-m-d');
        $level = $validated['level'] ?? null;
        $query = $validated['query'] ?? null;
        $hour = isset($validated['hour']) ? (int) $validated['hour'] : null;
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $logFile = storage_path("logs/laravel-{$date}.log");

        if (! File::exists($logFile)) {
            return response()->json([
                'data' => [],
                'meta' => ['total' => 0, 'last_page' => 1, 'current_page' => 1, 'per_page' => $perPage],
                'stats' => [],
            ]);
        }

        $cached = $this->getCache($logFile, $level, $query, $hour, $perPage);
        $offsets = $cached['page_offsets'];
        $total = $cached['total'];
        $stats = $cached['stats'];

        $lastPage = (int) ceil($total / max(1, $perPage));

        // Sliding window newest-first: Page 1 is always the latest logs.
        $startMatchIndex = max(0, $total - $page * $perPage);
        $endMatchIndex = $total - ($page - 1) * $perPage - 1;
        $itemsToRead = $endMatchIndex - $startMatchIndex + 1;

        $logs = [];

        // Page requested beyond what we have or invalid
        if ($itemsToRead <= 0 || $page > $lastPage) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => $lastPage,
                ],
                'stats' => $stats,
            ]);
        }

        $offsetKey = (int) floor($startMatchIndex / $perPage);
        $skipCount = $startMatchIndex % $perPage;

        $handle = fopen($logFile, 'rb');

        if (isset($offsets[$offsetKey])) {
            fseek($handle, $offsets[$offsetKey]);
        }

        // Skip lines until we reach the start match index
        $skipped = 0;
        while ($skipped < $skipCount && ! feof($handle)) {
            $line = fgets($handle);
            if ($line === false || trim($line) === '') {
                continue;
            }

            // Quick JSON-like check (must match what scan() uses)
            if (! str_contains($line, '"datetime":"')) {
                continue;
            }

            // Re-apply filters
            if ($level && ! str_contains($line, '"level_name":"'.strtoupper($level).'"')) {
                continue;
            }
            if ($query && ! str_contains(strtolower($line), strtolower($query))) {
                continue;
            }
            if ($hour !== null && ! $this->lineMatchesHour($line, $hour)) {
                continue;
            }
            $skipped++;
        }

        // Read the actual logs for the page
        $read = 0;
        while ($read < $itemsToRead && ! feof($handle)) {
            $line = fgets($handle);
            if ($line === false || trim($line) === '') {
                continue;
            }

            if (! str_contains($line, '"datetime":"')) {
                continue;
            }

            // Re-apply filters
            if ($level && ! str_contains($line, '"level_name":"'.strtoupper($level).'"')) {
                continue;
            }
            if ($query && ! str_contains(strtolower($line), strtolower($query))) {
                continue;
            }
            if ($hour !== null && ! $this->lineMatchesHour($line, $hour)) {
                continue;
            }

            $entry = json_decode($line, true);
            if ($entry) {
                $logs[] = [
                    'datetime' => $entry['datetime'],
                    'env' => $entry['channel'] ?? 'local',
                    'level' => strtoupper($entry['level_name']),
                    'message' => trim($entry['message']),
                    'context' => $entry['context'] ?: null,
                ];
                $read++;
            }
        }

        fclose($handle);

        // Logs are oldest-first from file, reverse for newest-first within the page
        $logs = array_reverse($logs);

        return response()->json([
            'data' => $logs,
            'meta' => [
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
            ],
            'stats' => $stats,
        ]);
    }

    private function getCache(string $logFile, ?string $level, ?string $query, ?int $hour, int $perPage): array
    {
        clearstatcache(true, $logFile);
        $fileSize = File::size($logFile);
        $cacheKey = 'logs:'.basename($logFile).':'.md5(serialize([$level, $query, $hour, $perPage]));
        $cached = Cache::get($cacheKey);

        if ($cached && $cached['file_size'] === $fileSize) {
            // File unchanged, cache is valid
            return $cached;
        }

        if ($cached && $cached['file_size'] < $fileSize) {
            // File grew (new logs appended) — scan only the new bytes
            $tail = $this->scan($logFile, $level, $query, $hour, $perPage, $cached['file_size'], $cached['total']);

            $cached['total'] += $tail['total'];
            $cached['stats'] = $this->mergeStats($cached['stats'], $tail['stats']);
            $cached['page_offsets'] = array_merge($cached['page_offsets'], $tail['page_offsets']);
            $cached['file_size'] = $fileSize;
        } else {
            // No cache, or file was rotated/truncated — full scan from beginning
            $cached = $this->scan($logFile, $level, $query, $hour, $perPage, 0, 0);
            $cached['file_size'] = $fileSize;
        }

        Cache::put($cacheKey, $cached, now()->addDay());

        return $cached;
    }

    private function scan(
        string $logFile,
        ?string $level,
        ?string $query,
        ?int $hour,
        int $perPage,
        int $fromByte,
        int $existingMatchCount
    ): array {
        $stats = array_fill(0, 24, ['count' => 0, 'error' => 0, 'warning' => 0, 'info' => 0]);
        $pageOffsets = [];
        $matchedCount = $existingMatchCount;

        $handle = fopen($logFile, 'rb');
        fseek($handle, $fromByte);

        while (! feof($handle)) {
            $lineStart = ftell($handle);
            $line = fgets($handle);

            if ($line === false || trim($line) === '') {
                continue;
            }

            // Quick check: if it doesn't look like a JSON log line, skip stats and matching
            if (! str_contains($line, '"datetime":"')) {
                continue;
            }

            $this->accumulateStats($stats, $line);

            if ($level && ! str_contains($line, '"level_name":"'.strtoupper($level).'"')) {
                continue;
            }
            if ($query && ! str_contains(strtolower($line), strtolower($query))) {
                continue;
            }
            if ($hour !== null && ! $this->lineMatchesHour($line, $hour)) {
                continue;
            }

            // Record byte offset at the start of each page boundary
            if ($matchedCount % $perPage === 0) {
                $pageOffsets[] = $lineStart;
            }
            $matchedCount++;
        }

        fclose($handle);

        return [
            'total' => $matchedCount - $existingMatchCount,
            'stats' => $stats,
            'page_offsets' => $pageOffsets,
        ];
    }

    private function mergeStats(array $existing, array $new): array
    {
        foreach ($new as $hour => $counts) {
            $existing[$hour]['count'] += $counts['count'];
            $existing[$hour]['error'] += $counts['error'];
            $existing[$hour]['warning'] += $counts['warning'];
            $existing[$hour]['info'] += $counts['info'];
        }

        return $existing;
    }

    private function accumulateStats(array &$stats, string $line): void
    {
        $dtPos = strpos($line, '"datetime":"');
        $lvPos = strpos($line, '"level_name":"');

        if ($dtPos === false || $lvPos === false) {
            return;
        }

        $colonPos = strpos($line, ':', $dtPos + 22);
        if ($colonPos === false) {
            return;
        }

        $actualHour = (int) substr($line, $colonPos - 2, 2);
        $endLv = strpos($line, '"', $lvPos + 14);
        $actualLevel = strtoupper(substr($line, $lvPos + 14, $endLv - ($lvPos + 14)));

        if ($actualHour >= 0 && $actualHour < 24) {
            $stats[$actualHour]['count']++;
            match ($actualLevel) {
                'ERROR' => $stats[$actualHour]['error']++,
                'WARNING' => $stats[$actualHour]['warning']++,
                default => $stats[$actualHour]['info']++,
            };
        }
    }

    private function lineMatchesHour(string $line, int $hour): bool
    {
        $dtPos = strpos($line, '"datetime":"');
        if ($dtPos === false) {
            return false;
        }

        $colonPos = strpos($line, ':', $dtPos + 22);
        if ($colonPos === false) {
            return false;
        }

        return (int) substr($line, $colonPos - 2, 2) === $hour;
    }
}

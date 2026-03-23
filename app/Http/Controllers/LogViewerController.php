<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogViewerRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogViewerController extends Controller
{
    public function getDates()
    {
        $logPath = storage_path('logs');
        if (!File::isDirectory($logPath)) {
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
        if (!File::exists($logFile)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'last_page' => 1,
                    'current_page' => 1,
                    'per_page' => $perPage,
                ],
                'stats' => [],
            ]);
        }

        $file = new \SplFileObject($logFile, 'r');
        $logs = [];
        $stats = array_fill(0, 24, ['count' => 0, 'error' => 0, 'warning' => 0, 'info' => 0]);

        $processEntry = function ($entry) use (&$logs, &$stats, $level, $hour, $query) {
            $logDate = $entry['date'];
            $parsedLevel = strtoupper($entry['level']);
            $env = $entry['env'];
            $message = trim($entry['message']);

            try {
                $d = new \DateTime($logDate);
                $h = (int) $d->format('H');
                $stats[$h]['count']++;
                if ($parsedLevel === 'ERROR') {
                    $stats[$h]['error']++;
                } elseif ($parsedLevel === 'WARNING') {
                    $stats[$h]['warning']++;
                } else {
                    $stats[$h]['info']++;
                }
            } catch (\Exception $e) {
                $h = null;
            }

            if ($level && strtolower($parsedLevel) !== strtolower($level)) {
                return;
            }

            if ($hour !== null && $hour !== '' && (int) $h !== (int) $hour) {
                return;
            }

            $context = null;
            if (preg_match('/({.*})$/s', $message, $jsonMatch)) {
                $jsonStr = $jsonMatch[1];
                $decoded = json_decode($jsonStr, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $context = $decoded;
                    $message = trim(substr($message, 0, -strlen($jsonStr)));
                }
            }

            if ($query) {
                $q = strtolower($query);
                $inMessage = str_contains(strtolower($message), $q);
                $inContext = $context && str_contains(strtolower(json_encode($context)), $q);
                if (!$inMessage && !$inContext) {
                    return;
                }
            }

            $logs[] = [
                'datetime' => $logDate,
                'env' => $env,
                'level' => $parsedLevel,
                'message' => $message,
                'context' => $context,
            ];
        };

        $currentEntry = null;
        while (!$file->eof()) {
            $line = $file->fgets();
            if ($line === false || $line === '') {
                continue;
            }

            if (preg_match('/^\[(?P<date>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (?P<env>[a-zA-Z0-9_]+)\.(?P<level>[A-Z_]+): (?P<message>.*)$/s', $line, $match)) {
                if ($currentEntry) {
                    $processEntry($currentEntry);
                }

                $currentEntry = [
                    'date' => $match['date'],
                    'env' => $match['env'],
                    'level' => $match['level'],
                    'message' => $match['message'],
                ];
            } elseif ($currentEntry) {
                $currentEntry['message'] .= $line;
            }
        }

        if ($currentEntry) {
            $processEntry($currentEntry);
        }


        // Reverse to show newest first
        $logs = array_reverse($logs);

        // Paginate
        $total = count($logs);
        $offset = ($page - 1) * $perPage;
        $paginatedLogs = array_slice($logs, $offset, $perPage);

        return response()->json([
            'data' => $paginatedLogs,
            'meta' => [
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'stats' => $stats,
        ]);
    }
}

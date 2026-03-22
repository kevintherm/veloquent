<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

    public function index(Request $request)
    {
        $date = $request->query('date', date('Y-m-d'));
        $level = $request->query('level');

        $logFile = storage_path("logs/laravel-{$date}.log");
        if (! File::exists($logFile)) {
            return response()->json([
                'data' => [],
                'meta' => ['total' => 0],
            ]);
        }

        $content = File::get($logFile);
        $pattern = '/^\[(?P<date>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (?P<env>[a-zA-Z0-9_]+)\.(?P<level>[A-Z_]+): (?P<message>.*?)(?=\n\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] |\z)/ms';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $logs = [];
        foreach ($matches as $match) {
            $parsedLevel = $match['level'];
            if ($level && strtolower($parsedLevel) !== strtolower($level)) {
                continue;
            }

            // Extract context JSON if message contains it at the end
            $message = trim($match['message']);
            $context = null;

            // Simple extraction: context often starts with { and ends with } at the end of the message
            if (preg_match('/({.*})$/s', $message, $jsonMatch)) {
                $jsonStr = $jsonMatch[1];
                $decoded = json_decode($jsonStr, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $context = $decoded;
                    $message = trim(substr($message, 0, -strlen($jsonStr)));
                }
            }

            $logs[] = [
                'datetime' => $match['date'],
                'env' => $match['env'],
                'level' => $parsedLevel,
                'message' => $message,
                'context' => $context,
            ];
        }

        // Reverse to show newest first
        $logs = array_reverse($logs);

        return response()->json([
            'data' => $logs,
            'meta' => [
                'total' => count($logs),
            ],
        ]);
    }
}

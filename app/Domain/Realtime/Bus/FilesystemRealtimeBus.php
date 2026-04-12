<?php

namespace App\Domain\Realtime\Bus;

use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use Closure;

class FilesystemRealtimeBus implements RealtimeBusDriver
{
    private const int POLL_INTERVAL_US = 500_000;

    private function directory(): string
    {
        $directory = trim((string) config('velo.realtime.filesystem_bus_path', storage_path('realtime/bus')));

        if ($directory === '') {
            $directory = storage_path('realtime/bus');
        }

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory;
    }

    public function publish(array $payload): void
    {
        $encodedPayload = json_encode($payload);

        if ($encodedPayload === false) {
            return;
        }

        $filename = $this->directory().'/'.microtime(true).'_'.uniqid('', true).'.json';

        file_put_contents($filename, $encodedPayload, LOCK_EX);
    }

    public function listen(callable $callback, Closure $shouldStop): void
    {
        while (! $shouldStop()) {
            $files = glob($this->directory().'/*.json');

            if (is_array($files) && $files !== []) {
                sort($files);

                foreach ($files as $file) {
                    $contents = @file_get_contents($file);

                    if ($contents === false) {
                        @unlink($file);

                        continue;
                    }

                    $payload = json_decode($contents, true);

                    if (is_array($payload)) {
                        $callback($payload);
                    }

                    @unlink($file);
                }
            }

            usleep(self::POLL_INTERVAL_US);
        }
    }
}

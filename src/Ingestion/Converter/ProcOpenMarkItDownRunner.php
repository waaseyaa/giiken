<?php

declare(strict_types=1);

namespace App\Ingestion\Converter;

final class ProcOpenMarkItDownRunner implements MarkItDownRunnerInterface
{
    public function run(string $binaryPath, string $filePath, int $timeoutSeconds): string
    {
        $command = [$binaryPath, $filePath];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new ConversionException('Failed to start MarkItDown conversion process.');
        }

        try {
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $stdout = '';
            $stderr = '';
            $deadline = microtime(true) + $timeoutSeconds;

            while (true) {
                $stdout .= stream_get_contents($pipes[1]) ?: '';
                $stderr .= stream_get_contents($pipes[2]) ?: '';

                $status = proc_get_status($process);
                if (!is_array($status)) {
                    throw new ConversionException('Failed to read MarkItDown process status.');
                }

                if ($status['running'] !== true) {
                    break;
                }

                if (microtime(true) >= $deadline) {
                    proc_terminate($process);
                    throw new ConversionException(
                        "MarkItDown conversion timed out after {$timeoutSeconds} seconds.",
                    );
                }

                usleep(10_000);
            }

            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';

            $exitCode = proc_close($process);
            if ($exitCode !== 0) {
                throw new ConversionException("MarkItDown conversion failed (exit {$exitCode}).");
            }

            return $stdout;
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
        }
    }
}

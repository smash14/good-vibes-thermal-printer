<?php

// Runs server/scripts/image_converter.py (a deployed duplicate of
// main_code/image_converter.py's CLI) to convert a staged upload into an ESC/POS
// .bin file and, optionally, a preview .jpg - used for the interactive review step
// in ImageRepository so users can adjust contrast/threshold before saving.
final class ImageConverterRunner
{
    private string $pythonBinary;
    private string $scriptPath;

    public function __construct(string $pythonBinary, string $scriptPath)
    {
        $this->pythonBinary = $pythonBinary;
        $this->scriptPath = $scriptPath;
    }

    public function convert(
        string $inputPath,
        string $outputBinPath,
        ?string $outputPreviewPath,
        float $contrast,
        ?int $threshold,
        int $maxWidth,
        int $rotation = 0,
        float $brightness = 1.0,
        bool $autoContrast = false
    ): void {
        $command = [
            $this->pythonBinary,
            $this->scriptPath,
            $inputPath,
            '--output-bin', $outputBinPath,
            '--max-width', (string) $maxWidth,
            '--contrast', (string) $contrast,
            '--rotation', (string) $rotation,
            '--brightness', (string) $brightness,
        ];

        if ($outputPreviewPath !== null) {
            $command[] = '--output-preview';
            $command[] = $outputPreviewPath;
        }
        if ($threshold !== null) {
            $command[] = '--threshold';
            $command[] = (string) $threshold;
        }
        if ($autoContrast) {
            $command[] = '--auto-contrast';
        }

        // $command is passed as an array, so PHP execs the binary directly with no
        // shell involved - no command-injection surface regardless of file content.
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start image conversion process.');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $detail = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
            throw new RuntimeException('Image conversion failed: ' . $detail);
        }
    }
}

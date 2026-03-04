<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class FirmwareFlashService
{
    /**
     * @return array{
     *   ok: bool,
     *   mode: string,
     *   command: string,
     *   workdir: string,
     *   timeout_seconds: int,
     *   exit_code: int,
     *   output: string
     * }
     */
    public function runBuild(): array
    {
        return $this->run('build');
    }

    /**
     * @return array{
     *   ok: bool,
     *   mode: string,
     *   command: string,
     *   workdir: string,
     *   timeout_seconds: int,
     *   exit_code: int,
     *   output: string
     * }
     */
    public function runUpload(): array
    {
        return $this->run('upload');
    }

    /**
     * @return array{
     *   ok: bool,
     *   mode: string,
     *   command: string,
     *   workdir: string,
     *   timeout_seconds: int,
     *   exit_code: int,
     *   output: string
     * }
     */
    private function run(string $mode): array
    {
        $platformioCommand = trim((string) config('admin.platformio_command', 'pio'));
        if ($platformioCommand === '') {
            $platformioCommand = 'pio';
        }

        $workdir = trim((string) config('admin.platformio_workdir', base_path('ESP32_Firmware')));
        if ($workdir === '') {
            $workdir = base_path('ESP32_Firmware');
        }

        $timeoutSeconds = max(60, (int) config('admin.platformio_timeout_seconds', 900));
        $environment = $this->resolveBuildEnvironment();
        $command = $platformioCommand . ' run -e ' . $environment . ($mode === 'upload' ? ' -t upload' : '');

        try {
            $result = Process::path($workdir)
                ->timeout($timeoutSeconds)
                ->run($command);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'mode' => $mode,
                'command' => $command,
                'workdir' => $workdir,
                'timeout_seconds' => $timeoutSeconds,
                'exit_code' => 255,
                'output' => $this->truncateOutput('Failed to run PlatformIO command: ' . $e->getMessage()),
            ];
        }

        $combinedOutput = trim($result->output() . PHP_EOL . $result->errorOutput());
        if ($combinedOutput === '') {
            $combinedOutput = '(no command output)';
        }

        return [
            'ok' => $result->successful(),
            'mode' => $mode,
            'command' => $command,
            'workdir' => $workdir,
            'timeout_seconds' => $timeoutSeconds,
            'exit_code' => (int) $result->exitCode(),
            'output' => $this->truncateOutput($combinedOutput),
        ];
    }

    /**
     * @return array{
     *   environment: string,
     *   build_dir: string,
     *   required_missing: string[],
     *   images: array<int, array{name: string, address: int, path: string, size: int}>
     * }
     */
    public function resolveWebFlashArtifacts(): array
    {
        $environment = $this->resolveBuildEnvironment();
        $buildDir = $this->resolveBuildDirectory($environment);

        $catalog = [
            ['name' => 'bootloader', 'filename' => 'bootloader.bin', 'address' => 0x1000],
            ['name' => 'partitions', 'filename' => 'partitions.bin', 'address' => 0x8000],
            ['name' => 'firmware', 'filename' => 'firmware.bin', 'address' => 0x10000],
        ];

        $images = [];
        $missing = [];

        foreach ($catalog as $item) {
            $path = $buildDir . DIRECTORY_SEPARATOR . $item['filename'];
            if (!is_file($path)) {
                $missing[] = $item['name'];
                continue;
            }

            $images[] = [
                'name' => $item['name'],
                'address' => (int) $item['address'],
                'path' => $path,
                'size' => (int) filesize($path),
            ];
        }

        return [
            'environment' => $environment,
            'build_dir' => $buildDir,
            'required_missing' => $missing,
            'images' => $images,
        ];
    }

    public function findWebFlashArtifact(string $name): ?array
    {
        $artifactName = Str::lower(trim($name));
        $artifacts = $this->resolveWebFlashArtifacts();

        foreach ($artifacts['images'] as $image) {
            if ($image['name'] === $artifactName) {
                return $image;
            }
        }

        return null;
    }

    public function resolveBuildEnvironment(): string
    {
        $configured = trim((string) config('admin.platformio_env', ''));
        if ($configured !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $configured) === 1) {
            return $configured;
        }

        $workdir = trim((string) config('admin.platformio_workdir', base_path('ESP32_Firmware')));
        if ($workdir === '') {
            $workdir = base_path('ESP32_Firmware');
        }

        $iniPath = $workdir . DIRECTORY_SEPARATOR . 'platformio.ini';
        if (is_file($iniPath)) {
            $content = file_get_contents($iniPath) ?: '';
            if (preg_match('/^\[env:([^\]\r\n]+)\]/m', $content, $matches) === 1) {
                $parsed = trim((string) ($matches[1] ?? ''));
                if ($parsed !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $parsed) === 1) {
                    return $parsed;
                }
            }
        }

        return 'esp32doit-devkit-v1';
    }

    private function resolveBuildDirectory(string $environment): string
    {
        $workdir = trim((string) config('admin.platformio_workdir', base_path('ESP32_Firmware')));
        if ($workdir === '') {
            $workdir = base_path('ESP32_Firmware');
        }

        return $workdir . DIRECTORY_SEPARATOR . '.pio' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . $environment;
    }

    private function truncateOutput(string $output): string
    {
        $limit = max(2000, (int) config('admin.firmware_cli_output_limit', 60000));
        if (mb_strlen($output) <= $limit) {
            return $output;
        }

        $head = mb_substr($output, 0, $limit);

        return $head . PHP_EOL . '...[truncated]';
    }
}

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
        $workdir = trim((string) config('admin.platformio_workdir', base_path('ESP32_Firmware')));
        if ($workdir === '') {
            $workdir = base_path('ESP32_Firmware');
        }

        $projectValidationError = $this->validateProjectFilesBeforeBuild($workdir);
        if ($projectValidationError !== null) {
            return [
                'ok' => false,
                'mode' => $mode,
                'command' => 'validation',
                'workdir' => $workdir,
                'timeout_seconds' => 0,
                'exit_code' => 2,
                'output' => $this->truncateOutput($projectValidationError),
            ];
        }

        $timeoutSeconds = max(60, (int) config('admin.platformio_timeout_seconds', 900));
        $environment = $this->resolveBuildEnvironment();
        $candidateBaseCommands = $this->resolvePlatformioCommandCandidates();
        $attemptLogs = [];
        $finalResult = null;

        foreach ($candidateBaseCommands as $baseCommand) {
            $command = $baseCommand . ' run -e ' . $environment . ($mode === 'upload' ? ' -t upload' : '');

            try {
                $result = Process::path($workdir)
                    ->env($this->resolveProcessEnvironment())
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

            $combinedOutput = $this->normalizeOutput($result->output(), $result->errorOutput());
            $exitCode = (int) $result->exitCode();
            $isMissingCommand = $this->isMissingCommandError($exitCode, $combinedOutput);

            if ($result->successful()) {
                if ($attemptLogs !== []) {
                    $combinedOutput =
                        "PlatformIO fallback activated after command lookup failure(s)." . PHP_EOL .
                        implode(PHP_EOL . PHP_EOL, $attemptLogs) . PHP_EOL . PHP_EOL .
                        "=== Successful Command Output ===" . PHP_EOL .
                        $combinedOutput;
                }

                return [
                    'ok' => true,
                    'mode' => $mode,
                    'command' => $command,
                    'workdir' => $workdir,
                    'timeout_seconds' => $timeoutSeconds,
                    'exit_code' => $exitCode,
                    'output' => $this->truncateOutput($combinedOutput),
                ];
            }

            $finalResult = [
                'ok' => false,
                'mode' => $mode,
                'command' => $command,
                'workdir' => $workdir,
                'timeout_seconds' => $timeoutSeconds,
                'exit_code' => $exitCode,
                'output' => $combinedOutput,
            ];

            if (!$isMissingCommand) {
                if ($attemptLogs !== []) {
                    $finalResult['output'] =
                        "PlatformIO fallback attempt log:" . PHP_EOL .
                        implode(PHP_EOL . PHP_EOL, $attemptLogs) . PHP_EOL . PHP_EOL .
                        "=== Last Command Output ===" . PHP_EOL .
                        $combinedOutput;
                }
                $finalResult['output'] = $this->truncateOutput((string) $finalResult['output']);

                return $finalResult;
            }

            $attemptLogs[] = sprintf(
                '[%s] exit=%d%s%s',
                $command,
                $exitCode,
                PHP_EOL,
                $combinedOutput
            );
        }

        if ($finalResult === null) {
            return [
                'ok' => false,
                'mode' => $mode,
                'command' => 'n/a',
                'workdir' => $workdir,
                'timeout_seconds' => $timeoutSeconds,
                'exit_code' => 255,
                'output' => $this->truncateOutput('PlatformIO command candidate list is empty.'),
            ];
        }

        $checkedCommands = array_map(
            static fn (string $base): string => $base . ' run -e ' . $environment . ($mode === 'upload' ? ' -t upload' : ''),
            $candidateBaseCommands
        );

        $guidance = implode(PHP_EOL, [
            'PlatformIO CLI command was not found from Laravel runtime PATH.',
            'Set ADMIN_PLATFORMIO_COMMAND to an absolute executable path if needed.',
            'Checked commands:',
            '- ' . implode(PHP_EOL . '- ', $checkedCommands),
            '',
            'Example:',
            'ADMIN_PLATFORMIO_COMMAND=/home/your-user/.local/bin/pio',
        ]);

        return [
            'ok' => false,
            'mode' => $mode,
            'command' => (string) ($finalResult['command'] ?? 'n/a'),
            'workdir' => $workdir,
            'timeout_seconds' => $timeoutSeconds,
            'exit_code' => (int) ($finalResult['exit_code'] ?? 127),
            'output' => $this->truncateOutput(
                $guidance . PHP_EOL . PHP_EOL . implode(PHP_EOL . PHP_EOL, $attemptLogs)
            ),
        ];
    }

    private function validateProjectFilesBeforeBuild(string $workdir): ?string
    {
        $paths = [
            $workdir . DIRECTORY_SEPARATOR . 'platformio.ini',
            $workdir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'main.cpp',
        ];

        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $conflictLines = $this->findGitConflictMarkerLines($path);
            if ($conflictLines === []) {
                continue;
            }

            return implode(PHP_EOL, [
                'Git conflict marker terdeteksi di file project sebelum build.',
                'Selesaikan konflik lalu jalankan build ulang.',
                '',
                'File: ' . $path,
                'Lines: ' . implode(', ', $conflictLines),
                '',
                'Contoh marker: <<<<<<< ======= >>>>>>>',
            ]);
        }

        return null;
    }

    /**
     * @return int[]
     */
    private function findGitConflictMarkerLines(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines) || $lines === []) {
            return [];
        }

        $found = [];
        foreach ($lines as $idx => $line) {
            $trimmed = trim((string) $line);
            if (preg_match('/^(<<<<<<<|=======|>>>>>>>)(?:\s.*)?$/', $trimmed) === 1) {
                $found[] = $idx + 1;
            }
        }

        return $found;
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

    /**
     * @return string[]
     */
    private function resolvePlatformioCommandCandidates(): array
    {
        $configuredCommand = trim((string) config('admin.platformio_command', 'pio'));
        if ($configuredCommand === '') {
            $configuredCommand = 'pio';
        }

        $candidates = [
            $configuredCommand,
            'pio',
            'platformio',
            'python3 -m platformio',
            'python -m platformio',
            'py -m platformio',
        ];

        foreach ($this->resolveAbsolutePlatformioPaths() as $path) {
            $candidates[] = $this->quoteCommandPath($path);
        }

        return array_values(array_unique(array_filter(array_map('trim', $candidates), static function (string $value): bool {
            return $value !== '';
        })));
    }

    /**
     * @return string[]
     */
    private function resolveAbsolutePlatformioPaths(): array
    {
        $paths = [];

        if (DIRECTORY_SEPARATOR === '\\') {
            $appData = trim((string) getenv('APPDATA'));
            if ($appData !== '') {
                foreach (glob($appData . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python*' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'pio.exe') ?: [] as $path) {
                    $paths[] = $path;
                }
                foreach (glob($appData . DIRECTORY_SEPARATOR . 'Python' . DIRECTORY_SEPARATOR . 'Python*' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'platformio.exe') ?: [] as $path) {
                    $paths[] = $path;
                }
            }

            $userProfile = trim((string) getenv('USERPROFILE'));
            if ($userProfile !== '') {
                $paths[] = $userProfile . DIRECTORY_SEPARATOR . '.platformio' . DIRECTORY_SEPARATOR . 'penv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'pio.exe';
                $paths[] = $userProfile . DIRECTORY_SEPARATOR . '.platformio' . DIRECTORY_SEPARATOR . 'penv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'platformio.exe';
            }
        } else {
            $paths = array_merge($paths, [
                '/usr/local/bin/pio',
                '/usr/bin/pio',
                '/usr/local/bin/platformio',
                '/usr/bin/platformio',
                '/opt/homebrew/bin/pio',
                '/opt/homebrew/bin/platformio',
            ]);

            foreach ($this->resolveHomeDirectories() as $home) {
                $paths[] = $home . '/.local/bin/pio';
                $paths[] = $home . '/.local/bin/platformio';
                $paths[] = $home . '/.platformio/penv/bin/pio';
                $paths[] = $home . '/.platformio/penv/bin/platformio';
            }
        }

        $resolved = [];
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                $resolved[] = $path;
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @return string[]
     */
    private function resolveHomeDirectories(): array
    {
        $homes = [];

        $home = trim((string) getenv('HOME'));
        if ($home !== '') {
            $homes[] = $home;
        }

        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid(posix_geteuid());
            if (is_array($info)) {
                $posixHome = trim((string) ($info['dir'] ?? ''));
                if ($posixHome !== '') {
                    $homes[] = $posixHome;
                }
            }
        }

        return array_values(array_unique($homes));
    }

    private function normalizeOutput(string $stdout, string $stderr): string
    {
        $combinedOutput = trim($stdout . PHP_EOL . $stderr);
        if ($combinedOutput === '') {
            return '(no command output)';
        }

        return $combinedOutput;
    }

    private function isMissingCommandError(int $exitCode, string $output): bool
    {
        if ($exitCode === 127) {
            return true;
        }

        $normalized = strtolower($output);
        if (str_contains($normalized, '/bin/sh:') && str_contains($normalized, 'not found')) {
            return true;
        }

        $needles = [
            'command not found',
            'is not recognized as an internal or external command',
            'could not open input file: platformio',
            'no module named platformio',
            'platformio: not found',
            'pio: not found',
            'python3: not found',
            'python: not found',
            'py: not found',
        ];

        foreach ($needles as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function quoteCommandPath(string $path): string
    {
        if (!str_contains($path, ' ')) {
            return $path;
        }

        return '"' . str_replace('"', '\"', $path) . '"';
    }

    /**
     * Ensure PlatformIO subprocess has a complete executable lookup path.
     *
     * Some PM2/Laravel runtimes provide a very minimal PATH that misses `/bin`,
     * which causes PlatformIO/SCons child commands to fail with:
     * `sh: No such file or directory`.
     *
     * @return array<string, string>
     */
    private function resolveProcessEnvironment(): array
    {
        $pathSeparator = DIRECTORY_SEPARATOR === '\\' ? ';' : ':';
        $path = trim((string) getenv('PATH'));
        $pathItems = $path !== '' ? explode($pathSeparator, $path) : [];

        if (DIRECTORY_SEPARATOR === '\\') {
            $systemRoot = trim((string) getenv('SystemRoot'));
            if ($systemRoot !== '') {
                $pathItems[] = $systemRoot;
                $pathItems[] = $systemRoot . '\\System32';
                $pathItems[] = $systemRoot . '\\System32\\WindowsPowerShell\\v1.0';
            }
        } else {
            $pathItems = array_merge($pathItems, [
                '/usr/local/sbin',
                '/usr/local/bin',
                '/usr/sbin',
                '/usr/bin',
                '/sbin',
                '/bin',
            ]);
        }

        $normalizedPath = implode($pathSeparator, array_values(array_unique(array_filter(array_map(
            static fn (string $item): string => trim($item),
            $pathItems
        ), static fn (string $item): bool => $item !== ''))));

        $env = [
            'PATH' => $normalizedPath,
        ];

        if (DIRECTORY_SEPARATOR !== '\\' && is_file('/bin/sh')) {
            $env['SHELL'] = '/bin/sh';
        }

        $home = trim((string) getenv(DIRECTORY_SEPARATOR === '\\' ? 'USERPROFILE' : 'HOME'));
        if ($home !== '') {
            $env[DIRECTORY_SEPARATOR === '\\' ? 'USERPROFILE' : 'HOME'] = $home;
        }

        return $env;
    }
}

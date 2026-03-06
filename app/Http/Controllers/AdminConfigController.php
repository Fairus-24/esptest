<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdminSession;
use App\Models\Device;
use App\Services\AdminEnvironmentService;
use App\Services\FirmwareFlashService;
use App\Services\FirmwareTemplateService;
use App\Services\RuntimeConfigOverrideService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminConfigController extends Controller
{
    public function __construct(
        private readonly AdminEnvironmentService $adminEnvironmentService,
        private readonly RuntimeConfigOverrideService $runtimeConfigOverrideService,
        private readonly FirmwareTemplateService $firmwareTemplateService,
        private readonly FirmwareFlashService $firmwareFlashService
    ) {
    }

    public function loginForm()
    {
        $googleAuthConfig = $this->resolveGoogleAuthConfig();

        return view('admin-login', [
            'googleLoginConfigured' => $googleAuthConfig['configured'],
        ]);
    }

    public function redirectToGoogle(): RedirectResponse
    {
        $googleAuthConfig = $this->resolveGoogleAuthConfig();
        if (!$googleAuthConfig['configured']) {
            return redirect()
                ->route('admin.login')
                ->with('admin_error', 'Google admin login belum dikonfigurasi di server.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $googleAuthConfig = $this->resolveGoogleAuthConfig();
        if (!$googleAuthConfig['configured']) {
            return redirect()
                ->route('admin.login')
                ->with('admin_error', 'Google admin login belum dikonfigurasi di server.');
        }

        try {
            /** @var SocialiteUser $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google admin login callback failed.', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return redirect()
                ->route('admin.login')
                ->with('admin_error', 'Login Google gagal diproses. Coba lagi.');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        $allowedEmail = $googleAuthConfig['allowed_email'];
        if ($email === '' || $allowedEmail === '' || !hash_equals($allowedEmail, $email)) {
            return redirect()
                ->route('admin.login')
                ->with('admin_error', 'Akun Google ini tidak dapat digunakan untuk akses admin. Silakan hubungi administrator.');
        }

        $sessionKey = (string) config('admin.session_key', 'admin_config_authenticated');
        $request->session()->put($sessionKey, [
            'ok' => true,
            'at' => now()->timestamp,
            'ip' => $request->ip(),
            'provider' => 'google',
            'email' => $email,
            'name' => trim((string) ($googleUser->getName() ?? '')),
        ]);

        return redirect()
            ->intended(route('admin.config.index'))
            ->with('admin_status', 'Login admin via Google berhasil.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $sessionKey = (string) config('admin.session_key', 'admin_config_authenticated');
        $request->session()->forget($sessionKey);

        return redirect()
            ->route('admin.login')
            ->with('admin_status', 'Sesi admin ditutup.');
    }

    public function index(Request $request)
    {
        $settings = $this->adminEnvironmentService->getFormState();

        $devices = Device::query()
            ->withCount('eksperimens')
            ->orderBy('id')
            ->get();
        $selectedDevice = null;
        $selectedProfile = null;
        $renderedFirmware = null;
        $workspaceInSync = false;

        if ($devices->isNotEmpty()) {
            $selectedId = (int) $request->query('device_id', (int) $devices->first()->id);
            $selectedDevice = $devices->firstWhere('id', $selectedId) ?? $devices->first();
            if ($selectedDevice !== null) {
                $selectedProfile = $this->firmwareTemplateService->ensureProfile($selectedDevice);
                $renderedFirmware = $this->firmwareTemplateService->render($selectedDevice, $selectedProfile, $settings);
                $workspaceInSync = $this->firmwareTemplateService->isWorkspaceSynchronized($renderedFirmware);
            }
        }

        return view('admin-config', [
            'settings' => $settings,
            'devices' => $devices,
            'selectedDevice' => $selectedDevice,
            'selectedProfile' => $selectedProfile,
            'renderedFirmware' => $renderedFirmware,
            'workspaceInSync' => $workspaceInSync,
            'boardOptions' => (array) config('admin.board_options', []),
            'dhtModels' => (array) config('admin.dht_models', []),
            'adminSessionMiddleware' => EnsureAdminSession::class,
        ]);
    }

    public function saveRuntime(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->adminEnvironmentService->validationRules());
        $this->adminEnvironmentService->saveOverrides($validated, $request->ip());
        $this->runtimeConfigOverrideService->apply();

        return back()->with('admin_status', 'Runtime environment overrides berhasil disimpan.');
    }

    public function storeDevice(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_device' => ['required', 'string', 'max:120', Rule::unique('devices', 'nama_device')],
            'lokasi' => ['nullable', 'string', 'max:160'],
            'clone_profile_from_device_id' => ['nullable', 'integer', 'exists:devices,id'],
        ]);

        $device = Device::query()->create([
            'nama_device' => trim((string) $validated['nama_device']),
            'lokasi' => isset($validated['lokasi']) ? trim((string) $validated['lokasi']) : null,
        ]);

        $targetProfile = $this->firmwareTemplateService->ensureProfile($device);
        $cloneFromDeviceId = (int) ($validated['clone_profile_from_device_id'] ?? 0);
        if ($cloneFromDeviceId > 0 && $cloneFromDeviceId !== (int) $device->id) {
            $sourceProfile = $this->firmwareTemplateService->findProfileByDeviceId($cloneFromDeviceId);
            if ($sourceProfile !== null) {
                $targetProfile->fill(
                    Arr::only($sourceProfile->toArray(), [
                        'board',
                        'wifi_ssid',
                        'wifi_password',
                        'server_host',
                        'http_base_url',
                        'http_endpoint',
                        'mqtt_broker',
                        'mqtt_host',
                        'mqtt_port',
                        'mqtt_topic',
                        'mqtt_user',
                        'mqtt_password',
                        'http_tls_insecure',
                        'http_read_timeout_ms',
                        'dht_pin',
                        'dht_model',
                        'sensor_interval_ms',
                        'http_interval_ms',
                        'mqtt_interval_ms',
                        'dht_min_read_interval_ms',
                        'core_debug_level',
                        'mqtt_max_packet_size',
                        'monitor_speed',
                        'monitor_port',
                        'upload_port',
                        'extra_build_flags',
                    ])
                );
                $targetProfile->save();
            }
        }

        return redirect()
            ->route('admin.config.index', ['device_id' => $device->id])
            ->with('admin_status', 'Device baru berhasil ditambahkan.');
    }

    public function updateDevice(Request $request, Device $device): RedirectResponse
    {
        $validated = $request->validate([
            'nama_device' => [
                'required',
                'string',
                'max:120',
                Rule::unique('devices', 'nama_device')->ignore($device->id),
            ],
            'lokasi' => ['nullable', 'string', 'max:160'],
        ]);

        $device->fill([
            'nama_device' => trim((string) $validated['nama_device']),
            'lokasi' => isset($validated['lokasi']) ? trim((string) $validated['lokasi']) : null,
        ]);
        $device->save();

        return redirect()
            ->route('admin.config.index', ['device_id' => $device->id])
            ->with('admin_status', 'Data device berhasil diperbarui.');
    }

    public function destroyDevice(Request $request, Device $device): RedirectResponse
    {
        $validated = $request->validate([
            'confirm_delete' => ['required', 'string', Rule::in(['DELETE'])],
            'purge_experiments' => ['nullable', 'in:0,1,true,false,on,off,yes,no'],
        ]);

        $hasMeasurements = $device->eksperimens()->exists();
        $purgeExperiments = filter_var($validated['purge_experiments'] ?? false, FILTER_VALIDATE_BOOL);

        if ($hasMeasurements && !$purgeExperiments) {
            return redirect()
                ->route('admin.config.index', ['device_id' => $device->id])
                ->withErrors([
                    'device_delete' => 'Device masih memiliki data eksperimen. Centang purge data terlebih dahulu atau gunakan device lain.',
                ]);
        }

        try {
            DB::transaction(function () use ($device, $purgeExperiments): void {
                if ($purgeExperiments) {
                    $device->eksperimens()->delete();
                }
                $device->delete();
            });
        } catch (QueryException) {
            return redirect()
                ->route('admin.config.index', ['device_id' => $device->id])
                ->withErrors([
                    'device_delete' => 'Gagal menghapus device karena masih terhubung dengan data lain.',
                ]);
        }

        $nextDevice = Device::query()->orderBy('id')->first();
        $routeParams = $nextDevice !== null ? ['device_id' => $nextDevice->id] : [];

        return redirect()
            ->route('admin.config.index', $routeParams)
            ->with('admin_status', 'Device berhasil dihapus.');
    }

    public function saveDeviceProfile(Request $request, Device $device): RedirectResponse
    {
        $boardOptions = (array) config('admin.board_options', []);
        $dhtModels = (array) config('admin.dht_models', []);

        $validated = $request->validate([
            'board' => ['required', 'string', Rule::in($boardOptions)],
            'wifi_ssid' => ['required', 'string', 'max:120'],
            'wifi_password' => ['required', 'string', 'max:120'],
            'http_base_url' => ['required', 'string', 'max:200'],
            'http_endpoint' => ['required', 'string', 'max:160'],
            'mqtt_broker' => ['required', 'string', 'max:120'],
            'mqtt_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mqtt_topic' => ['required', 'string', 'max:120'],
            'mqtt_user' => ['required', 'string', 'max:80'],
            'mqtt_password' => ['required', 'string', 'max:120'],
            'http_tls_insecure' => ['required', 'in:0,1,true,false,on,off,yes,no'],
            'http_read_timeout_ms' => ['nullable', 'integer', 'min:1000', 'max:120000'],
            'dht_pin' => ['required', 'integer', 'min:0', 'max:39'],
            'dht_model' => ['required', 'string', Rule::in($dhtModels)],
            'sensor_interval_ms' => ['nullable', 'integer', 'min:500', 'max:3600000'],
            'http_interval_ms' => ['nullable', 'integer', 'min:500', 'max:3600000'],
            'mqtt_interval_ms' => ['nullable', 'integer', 'min:500', 'max:3600000'],
            'dht_min_read_interval_ms' => ['nullable', 'integer', 'min:250', 'max:120000'],
            'core_debug_level' => ['nullable', 'integer', 'min:0', 'max:5'],
            'mqtt_max_packet_size' => ['nullable', 'integer', 'min:256', 'max:65535'],
            'monitor_speed' => ['nullable', 'integer', 'min:1200', 'max:3000000'],
            'monitor_port' => ['nullable', 'string', 'max:80'],
            'upload_port' => ['nullable', 'string', 'max:80'],
            'extra_build_flags' => ['nullable', 'string', 'max:4000'],
        ]);

        $profile = $this->firmwareTemplateService->ensureProfile($device);
        $validated['mqtt_broker'] = trim((string) $validated['mqtt_broker']);

        $httpBaseUrl = rtrim(trim((string) ($validated['http_base_url'] ?? '')), '/');
        $parsedServerHost = (string) (parse_url($httpBaseUrl, PHP_URL_HOST) ?: '');

        if ($parsedServerHost === '') {
            return redirect()
                ->route('admin.config.index', ['device_id' => $device->id])
                ->withInput()
                ->withErrors([
                    'http_base_url' => 'HTTP Base URL tidak valid. Host tidak dapat dibaca.',
                ]);
        }

        if ($this->isUnsafeFirmwareTargetHost($parsedServerHost)) {
            return redirect()
                ->route('admin.config.index', ['device_id' => $device->id])
                ->withInput()
                ->withErrors([
                    'http_base_url' => 'HTTP Base URL host tidak boleh localhost/loopback untuk firmware ESP32.',
                ]);
        }

        if ($this->isUnsafeFirmwareTargetHost($validated['mqtt_broker'])) {
            return redirect()
                ->route('admin.config.index', ['device_id' => $device->id])
                ->withInput()
                ->withErrors([
                    'mqtt_broker' => 'MQTT Broker tidak boleh localhost/loopback atau placeholder macro.',
                ]);
        }

        $validated['server_host'] = $parsedServerHost !== ''
            ? $parsedServerHost
            : trim((string) ($validated['mqtt_broker'] ?? '127.0.0.1'));
        $validated['mqtt_host'] = trim((string) ($validated['mqtt_broker'] ?? '127.0.0.1'));
        $validated['http_base_url'] = $httpBaseUrl;
        $validated['http_tls_insecure'] = filter_var($validated['http_tls_insecure'], FILTER_VALIDATE_BOOL);
        $validated['http_read_timeout_ms'] = (int) ($validated['http_read_timeout_ms'] ?? $profile->http_read_timeout_ms ?? 5000);
        $validated['sensor_interval_ms'] = (int) ($validated['sensor_interval_ms'] ?? $profile->sensor_interval_ms ?? 5000);
        $validated['http_interval_ms'] = (int) ($validated['http_interval_ms'] ?? $profile->http_interval_ms ?? 10000);
        $validated['mqtt_interval_ms'] = (int) ($validated['mqtt_interval_ms'] ?? $profile->mqtt_interval_ms ?? 10000);
        $validated['dht_min_read_interval_ms'] = (int) ($validated['dht_min_read_interval_ms'] ?? $profile->dht_min_read_interval_ms ?? 1500);
        $validated['core_debug_level'] = (int) ($validated['core_debug_level'] ?? $profile->core_debug_level ?? 0);
        $validated['mqtt_max_packet_size'] = (int) ($validated['mqtt_max_packet_size'] ?? $profile->mqtt_max_packet_size ?? 2048);
        $validated['monitor_speed'] = (int) ($validated['monitor_speed'] ?? $profile->monitor_speed ?? 115200);
        $validated['monitor_port'] = trim((string) ($validated['monitor_port'] ?? ''));
        $validated['upload_port'] = trim((string) ($validated['upload_port'] ?? ''));
        $validated['monitor_port'] = $validated['monitor_port'] !== '' ? $validated['monitor_port'] : null;
        $validated['upload_port'] = $validated['upload_port'] !== '' ? $validated['upload_port'] : null;
        $extraFlagsRaw = (string) ($validated['extra_build_flags'] ?? '');
        [$sanitizedExtraFlags, $droppedManagedFlags] = $this->sanitizeExtraBuildFlags($extraFlagsRaw);
        $validated['extra_build_flags'] = $sanitizedExtraFlags !== '' ? $sanitizedExtraFlags : null;

        $profile->fill($validated);
        $profile->save();

        $statusMessage = 'Profil firmware device berhasil diperbarui.';
        if ($droppedManagedFlags !== []) {
            $statusMessage .= ' Beberapa macro build dikelola otomatis dan diabaikan: ' . implode(', ', $droppedManagedFlags) . '.';
        }

        return redirect()
            ->route('admin.config.index', ['device_id' => $device->id])
            ->with('admin_status', $statusMessage);
    }

    public function downloadMain(Device $device): StreamedResponse
    {
        $bundle = $this->prepareFirmwareBundle($device);
        $filename = 'main.device-' . $device->id . '.cpp';

        return response()->streamDownload(function () use ($bundle): void {
            echo $bundle['main_cpp'];
        }, $filename, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function downloadPlatformio(Device $device): StreamedResponse
    {
        $bundle = $this->prepareFirmwareBundle($device);
        $filename = 'platformio.device-' . $device->id . '.ini';

        return response()->streamDownload(function () use ($bundle): void {
            echo $bundle['platformio_ini'];
        }, $filename, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function editFirmwareSource(Device $device, string $target)
    {
        $targetMeta = $this->resolveFirmwareEditorTarget($target);
        $settings = $this->adminEnvironmentService->getFormState();
        $profile = $this->firmwareTemplateService->ensureProfile($device);
        $standardBundle = $this->firmwareTemplateService->renderStandard($device, $profile, $settings);
        $bundle = $this->firmwareTemplateService->render($device, $profile, $settings);
        $profileColumn = (string) $targetMeta['profile_column'];
        $bundleKey = (string) $targetMeta['bundle_key'];

        return view('admin-firmware-editor', [
            'device' => $device,
            'profile' => $profile,
            'targetMeta' => $targetMeta,
            'content' => old('content', (string) ($bundle[$bundleKey] ?? '')),
            'standardContent' => (string) ($standardBundle[$bundleKey] ?? ''),
            'isCustomOverride' => $this->hasFirmwareSourceOverride($profile->{$profileColumn} ?? null),
            'workspacePaths' => [
                'main_cpp' => base_path('ESP32_Firmware/src/main.cpp'),
                'platformio_ini' => base_path('ESP32_Firmware/platformio.ini'),
            ],
        ]);
    }

    public function saveFirmwareSource(Request $request, Device $device, string $target): RedirectResponse
    {
        $targetMeta = $this->resolveFirmwareEditorTarget($target);
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:500000'],
        ]);

        $settings = $this->adminEnvironmentService->getFormState();
        $profile = $this->firmwareTemplateService->ensureProfile($device);
        $standardBundle = $this->firmwareTemplateService->renderStandard($device, $profile, $settings);
        $bundleKey = (string) $targetMeta['bundle_key'];
        $profileColumn = (string) $targetMeta['profile_column'];
        $fileLabel = (string) $targetMeta['file_label'];
        $normalizedContent = $this->normalizeFirmwareSourceContent((string) $validated['content']);

        if (trim($normalizedContent) === '') {
            return redirect()
                ->route('admin.config.devices.firmware.editor', [
                    'device' => $device->id,
                    'target' => $target,
                ])
                ->withInput()
                ->withErrors([
                    'content' => $fileLabel . ' tidak boleh kosong.',
                ]);
        }

        $standardContent = $this->normalizeFirmwareSourceContent((string) ($standardBundle[$bundleKey] ?? ''));
        $isGeneratedStandard = $normalizedContent === $standardContent;

        $profile->{$profileColumn} = $isGeneratedStandard ? null : $normalizedContent;
        $profile->save();

        $statusMessage = $isGeneratedStandard
            ? 'Generated standard ' . $fileLabel . ' dipulihkan untuk device ini.'
            : 'Custom ' . $fileLabel . ' berhasil disimpan untuk device ini.';

        try {
            $bundle = $this->firmwareTemplateService->render($device, $profile, $settings);
            $applyResult = $this->firmwareTemplateService->applyToWorkspace($device, $bundle);

            return redirect()
                ->route('admin.config.devices.firmware.editor', [
                    'device' => $device->id,
                    'target' => $target,
                ])
                ->with('admin_status', $statusMessage . ' Workspace sinkron. Backup: ' . $applyResult['backup_dir']);
        } catch (Throwable $e) {
            Log::error('Firmware source editor failed to synchronize workspace.', [
                'device_id' => $device->id,
                'target' => $target,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.config.devices.firmware.editor', [
                    'device' => $device->id,
                    'target' => $target,
                ])
                ->with('admin_status', $statusMessage)
                ->withErrors([
                    'firmware_editor' => 'Source override tersimpan, tetapi sinkronisasi ke workspace gagal. Jalankan Apply to Workspace dari panel admin setelah mengecek permission file.',
                ]);
        }
    }

    public function applyFirmware(Device $device): RedirectResponse
    {
        $bundle = $this->prepareFirmwareBundle($device);
        $result = $this->firmwareTemplateService->applyToWorkspace($device, $bundle);

        return redirect()
            ->route('admin.config.index', ['device_id' => $device->id])
            ->with('admin_status', 'Template firmware diterapkan ke workspace. Backup: ' . $result['backup_dir']);
    }

    public function buildFirmware(Device $device): RedirectResponse
    {
        return $this->runFirmwareCliCommand($device, 'build');
    }

    public function uploadFirmware(Device $device): RedirectResponse
    {
        return $this->runFirmwareCliCommand($device, 'upload');
    }

    public function prepareWebFlash(Device $device): JsonResponse
    {
        try {
            // Always regenerate latest source before creating browser-flash artifacts.
            $bundle = $this->prepareFirmwareBundle($device);
            $applyResult = $this->firmwareTemplateService->applyToWorkspace($device, $bundle);
        } catch (Throwable $e) {
            Log::error('Webflash preparation failed on workspace apply stage.', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Gagal menyiapkan source firmware terbaru di workspace.',
            ], 500);
        }

        $build = $this->firmwareFlashService->runBuild();
        if (!$build['ok']) {
            return response()->json([
                'ok' => false,
                'message' => 'Build firmware gagal. Cek output build pada response.',
                'build' => $build,
            ], 422);
        }

        $artifacts = $this->firmwareFlashService->resolveWebFlashArtifacts();
        if ($artifacts['required_missing'] !== []) {
            return response()->json([
                'ok' => false,
                'message' => 'Build selesai tetapi artifact binary wajib belum lengkap.',
                'missing' => $artifacts['required_missing'],
                'artifacts' => $artifacts,
                'build' => $build,
            ], 422);
        }

        $images = collect($artifacts['images'])
            ->map(function (array $image) use ($device): array {
                return [
                    'name' => $image['name'],
                    'address' => (int) $image['address'],
                    'size' => (int) $image['size'],
                    'url' => route('admin.config.devices.firmware.webflash.artifact', [
                        'device' => $device->id,
                        'artifact' => $image['name'],
                    ], false),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'message' => 'Artifact web flash siap digunakan dari browser client.',
            'device_id' => $device->id,
            'build' => $build,
            'environment' => $artifacts['environment'],
            'build_dir' => $artifacts['build_dir'],
            'backup_dir' => (string) ($applyResult['backup_dir'] ?? ''),
            'images' => $images,
        ]);
    }

    public function downloadWebFlashArtifact(Device $device, string $artifact): BinaryFileResponse
    {
        $resolved = $this->firmwareFlashService->findWebFlashArtifact($artifact);
        if ($resolved === null) {
            abort(404, 'Artifact firmware tidak ditemukan. Jalankan build terlebih dahulu.');
        }

        $filename = 'esp32-' . $device->id . '-' . $resolved['name'] . '.bin';

        return response()->download((string) $resolved['path'], $filename, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function prepareFirmwareBundle(Device $device): array
    {
        $settings = $this->adminEnvironmentService->getFormState();
        $profile = $this->firmwareTemplateService->ensureProfile($device);

        return $this->firmwareTemplateService->render($device, $profile, $settings);
    }

    /**
     * @return array{
     *   target: string,
     *   bundle_key: string,
     *   profile_column: string,
     *   file_label: string,
     *   language: string,
     *   description: string
     * }
     */
    private function resolveFirmwareEditorTarget(string $target): array
    {
        return match ($target) {
            'main-cpp' => [
                'target' => 'main-cpp',
                'bundle_key' => 'main_cpp',
                'profile_column' => 'custom_main_cpp',
                'file_label' => 'main.cpp',
                'language' => 'cpp',
                'description' => 'Full Arduino source for the selected device firmware.',
            ],
            'platformio-ini' => [
                'target' => 'platformio-ini',
                'bundle_key' => 'platformio_ini',
                'profile_column' => 'custom_platformio_ini',
                'file_label' => 'platformio.ini',
                'language' => 'ini',
                'description' => 'Full PlatformIO project configuration for the selected device.',
            ],
            default => abort(404),
        };
    }

    private function hasFirmwareSourceOverride(mixed $value): bool
    {
        return is_string($value) && trim($this->normalizeFirmwareSourceContent($value)) !== '';
    }

    private function normalizeFirmwareSourceContent(string $content): string
    {
        return str_replace(["\r\n", "\r"], "\n", $content);
    }

    private function runFirmwareCliCommand(Device $device, string $mode): RedirectResponse
    {
        $modeLabel = $mode === 'upload' ? 'Upload firmware' : 'Build firmware';
        $route = redirect()->route('admin.config.index', ['device_id' => $device->id]);

        try {
            // Always regenerate + apply first so CLI operation runs on latest profile output.
            $bundle = $this->prepareFirmwareBundle($device);
            $applyResult = $this->firmwareTemplateService->applyToWorkspace($device, $bundle);
        } catch (Throwable $e) {
            Log::error('Firmware workspace apply failed before CLI command.', [
                'mode' => $mode,
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);

            return $route->withErrors([
                'firmware_cli' => $modeLabel . ' gagal: tidak dapat menyiapkan file firmware di workspace.',
            ]);
        }

        $run = $mode === 'upload'
            ? $this->firmwareFlashService->runUpload()
            : $this->firmwareFlashService->runBuild();

        $resultPayload = [
            'mode' => $run['mode'],
            'ok' => (bool) $run['ok'],
            'command' => (string) $run['command'],
            'workdir' => (string) $run['workdir'],
            'timeout_seconds' => (int) $run['timeout_seconds'],
            'exit_code' => (int) $run['exit_code'],
            'output' => (string) $run['output'],
            'backup_dir' => (string) ($applyResult['backup_dir'] ?? ''),
        ];

        if ($run['ok']) {
            return $route
                ->with('admin_status', $modeLabel . ' berhasil dijalankan.')
                ->with('firmware_cli_result', $resultPayload);
        }

        $errorMessage = $modeLabel . ' gagal (exit code ' . (int) $run['exit_code'] . ').';
        $outputLower = strtolower((string) ($run['output'] ?? ''));
        if ((int) $run['exit_code'] === 127) {
            $errorMessage = $modeLabel . ' gagal: PlatformIO CLI tidak ditemukan pada runtime server (exit code 127).';
        } elseif ($mode === 'upload' && (
            str_contains($outputLower, 'upload_port') ||
            str_contains($outputLower, 'serial port') ||
            str_contains($outputLower, 'could not open port') ||
            str_contains($outputLower, 'could not find the file') ||
            str_contains($outputLower, 'resource busy') ||
            str_contains($outputLower, 'permission denied')
        )) {
            $errorMessage = 'Upload firmware gagal: port USB serial tidak tersedia/terkunci di server. '
                . 'Jika server remote tanpa USB, gunakan Web Flash dari browser client.';
        }

        return $route
            ->withErrors([
                'firmware_cli' => $errorMessage,
            ])
            ->with('firmware_cli_result', $resultPayload);
    }

    /**
     * @return array{configured: bool, allowed_email: string}
     */
    private function resolveGoogleAuthConfig(): array
    {
        $clientId = trim((string) config('services.google.client_id', ''));
        $clientSecret = trim((string) config('services.google.client_secret', ''));
        $redirectUri = trim((string) config('services.google.redirect', ''));
        $allowedEmail = strtolower(trim((string) config('admin.google_allowed_email', '')));

        return [
            'configured' => $clientId !== '' && $clientSecret !== '' && $redirectUri !== '' && $allowedEmail !== '',
            'allowed_email' => $allowedEmail,
        ];
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function sanitizeExtraBuildFlags(string $extraFlags): array
    {
        $reservedFlags = $this->reservedManagedBuildFlags();
        $reservedMap = [];
        foreach ($reservedFlags as $flag) {
            $reservedMap[strtoupper($flag)] = true;
        }

        $sanitizedLines = [];
        $droppedManagedFlags = [];
        $lines = preg_split('/\r\n|\r|\n/', $extraFlags) ?: [];

        foreach ($lines as $line) {
            $trimmedLine = trim((string) $line);
            if ($trimmedLine === '') {
                continue;
            }

            if (preg_match('/^-D([A-Za-z_][A-Za-z0-9_]*)(?:=.*)?$/', $trimmedLine, $matches) === 1) {
                $macroName = strtoupper((string) ($matches[1] ?? ''));
                if ($macroName !== '' && isset($reservedMap[$macroName])) {
                    $droppedManagedFlags[$macroName] = true;
                    continue;
                }
            }

            $sanitizedLines[] = $trimmedLine;
        }

        return [
            implode(PHP_EOL, $sanitizedLines),
            array_keys($droppedManagedFlags),
        ];
    }

    /**
     * @return list<string>
     */
    private function reservedManagedBuildFlags(): array
    {
        return [
            'ESP_DEVICE_ID',
            'ESP_DHT_PIN',
            'ESP_WIFI_SSID',
            'ESP_WIFI_PASSWORD',
            'ESP_HTTP_INGEST_KEY',
            'ESP_HTTP_BASE_URL',
            'ESP_HTTP_ENDPOINT',
            'ESP_MQTT_BROKER',
            'ESP_MQTT_PORT',
            'ESP_MQTT_TOPIC',
            'ESP_MQTT_USER',
            'ESP_MQTT_PASSWORD',
            'ESP_HTTP_TLS_INSECURE',
            'ESP_HTTP_READ_TIMEOUT_MS',
            'ESP_SENSOR_INTERVAL_MS',
            'ESP_HTTP_INTERVAL_MS',
            'ESP_MQTT_INTERVAL_MS',
            'ESP_DHT_MIN_READ_INTERVAL_MS',
            'CORE_DEBUG_LEVEL',
            'HTTP_CLIENT_TIMEOUT',
            'MQTT_MAX_PACKET_SIZE',
        ];
    }

    private function isUnsafeFirmwareTargetHost(string $host): bool
    {
        $candidate = strtolower(trim($host));
        if ($candidate === '') {
            return true;
        }

        if (in_array($candidate, ['localhost', '127.0.0.1', '::1', '0.0.0.0', 'esp_mqtt_broker'], true)) {
            return true;
        }

        if (str_starts_with($candidate, '127.')) {
            return true;
        }

        return false;
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdminSession;
use App\Models\Device;
use App\Services\AdminEnvironmentService;
use App\Services\FirmwareTemplateService;
use App\Services\RuntimeConfigOverrideService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminConfigController extends Controller
{
    public function __construct(
        private readonly AdminEnvironmentService $adminEnvironmentService,
        private readonly RuntimeConfigOverrideService $runtimeConfigOverrideService,
        private readonly FirmwareTemplateService $firmwareTemplateService
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
        $envSnippet = $this->adminEnvironmentService->renderEnvSnippet();

        $devices = Device::query()
            ->withCount('eksperimens')
            ->orderBy('id')
            ->get();
        $selectedDevice = null;
        $selectedProfile = null;
        $renderedFirmware = null;

        if ($devices->isNotEmpty()) {
            $selectedId = (int) $request->query('device_id', (int) $devices->first()->id);
            $selectedDevice = $devices->firstWhere('id', $selectedId) ?? $devices->first();
            if ($selectedDevice !== null) {
                $selectedProfile = $this->firmwareTemplateService->ensureProfile($selectedDevice);
                $renderedFirmware = $this->firmwareTemplateService->render($selectedDevice, $selectedProfile, $settings);
            }
        }

        return view('admin-config', [
            'settings' => $settings,
            'envSnippet' => $envSnippet,
            'devices' => $devices,
            'selectedDevice' => $selectedDevice,
            'selectedProfile' => $selectedProfile,
            'renderedFirmware' => $renderedFirmware,
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
                        'dht_pin',
                        'dht_model',
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
            'server_host' => ['nullable', 'string', 'max:120'],
            'http_base_url' => ['required', 'string', 'max:200'],
            'http_endpoint' => ['required', 'string', 'max:160'],
            'mqtt_broker' => ['required', 'string', 'max:120'],
            'mqtt_host' => ['nullable', 'string', 'max:120'],
            'mqtt_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mqtt_topic' => ['required', 'string', 'max:120'],
            'mqtt_user' => ['required', 'string', 'max:80'],
            'mqtt_password' => ['required', 'string', 'max:120'],
            'http_tls_insecure' => ['required', 'in:0,1,true,false,on,off,yes,no'],
            'dht_pin' => ['required', 'integer', 'min:0', 'max:39'],
            'dht_model' => ['required', 'string', Rule::in($dhtModels)],
            'extra_build_flags' => ['nullable', 'string', 'max:4000'],
        ]);

        $httpBaseUrl = rtrim(trim((string) ($validated['http_base_url'] ?? '')), '/');
        $parsedServerHost = (string) (parse_url($httpBaseUrl, PHP_URL_HOST) ?: '');
        $serverHost = trim((string) ($validated['server_host'] ?? ''));
        if ($serverHost === '' && $parsedServerHost !== '') {
            $serverHost = $parsedServerHost;
        }
        $validated['server_host'] = $serverHost !== '' ? $serverHost : trim((string) ($validated['mqtt_broker'] ?? '127.0.0.1'));

        $mqttHost = trim((string) ($validated['mqtt_host'] ?? ''));
        if ($mqttHost === '') {
            $mqttHost = trim((string) ($validated['mqtt_broker'] ?? '127.0.0.1'));
        }
        $validated['mqtt_host'] = $mqttHost;
        $validated['http_base_url'] = $httpBaseUrl;
        $validated['mqtt_broker'] = trim((string) $validated['mqtt_broker']);
        $validated['http_tls_insecure'] = filter_var($validated['http_tls_insecure'], FILTER_VALIDATE_BOOL);

        $profile = $this->firmwareTemplateService->ensureProfile($device);
        $profile->fill($validated);
        $profile->save();

        return redirect()
            ->route('admin.config.index', ['device_id' => $device->id])
            ->with('admin_status', 'Profil firmware device berhasil diperbarui.');
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

    public function applyFirmware(Device $device): RedirectResponse
    {
        $bundle = $this->prepareFirmwareBundle($device);
        $result = $this->firmwareTemplateService->applyToWorkspace($device, $bundle);

        return redirect()
            ->route('admin.config.index', ['device_id' => $device->id])
            ->with('admin_status', 'Template firmware diterapkan ke workspace. Backup: ' . $result['backup_dir']);
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
}

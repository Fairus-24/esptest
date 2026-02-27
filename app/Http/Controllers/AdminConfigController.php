<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdminSession;
use App\Models\Device;
use App\Services\AdminEnvironmentService;
use App\Services\FirmwareTemplateService;
use App\Services\RuntimeConfigOverrideService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        return view('admin-login', [
            'allowWithoutToken' => (bool) config('admin.allow_without_token', false),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['nullable', 'string', 'max:255'],
        ]);

        $expectedToken = trim((string) config('admin.panel_token', ''));
        $allowWithoutToken = (bool) config('admin.allow_without_token', false);
        $providedToken = trim((string) ($validated['token'] ?? ''));

        if ($expectedToken === '' && !$allowWithoutToken) {
            return back()->withErrors([
                'token' => 'ADMIN_PANEL_TOKEN belum dikonfigurasi di server.',
            ]);
        }

        if ($expectedToken !== '' && !hash_equals($expectedToken, $providedToken)) {
            return back()->withErrors([
                'token' => 'Token admin tidak valid.',
            ]);
        }

        $sessionKey = (string) config('admin.session_key', 'admin_config_authenticated');
        $request->session()->put($sessionKey, [
            'ok' => true,
            'at' => now()->timestamp,
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->intended(route('admin.config.index'))
            ->with('admin_status', 'Login admin berhasil.');
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

        $devices = Device::query()->orderBy('id')->get();
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
        ]);

        $device = Device::query()->create([
            'nama_device' => trim((string) $validated['nama_device']),
            'lokasi' => isset($validated['lokasi']) ? trim((string) $validated['lokasi']) : null,
        ]);

        $this->firmwareTemplateService->ensureProfile($device);

        return redirect()
            ->route('admin.config.index', ['device_id' => $device->id])
            ->with('admin_status', 'Device baru berhasil ditambahkan.');
    }

    public function saveDeviceProfile(Request $request, Device $device): RedirectResponse
    {
        $boardOptions = (array) config('admin.board_options', []);
        $dhtModels = (array) config('admin.dht_models', []);

        $validated = $request->validate([
            'board' => ['required', 'string', Rule::in($boardOptions)],
            'wifi_ssid' => ['required', 'string', 'max:120'],
            'wifi_password' => ['required', 'string', 'max:120'],
            'server_host' => ['required', 'string', 'max:120'],
            'http_endpoint' => ['required', 'string', 'max:160'],
            'mqtt_host' => ['required', 'string', 'max:120'],
            'mqtt_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mqtt_topic' => ['required', 'string', 'max:120'],
            'mqtt_user' => ['required', 'string', 'max:80'],
            'mqtt_password' => ['required', 'string', 'max:120'],
            'dht_pin' => ['required', 'integer', 'min:0', 'max:39'],
            'dht_model' => ['required', 'string', Rule::in($dhtModels)],
            'extra_build_flags' => ['nullable', 'string', 'max:4000'],
        ]);

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
}


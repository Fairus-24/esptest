<?php

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        Device::query()->create([
            'nama_device' => 'ESP32-1',
            'lokasi' => 'Lab',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}

<?php

namespace App\Services;

use Throwable;

class RuntimeConfigOverrideService
{
    public function __construct(
        private readonly AdminEnvironmentService $adminEnvironmentService
    ) {
    }

    public function apply(): void
    {
        try {
            $overrides = $this->adminEnvironmentService->resolveConfigOverrides();
            if ($overrides === []) {
                return;
            }

            config($overrides);
        } catch (Throwable) {
            // Safe fallback: runtime override is optional and should never crash request lifecycle.
        }
    }
}


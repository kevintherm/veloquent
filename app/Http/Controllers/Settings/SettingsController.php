<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Settings\TenantSettingsService;
use App\Http\Requests\UpdateSettingsRequest;
use App\Infrastructure\Http\Controllers\ApiController;

class SettingsController extends ApiController
{
    public function __construct(
        private readonly TenantSettingsService $settingsService
    ) {}

    public function index()
    {
        return $this->successResponse($this->settingsService->get());
    }

    public function update(UpdateSettingsRequest $request)
    {
        $this->settingsService->update($request->validated());

        return $this->successResponse($this->settingsService->get(), 'Settings updated successfully.');
    }
}

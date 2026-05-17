<?php

namespace Veloquent\Core\Support\Http\Controllers;

use Veloquent\Core\Domain\Settings\TenantSettingsService;
use Veloquent\Core\Support\Http\Controllers\ApiController;
use Veloquent\Core\Support\Http\Requests\UpdateSettingsRequest;

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

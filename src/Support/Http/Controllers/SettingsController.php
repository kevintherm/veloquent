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
        return $this->successResponse($this->maskSensitiveSettings($this->settingsService->get()));
    }

    public function update(UpdateSettingsRequest $request)
    {
        $this->settingsService->update($request->validated());

        return $this->successResponse($this->maskSensitiveSettings($this->settingsService->get()), 'Settings updated successfully.');
    }

    private function maskSensitiveSettings(array $settings): array
    {
        if (isset($settings['email']['mail_password']) && !empty($settings['email']['mail_password'])) {
            $settings['email']['mail_password'] = '••••••••';
        }
        if (isset($settings['storage']['s3_secret']) && !empty($settings['storage']['s3_secret'])) {
            $settings['storage']['s3_secret'] = '••••••••';
        }
        if (isset($settings['ai']['ai_api_key']) && !empty($settings['ai']['ai_api_key'])) {
            $settings['ai']['ai_api_key'] = '••••••••';
        }
        return $settings;
    }
}

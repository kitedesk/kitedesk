<?php

namespace App\Http\Requests\Admin;

use App\Domain\Branding\Branding;
use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveBrandingRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $logo = ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'];

        return [
            'name' => ['required', 'string', 'max:60'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-f]{6}$/i'],
            'logo' => $logo,
            'logo_dark' => $logo,
            'favicon' => ['nullable', 'file', 'mimes:png,ico,svg', 'max:256'],
            'remove_logo' => ['boolean'],
            'remove_logo_dark' => ['boolean'],
            'remove_favicon' => ['boolean'],
            'email_from_name' => ['nullable', 'string', 'max:80'],
            'email_footer' => ['nullable', 'string', 'max:500'],
            'help_title' => ['nullable', 'string', 'max:100'],
            'help_subtitle' => ['nullable', 'string', 'max:200'],
            'header_links' => ['nullable', 'array', 'max:'.Branding::MAX_HEADER_LINKS],
            'header_links.*.label' => ['required', 'string', 'max:40'],
            'header_links.*.url' => ['required', 'url:http,https', 'max:255'],
            'portal_footer' => ['nullable', 'string', 'max:500'],
            'show_powered_by' => PlanLimits::allows(Feature::CustomBranding) ? ['boolean'] : ['boolean', 'accepted'],
            'custom_css' => PlanLimits::allows(Feature::CustomBranding) ? ['nullable', 'string', 'max:10000'] : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'show_powered_by.accepted' => __('Your plan does not include hiding "Powered by KiteDesk".'),
            'custom_css.prohibited' => __('Your plan does not include custom CSS.'),
        ];
    }
}

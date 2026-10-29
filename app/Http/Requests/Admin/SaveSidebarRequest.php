<?php

namespace App\Http\Requests\Admin;

use App\Domain\Support\Sidebar\SidebarSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSidebarRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return ['mode' => ['required', Rule::in(SidebarSettings::MODES)]];
    }
}

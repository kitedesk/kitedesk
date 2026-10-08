<?php

namespace App\Http\Requests\Admin\Sla;

use App\Domain\Sla\Models\BusinessSchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreHolidayRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var BusinessSchedule $schedule */
                $schedule = $this->route('schedule');

                if ($validator->errors()->has('date')) {
                    return;
                }

                // Compare by calendar day: the date cast may store a time component.
                if ($schedule->holidays()->whereDate('date', (string) $this->input('date'))->exists()) {
                    $validator->errors()->add('date', __('This date is already a holiday.'));
                }
            },
        ];
    }
}

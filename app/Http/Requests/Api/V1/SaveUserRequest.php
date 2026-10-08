<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Accounts\Enums\UserType;
use App\Http\Requests\Admin\SaveUserRequest as AdminSaveUserRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The admin center's user rules (seat limit, last administrator, own access), with API defaults:
 * `type` is `customer` unless given, and `invite` chooses whether a new person is emailed a link
 * to choose a password.
 */
class SaveUserRequest extends AdminSaveUserRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('type')) {
            $this->merge(['type' => UserType::Customer->value]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** `staff` needs `role_id`. Defaults to `customer`. */
            'type' => parent::rules()['type'],
            /** Email new people a link to choose a password. Defaults to true. */
            'invite' => ['sometimes', 'boolean'],
        ];
    }

    public function userData(): array
    {
        /** @var array{name: string, email: string, type: string, organization_id?: int|null, timezone?: string|null, locale?: string|null, job_title?: string|null, phone?: string|null, group_ids?: list<int>} */
        return collect(parent::userData())->except('invite')->all();
    }
}

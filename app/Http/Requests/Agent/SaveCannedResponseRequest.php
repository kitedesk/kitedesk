<?php

namespace App\Http\Requests\Agent;

use App\Domain\Accounts\Enums\Permission;
use App\Domain\Support\RichText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCannedResponseRequest extends FormRequest
{
    /**
     * Only roles allowed to share may share responses with everyone or a group.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $canShare = $this->user()->hasPermission(Permission::ShareCannedResponses);

        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:65535'],
            'is_shared' => ['sometimes', $canShare ? 'boolean' : 'declined'],
            'group_id' => [$canShare ? 'nullable' : 'prohibited', 'integer', Rule::exists('groups', 'id')],
        ];
    }

    /**
     * @return array{title: string, body: string, is_shared: bool, group_id: int|null}
     */
    public function responseAttributes(): array
    {
        $isShared = $this->boolean('is_shared');

        return [
            'title' => $this->string('title')->toString(),
            'body' => RichText::sanitize($this->string('body')->toString()),
            'is_shared' => $isShared,
            'group_id' => ! $isShared && $this->filled('group_id') ? $this->integer('group_id') : null,
        ];
    }
}

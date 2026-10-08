<?php

namespace App\Http\Requests\Admin;

use App\Domain\Mail\Enums\MailboxDriver;
use App\Domain\Mail\Models\Mailbox;
use App\Domain\Support\PublicNetwork;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMailboxRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('address'))) {
            $this->merge(['address' => mb_strtolower(trim($this->input('address')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $mailbox = $this->route('mailbox');
        $isImap = fn (): bool => $this->input('driver') === MailboxDriver::Imap->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'email', 'max:255', Rule::unique('mailboxes', 'address')->ignore($mailbox instanceof Mailbox ? $mailbox->id : null)],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'default_group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')],
            'default_category_id' => ['nullable', 'integer', Rule::exists('ticket_categories', 'id')],
            'driver' => ['required', Rule::enum(MailboxDriver::class)],
            'imap_host' => [Rule::requiredIf($isImap), 'nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($isImap): void {
                if ($isImap() && is_string($value) && $value !== '' && ! PublicNetwork::allowsHost($value)) {
                    $fail(__('The host must be a public internet address.'));
                }
            }],
            'imap_port' => [Rule::requiredIf($isImap), 'nullable', 'integer', 'between:1,65535'],
            'imap_encryption' => ['nullable', Rule::in(['ssl', 'tls', 'starttls'])],
            'imap_username' => [Rule::requiredIf($isImap), 'nullable', 'string', 'max:255'],
            // Left empty when editing, the saved password is kept.
            'imap_password' => [Rule::requiredIf(fn (): bool => $isImap() && ! ($mailbox instanceof Mailbox && $mailbox->imap_password !== null)), 'nullable', 'string', 'max:255'],
            'imap_folder' => ['nullable', 'string', 'max:255'],
            'delete_after_import' => ['boolean'],
            'inbound_secret' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mailboxAttributes(): array
    {
        $attributes = $this->safe()->except(['imap_password', 'inbound_secret']);
        $attributes['imap_folder'] = $this->validated('imap_folder') ?: 'INBOX';

        foreach (['imap_password', 'inbound_secret'] as $secret) {
            if ($this->filled($secret)) {
                $attributes[$secret] = $this->string($secret)->toString();
            }
        }

        return $attributes;
    }
}

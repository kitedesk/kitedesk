<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

trait HasAttachments
{
    /**
     * Uploaded attachments as a list (validated by the `attachments.*` rules).
     *
     * @return list<UploadedFile>
     */
    public function attachments(): array
    {
        return array_values(Arr::wrap($this->file('attachments', [])));
    }

    /**
     * Validated custom field values keyed by field key.
     *
     * @return array<string, mixed>
     */
    public function customFields(): array
    {
        $fields = $this->validated('custom_fields', []);

        return is_array($fields) ? $fields : [];
    }
}

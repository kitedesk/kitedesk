<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveAiSettingsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            // http is allowed for models served on the local network (Ollama, LM Studio, vLLM).
            'base_url' => ['required_if:enabled,true', 'nullable', 'url:http,https', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'model' => ['required_if:enabled,true', 'nullable', 'string', 'max:150'],
            'summaries' => ['required', 'boolean'],
            'drafts' => ['required', 'boolean'],
            'improve' => ['required', 'boolean'],
            'mcp_enabled' => ['required', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'context_messages' => ['required', 'integer', 'min:5', 'max:100'],
        ];
    }

    /**
     * @return array{enabled: bool, base_url: ?string, api_key: ?string, model: ?string, summaries: bool, drafts: bool, improve: bool, mcp_enabled: bool, instructions: ?string, context_messages: int}
     */
    public function settings(): array
    {
        $text = fn (string $key): ?string => filled($this->input($key)) ? trim($this->string($key)->toString()) : null;

        return [
            'enabled' => $this->boolean('enabled'),
            'base_url' => $text('base_url'),
            'api_key' => $text('api_key'),
            'model' => $text('model'),
            'summaries' => $this->boolean('summaries'),
            'drafts' => $this->boolean('drafts'),
            'improve' => $this->boolean('improve'),
            'mcp_enabled' => $this->boolean('mcp_enabled'),
            'instructions' => $text('instructions'),
            'context_messages' => $this->integer('context_messages'),
        ];
    }
}

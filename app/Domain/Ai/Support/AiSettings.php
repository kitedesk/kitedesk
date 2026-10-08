<?php

namespace App\Domain\Ai\Support;

use App\Domain\Entitlements\Enums\Feature;
use App\Domain\Entitlements\Support\PlanLimits;
use App\Domain\Support\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Laravel\Ai\Ai;
use Laravel\Ai\Providers\Provider;

/**
 * The AI assistant's connection (any OpenAI-compatible endpoint), which features agents get,
 * and whether the MCP server is on. The API key is encrypted at rest: unlike other settings
 * it is a credential for a third-party service.
 */
final readonly class AiSettings
{
    public const string SETTING = 'ai';

    public const int DEFAULT_CONTEXT_MESSAGES = 30;

    public function __construct(
        public bool $enabled = false,
        public ?string $baseUrl = null,
        public ?string $apiKey = null,
        public ?string $model = null,
        public bool $summaries = true,
        public bool $drafts = true,
        public bool $improve = true,
        public bool $mcpEnabled = false,
        public ?string $instructions = null,
        public int $contextMessages = self::DEFAULT_CONTEXT_MESSAGES,
    ) {}

    public static function current(): self
    {
        $setting = Setting::get(self::SETTING);
        $setting = is_array($setting) ? $setting : [];
        $string = fn (string $key): ?string => is_string($setting[$key] ?? null) && $setting[$key] !== '' ? $setting[$key] : null;

        return new self(
            enabled: (bool) ($setting['enabled'] ?? false),
            baseUrl: $string('base_url'),
            apiKey: self::decrypt($string('api_key')),
            model: $string('model'),
            summaries: (bool) ($setting['summaries'] ?? true),
            drafts: (bool) ($setting['drafts'] ?? true),
            improve: (bool) ($setting['improve'] ?? true),
            mcpEnabled: (bool) ($setting['mcp_enabled'] ?? false),
            instructions: $string('instructions'),
            contextMessages: is_int($setting['context_messages'] ?? null) ? $setting['context_messages'] : self::DEFAULT_CONTEXT_MESSAGES,
        );
    }

    /**
     * The settings to store when an admin saves the page. A blank key keeps the stored one.
     *
     * @param  array{enabled: bool, base_url: ?string, api_key: ?string, model: ?string, summaries: bool, drafts: bool, improve: bool, mcp_enabled: bool, instructions: ?string, context_messages: int}  $input
     */
    public function update(array $input): self
    {
        return new self(
            enabled: $input['enabled'],
            baseUrl: $input['base_url'] !== null ? rtrim($input['base_url'], '/') : null,
            apiKey: filled($input['api_key']) ? $input['api_key'] : $this->apiKey,
            model: $input['model'],
            summaries: $input['summaries'],
            drafts: $input['drafts'],
            improve: $input['improve'],
            mcpEnabled: $input['mcp_enabled'],
            instructions: $input['instructions'],
            contextMessages: $input['context_messages'],
        );
    }

    public function save(): void
    {
        Setting::put(self::SETTING, [
            'enabled' => $this->enabled,
            'base_url' => $this->baseUrl,
            'api_key' => $this->apiKey !== null ? Crypt::encryptString($this->apiKey) : null,
            'model' => $this->model,
            'summaries' => $this->summaries,
            'drafts' => $this->drafts,
            'improve' => $this->improve,
            'mcp_enabled' => $this->mcpEnabled,
            'instructions' => $this->instructions,
            'context_messages' => $this->contextMessages,
        ]);
    }

    /**
     * Switched on and pointed at an endpoint and model.
     */
    public function isAvailable(): bool
    {
        return $this->enabled && $this->baseUrl !== null && $this->model !== null && PlanLimits::allows(Feature::Ai);
    }

    /**
     * Which assistant features agents can use, for the ticket page.
     *
     * @return array{summaries: bool, drafts: bool, improve: bool}
     */
    public function features(): array
    {
        $available = $this->isAvailable();

        return [
            'summaries' => $available && $this->summaries,
            'drafts' => $available && $this->drafts,
            'improve' => $available && $this->improve,
        ];
    }

    /**
     * The configured endpoint as an AI SDK provider.
     */
    public function provider(): Provider
    {
        return Ai::build([
            'driver' => 'openai-compatible',
            'url' => $this->baseUrl,
            'key' => $this->apiKey,
            'models' => ['text' => ['default' => $this->model]],
        ]);
    }

    /**
     * A key that can't be decrypted (APP_KEY was rotated) counts as missing, so the admin is
     * asked for it again instead of every request failing.
     */
    private static function decrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}

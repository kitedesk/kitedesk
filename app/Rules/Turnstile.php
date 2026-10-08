<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Verifies a Cloudflare Turnstile token. Only active when both Turnstile keys are configured.
 */
class Turnstile implements ValidationRule
{
    /**
     * The form field the Turnstile widget fills in.
     */
    public const string FIELD = 'cf-turnstile-response';

    private const string VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function enabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    /**
     * Validation rules to merge into a public form's rules.
     *
     * @return array<string, array<mixed>>
     */
    public static function rules(): array
    {
        return self::enabled() ? [self::FIELD => ['required', 'string', new self]] : [];
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $passed = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ])->json('success') === true;
        } catch (ConnectionException) {
            $passed = false;
        }

        if (! $passed) {
            $fail(__('Please confirm you are human and try again.'));
        }
    }
}

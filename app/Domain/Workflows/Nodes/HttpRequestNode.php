<?php

namespace App\Domain\Workflows\Nodes;

use App\Domain\Support\PublicNetwork;
use App\Domain\Workflows\Engine\NodeResult;
use App\Domain\Workflows\Engine\Placeholders;
use App\Domain\Workflows\Engine\RunContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Calls an external URL. Only public internet addresses are allowed (the connection is pinned
 * to the checked address) and redirects aren't followed.
 *
 * With `save_as`, the response is kept as `{{vars.<name>.status}}` and `{{vars.<name>.body}}`
 * (decoded when it is JSON), so later nodes can branch on it or loop over a list in it.
 */
class HttpRequestNode extends Node
{
    private const int MAX_SAVED_BYTES = 65536;

    public function __construct(private Placeholders $placeholders) {}

    public function type(): string
    {
        return 'http_request';
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'url' => ['required', 'string', 'max:2000', 'regex:/^https?:\/\//i'],
            'headers' => ['sometimes', 'array', 'max:20'],
            'headers.*.name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9-]+$/'],
            'headers.*.value' => ['present', 'nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:20000'],
            'save_as' => ['nullable', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/i'],
        ];
    }

    public function execute(array $data, RunContext $context): NodeResult
    {
        $method = (string) $data['method'];
        $url = trim($this->placeholders->text((string) $data['url'], $context));
        $body = $this->placeholders->text((string) ($data['body'] ?? ''), $context);
        $headers = [];

        foreach ((array) ($data['headers'] ?? []) as $header) {
            if (is_array($header) && is_string($header['name'] ?? null)) {
                $headers[$header['name']] = $this->placeholders->text((string) ($header['value'] ?? ''), $context);
            }
        }

        if ($context->simulating) {
            return NodeResult::next(['method' => $method, 'url' => $url]);
        }

        $pinning = preg_match('/^https?:\/\//i', $url) === 1 ? PublicNetwork::pinnedCurlOptions($url) : null;

        if ($pinning === null) {
            throw new RuntimeException(__('Blocked: the URL does not point to a public internet address.'));
        }

        $response = Http::timeout(10)
            ->withOptions(['allow_redirects' => false, 'curl' => $pinning])
            ->withHeaders(['User-Agent' => config('app.name').'-Workflows/1.0', ...$headers])
            ->when($body !== '' && $method !== 'GET', fn ($request) => $request->withBody($body, json_validate($body) ? 'application/json' : 'text/plain'))
            ->send($method, $url);

        if (filled($data['save_as'] ?? null)) {
            $raw = substr($response->body(), 0, self::MAX_SAVED_BYTES);
            $context->vars[(string) $data['save_as']] = [
                'status' => $response->status(),
                'body' => json_validate($raw) ? json_decode($raw, true) : $raw,
            ];
        }

        return NodeResult::next([
            'method' => $method,
            'url' => $url,
            'status' => $response->status(),
            'response' => Str::limit($response->body(), 500),
        ]);
    }
}

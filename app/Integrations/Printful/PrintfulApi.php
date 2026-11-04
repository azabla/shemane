<?php

namespace App\Integrations\Printful;

use App\Integrations\SupplierHttp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class PrintfulApi
{
    /** @param  array<string, mixed>  $settings */
    public static function get(array $settings, string $path, array $query = []): Response
    {
        return SupplierHttp::call(self::client($settings), fn (PendingRequest $r) => $r->get(self::url($path), $query));
    }

    /** @param  array<string, mixed>  $settings */
    public static function post(array $settings, string $path, array $body): Response
    {
        return SupplierHttp::call(self::client($settings), fn (PendingRequest $r) => $r->post(self::url($path), $body));
    }

    private static function url(string $path): string
    {
        return rtrim((string) config('services.printful.url', 'https://api.printful.com'), '/').$path;
    }

    /** @param  array<string, mixed>  $settings */
    private static function client(array $settings): PendingRequest
    {
        $request = Http::withToken((string) $settings['api_token'])->acceptJson();

        return filled($settings['store_id'] ?? null) ? $request->withHeaders(['X-PF-Store-Id' => (string) $settings['store_id']]) : $request;
    }
}

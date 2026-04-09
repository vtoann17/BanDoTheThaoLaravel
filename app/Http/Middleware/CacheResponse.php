<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class CacheResponse
{
    private array $tagMap = [
        '/api/products'        => ['products'],
        '/api/categories'      => ['categories'],
        '/api/subcategories'   => ['subcategories'],
        '/api/brands'          => ['brands'],
        '/api/coupons'         => ['coupons'],
        '/api/attributes'      => ['attributes'],
        '/api/attribute-value' => ['attribute_values'],
        '/api/variant'         => ['variants'],
        '/api/provinces'       => ['shipping'],
        '/api/districts'       => ['shipping'],
        '/api/wards'           => ['shipping'],
        '/api/getUser'         => ['users'],
    ];

    public function handle($request, Closure $next, $ttl = 600)
    {
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        $token = $request->bearerToken() ?? 'guest';
        $key   = 'api.' . md5($request->fullUrl() . $token);
        $tags  = $this->resolveTags($request->path());

        if (!empty($tags)) {
            if (Cache::tags($tags)->has($key)) {
                return response()->json(Cache::tags($tags)->get($key));
            }

            $response = $next($request);

            if ($response->isSuccessful()) {
                Cache::tags($tags)->put($key, $response->getData(true), (int) $ttl);
            }

            return $response;
        }

        if (Cache::has($key)) {
            return response()->json(Cache::get($key));
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            Cache::put($key, $response->getData(true), (int) $ttl);
        }

        return $response;
    }

    private function resolveTags(string $path): array
    {
        $path = '/' . ltrim($path, '/');

        foreach ($this->tagMap as $pattern => $tags) {
            if (str_starts_with($path, $pattern)) {
                return $tags;
            }
        }

        return [];
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * ResolvesLocale
 *
 * Provides a helper to extract the target locale from the request,
 * using either the `locale` query parameter or the `Accept-Language` header.
 *
 * Supported locales: 'en', 'id'. Defaults to 'en'.
 *
 * Requirements: 8.1, 8.5
 */
trait ResolvesLocale
{
    /**
     * Resolve the target locale from the request.
     *
     * Priority:
     *   1. ?locale=id query parameter
     *   2. Accept-Language header (first supported tag)
     *   3. 'en' fallback
     */
    protected function resolveLocale(Request $request): string
    {
        $supported = ['en', 'id'];

        // 1. Explicit query parameter
        $queryLocale = $request->query('locale');
        if ($queryLocale && in_array($queryLocale, $supported, true)) {
            return $queryLocale;
        }

        // 2. Accept-Language header
        $acceptLang = $request->header('Accept-Language', '');
        foreach (explode(',', $acceptLang) as $tag) {
            // Extract the language code (e.g. "id-ID;q=0.9" → "id")
            $lang = strtolower(trim(explode(';', $tag)[0]));
            $lang = explode('-', $lang)[0]; // "id-ID" → "id"
            if (in_array($lang, $supported, true)) {
                return $lang;
            }
        }

        return 'en';
    }
}

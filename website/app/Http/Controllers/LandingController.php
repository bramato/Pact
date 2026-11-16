<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

final class LandingController
{
    public function __invoke(?string $locale = null): Response
    {
        $defaultLocale = config('localization.default');
        $locale ??= $defaultLocale;
        $locales = config('localization.locales');
        abort_unless(isset($locales[$locale]), 404);

        app()->setLocale($locale);
        $copy = Lang::get('landing', [], $locale, false);
        abort_unless(is_array($copy), 500);

        $alternateUrls = [];
        foreach (array_keys($locales) as $code) {
            $alternateUrls[$code] = $code === $defaultLocale
                ? route('home')
                : route('home.localized', ['locale' => $code]);
        }

        return response()->view('pact', compact(
            'copy', 'locale', 'defaultLocale', 'locales', 'alternateUrls',
        ))->header('Content-Language', $locale);
    }
}

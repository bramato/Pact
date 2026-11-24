<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

final class LandingController
{
    public function __invoke(Request $request, ?string $locale = null): Response
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

        $releases = config('releases');
        $latest = array_key_first($releases);
        $selected = $request->query('version');
        $version = is_string($selected) && isset($releases[$selected]) ? $selected : $latest;
        $release = $releases[$version];
        $releaseCopy = Lang::get('releases', [], $locale, false)[$version];
        $handoff = Lang::get('handoff', [], $locale, false);
        $installation = $handoff['installation'][$release['installation'] ?? 'files'];
        $prompt = str_replace(':installation', $installation, $handoff['prompt']);
        $source = 'https://github.com/bramato/Pact/blob/v'.$version.'/';
        $documents = array_map(
            fn (string $path): string => $source.$path,
            [
                'guide' => 'docs/laravel-installation.md',
                'core' => 'specification/PACT-CORE-v0.2.md',
                'binding' => 'specification/PACT-LARAVEL-BINDING-v0.1.md',
                ...$release['documents'],
                'changelog' => 'CHANGELOG.md',
            ],
        );

        foreach ($handoff['roles'] as $role => $roleCopy) {
            $handoff['roles'][$role]['text'] = strtr($prompt, [
                ':version' => $version,
                ':protocol' => $release['protocol'],
                ':role' => $role,
                ':sources' => '- '.implode("\n- ", $documents),
                ':goal' => $roleCopy['goal'],
                ':linking' => $handoff['linking'][$release['linking']][$role],
                ':execution' => $roleCopy['execution'],
                ':release_instructions' => $releaseCopy['instructions'][$role],
                ':verification' => $roleCopy['verification'],
            ]);
        }

        return response()->view('pact', compact(
            'copy', 'locale', 'defaultLocale', 'locales', 'alternateUrls',
            'releases', 'latest', 'version', 'release', 'releaseCopy', 'handoff', 'documents',
        ))->header('Content-Language', $locale);
    }
}

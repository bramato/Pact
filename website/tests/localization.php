<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);

function expect(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, 'FAIL '.$message.PHP_EOL);
        exit(1);
    }
}

function requestPage(Kernel $kernel, string $path, string $method = 'GET', string $acceptLanguage = 'en'): Response
{
    $request = Request::create('http://pact.test'.$path, $method, server: [
        'HTTP_ACCEPT_LANGUAGE' => $acceptLanguage,
    ]);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
}

function document(string $html): DOMXPath
{
    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($document);
}

function catalogShape(array $catalog, string $prefix = ''): array
{
    $shape = [];
    foreach ($catalog as $key => $value) {
        $path = $prefix.'.'.$key;
        if (is_array($value)) {
            $shape += catalogShape($value, $path);
        } else {
            expect(! is_string($value) || trim($value) !== '', 'Empty translation: '.$path);
            $shape[$path] = get_debug_type($value);
        }
    }

    return $shape;
}

$italian = require __DIR__.'/../lang/it/landing.php';
$english = require __DIR__.'/../lang/en/landing.php';
expect(catalogShape($italian) === catalogShape($english), 'The two catalogs must cover the same content.');

$releases = require __DIR__.'/../config/releases.php';
$manifest = json_decode(file_get_contents(__DIR__.'/../../release.json'), true, flags: JSON_THROW_ON_ERROR);
$latest = array_key_first($releases);
expect($latest === $manifest['version'], 'The homepage must default to the version in release.json.');
expect($releases[$latest]['protocol'] === $manifest['protocol_version'], 'The latest homepage release must show its Core protocol version.');
$versions = array_keys($releases);
$sorted = $versions;
usort($sorted, fn (string $left, string $right): int => version_compare($right, $left));
expect($versions === $sorted, 'The version selector must list newest releases first.');

foreach ([['/', 'it', $italian, 'it_IT'], ['/en', 'en', $english, 'en_US'], ['/', 'it', $italian, 'it_IT']] as [$path, $locale, $copy, $ogLocale]) {
    // English headers must not change the cacheable Italian root; a prior English
    // request must not leak its locale into the following root response either.
    $response = requestPage($kernel, $path);
    expect($response->getStatusCode() === 200, $path.' must render successfully.');
    expect($response->headers->get('Content-Language') === $locale, 'Content-Language does not match '.$path);
    expect($response->headers->getCookies() === [], 'Public language selection must not create session cookies.');
    expect($response->headers->hasCacheControlDirective('public'), 'The localized page must remain public.');
    expect($response->headers->getCacheControlDirective('s-maxage') === '3600', 'Shared caching must remain enabled.');

    $html = $response->getContent();
    $xpath = document($html);
    expect($xpath->evaluate('string(/html/@lang)') === $locale, 'Incorrect document language.');
    expect($xpath->evaluate('string(//title)') === $copy['meta']['title'], 'The title is not localized.');
    expect($xpath->evaluate('string(//meta[@name="description"]/@content)') === $copy['meta']['description'], 'The description is not localized.');
    expect($xpath->evaluate('string(//meta[@property="og:locale"]/@content)') === $ogLocale, 'Incorrect Open Graph language.');
    expect($xpath->evaluate('string(//link[@rel="canonical"]/@href)') === rtrim('http://pact.test'.$path, '/'), 'Incorrect canonical URL.');
    expect($xpath->query('//link[@rel="alternate" and @hreflang]')->length === 3, 'Expected both languages and x-default.');
    expect($xpath->query('//div[@class="language-switch"]/a[@aria-current="page" and @lang="'.$locale.'"]')->length === 1, 'The selector must identify the active language.');
    expect($xpath->query('//div[@class="language-switch"]/a[@lang="en" and @href="http://pact.test/en"]')->length === 1, 'The English selector destination is wrong.');
    expect($xpath->query('//div[@class="language-switch"]/a[@lang="it" and @href="http://pact.test"]')->length === 1, 'The Italian selector destination is wrong.');
    expect($xpath->query('//div[@class="faq-list"]/details')->length === 4, 'The FAQ must remain available without JavaScript.');
    expect($xpath->evaluate('string(//nav[@class="handoff-versions"]//a[@aria-current])') === $latest, 'The unqualified homepage must select the latest release.');

    $payload = json_decode($xpath->evaluate('string(//script[@id="demo-data"])'), true, flags: JSON_THROW_ON_ERROR);
    expect(count($payload['steps']) === 5, 'All five demo steps must be available.');
    expect($xpath->evaluate('string(//*[@id="demo-heading"])') === $payload['steps'][0]['heading'], 'The no-JavaScript demo must match the localized client payload.');
    expect($payload['labels']['copy'] === $copy['demo']['client']['labels']['copy'], 'Clipboard messages must be localized.');
    $question = $payload['steps'][2]['data']['question'];
    $answer = $payload['steps'][3]['data']['answer'];
    expect($question['id'] === $answer['questionId'], 'Localized answers must still identify their question.');
    expect(in_array($answer['value'], $question['allowed'], true), 'The example answer must be allowed.');
    expect(count($payload['steps'][4]['data']['artifact']['words']) === $answer['value'], 'The translated delivery must contain the requested word count.');
    expect($payload['steps'][4]['data']['task']['status'] === 'COMPLETED', 'Protocol statuses must not be translated.');

    if ($locale === 'en') {
        foreach (['Vai al contenuto', 'Mettersi', 'Passaggio successivo', 'Bozza 0.2.0', 'Qualche chiarimento'] as $italianText) {
            expect(! str_contains($html, $italianText), 'Italian text leaked into the English page: '.$italianText);
        }
    }

    echo 'PASS '.$path.' · localized content, metadata, cache, selector and demo'.PHP_EOL;
}

$italianHandoff = require __DIR__.'/../lang/it/handoff.php';
$englishHandoff = require __DIR__.'/../lang/en/handoff.php';
expect(catalogShape($italianHandoff) === catalogShape($englishHandoff), 'Both handoff catalogs must cover the same roles, flows and document labels.');

foreach (['it' => '/', 'en' => '/en'] as $locale => $path) {
    $editions = require __DIR__.'/../lang/'.$locale.'/releases.php';
    expect(array_keys($editions) === $versions, 'Every release must have '.$locale.' notes and instructions.');
    catalogShape($editions);

    foreach ($versions as $version) {
        $response = requestPage($kernel, $path.'?version='.$version);
        expect($response->getStatusCode() === 200, 'A selected release must render successfully.');
        $xpath = document($response->getContent());
        $selector = '//nav[@class="handoff-versions"]';
        expect($xpath->query($selector.'//a')->length === count($versions), 'All releases must remain selectable.');
        expect($xpath->evaluate('string('.$selector.'//a[@aria-current])') === $version, 'The selector must mark the requested release.');
        expect($xpath->evaluate('string('.$selector.'//a[@aria-current]/@href)') === rtrim('http://pact.test'.$path, '/').'?version='.$version.'#handoff', 'The version must have a shareable URL.');

        $source = 'https://github.com/bramato/Pact/blob/v'.$version.'/';
        $documents = $xpath->query('//ul[@class="handoff-documents"]//a');
        expect($documents->length === 4 + count($releases[$version]['documents']), 'The release must show its complete documentation set.');
        foreach ($documents as $link) {
            expect(str_starts_with($link->getAttribute('href'), $source), 'Each document must point to the selected Git tag.');
        }
        $changes = trim($xpath->evaluate('string(//ul[@class="handoff-changes"]/li[1])'));
        expect($changes === $editions[$version]['changes'][0], 'Release notes must follow the selection.');

        foreach (['master', 'client'] as $role) {
            $id = 'handoff-'.$role.'-text';
            $prompt = $xpath->evaluate('string(//details/pre[@id="'.$id.'"])');
            expect(str_contains($prompt, "pact-protocol/laravel:$version pact-protocol/core:$version"), 'Both packages must be pinned to '.$version.'.');
            expect(str_contains($prompt, 'pact:install --role='.$role), 'The handoff must install the correct role.');
            expect(str_contains($prompt, $editions[$version]['instructions'][$role]), 'The preview must include the selected release instructions.');
            expect($xpath->query('//button[@data-copy-target="'.$id.'"]')->length === 1, 'Copy must use the visible prompt text.');
            expect(str_contains($prompt, 'pact:oauth-client') === ($releases[$version]['linking'] === 'manual'), 'Only the legacy handoff should require manual OAuth registration.');
            if ($role === 'client') {
                expect(str_contains($prompt, 'PactLaravel\Contracts\AgentHandler'), 'The client handoff must explain how to wire its own server-side agent.');
            }
        }
        if ($version !== $latest) {
            $other = $locale === 'it' ? 'en' : 'it';
            $href = $xpath->evaluate('string(//div[@class="language-switch"]/a[@lang="'.$other.'"]/@href)');
            expect(str_ends_with($href, '?version='.$version.'#handoff'), 'Switching languages must preserve the selected older release.');
        }
    }
    echo 'PASS '.$locale.' · release links, notes, documentation, and master/client handoff previews'.PHP_EOL;
}

foreach (['/?version=9.9.9', '/?version[]=0.2.0'] as $path) {
    $response = requestPage($kernel, $path);
    expect($response->getStatusCode() === 200, 'An invalid version must render the default homepage.');
    expect(document($response->getContent())->evaluate('string(//nav[@class="handoff-versions"]//a[@aria-current])') === $latest, 'An invalid version must select the latest release.');
}

$alias = requestPage($kernel, '/it');
expect($alias->getStatusCode() === 301 && $alias->headers->get('Location') === '/', 'The Italian alias must redirect to its canonical root.');
expect(requestPage($kernel, '/fr')->getStatusCode() === 404, 'Unsupported locales must not render the default language.');
expect(requestPage($kernel, '/en', 'POST')->getStatusCode() === 405, 'The localized page must remain read-only.');
expect(requestPage($kernel, '/it', 'POST')->getStatusCode() === 405, 'The alias must remain read-only.');
$health = requestPage($kernel, '/up');
expect($health->getStatusCode() === 200 && $health->getContent() === 'OK', 'The health endpoint must remain available.');
expect($health->headers->hasCacheControlDirective('no-store'), 'Health responses must not be cached.');
$head = requestPage($kernel, '/en', 'HEAD');
expect($head->getStatusCode() === 200 && $head->getContent() === '', 'HEAD must work without a body.');
echo 'PASS canonical alias, unsupported locale, read-only routes, health and HEAD'.PHP_EOL;

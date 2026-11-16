# PACT editorial website

`website/` is a standalone Laravel 13 application containing Italian and English editions of one editorial page. It explains Core, typed questions, progress, validated completion and optional Swarm coordination, with the existing project illustrations and a five-step browser-local example. It has no database, migrations, external cache, authentication, queue workers, scheduled tasks or model API calls. JavaScript enhances the example; the article, language selector and FAQ work without it.

The root reference demo remains a separate application with its own SQLite requirements. Laravel Cloud must deploy **only `website/`**.

## Local preview with Herd

From the repository root:

```sh
composer install --working-dir=website
```

Open `http://pact.test` for Italian or `http://pact.test/en` for English. The root front controller forwards `/`, the configured language paths and `/up` to the editorial app, and `public/assets` links to its local assets. Existing reference API and `/docs/` routes keep their original controller. To build those separate documentation pages, run `npm run docs:build` as before.

The editorial app defaults to `APP_URL=http://pact.test`, Italian, production-safe error display, stderr logs and in-memory cache/session drivers. It has no session middleware and does not issue session cookies. For optional local overrides, copy `website/.env.example` to `website/.env`; keep the Herd domain and scheme. Laravel Cloud supplies its own production environment variables.

## Languages

The IT / EN selector uses ordinary links. Italian has the canonical URL `/`; `/it` redirects there permanently. English uses `/en`. The URL determines the language, without cookies or browser-language negotiation, so each edition can be shared and cached independently. Both pages emit localized titles, descriptions, Open Graph metadata, document and content language, canonical URLs and `hreflang` alternates, including `x-default`.

The complete content catalogs live in `website/lang/it/landing.php` and `website/lang/en/landing.php`. They include article copy, captions, accessibility labels, FAQ, all five demo steps, illustrative JSON values and clipboard messages. The page renders the initial demo step on the server and supplies the same localized data to the external JavaScript through an escaped JSON block. Catalog HTML is checked-in editorial markup; never populate those raw HTML fields from untrusted input.

To add a language, register its code, native name and Open Graph locale in `website/config/localization.php`, add a complete `website/lang/{code}/landing.php` catalog, and extend the localization checks. Routes, selector links and alternate URLs use the locale configuration. The `default` setting is the stable routing default; it is separate from Laravel's mutable current-request locale.

## Laravel Cloud deployment

The website must first exist in the remote branch selected for deployment. Pushes are performed by Marco. An attempted application creation on 16 November 2026 was rejected because the remote `main` branch did not yet contain `website/`; no successful application creation or deployment is claimed.

After the website commit is available on the chosen remote branch, create an application connected to `bramato/Pact`, with these settings:

| Setting | Value |
| --- | --- |
| Application | PACT |
| Region | `eu-central-1` (Frankfurt) |
| Root directory | `website` |
| Runtime | PHP 8.5 (code and lock file also target PHP 8.3+) |
| App cluster | Flex, 512 MiB, `flex-512mb` |
| Replicas | 1 minimum, 1 maximum; scale to zero is a separate setting |
| Scale to zero | Enabled, 5-minute idle timeout |
| Scheduler / manual wake | Disabled |
| Octane / Inertia SSR | Disabled |
| Database / cache / object storage | None |
| Managed queues / worker clusters / background processes | None |
| Deploy commands | Empty; do not run migrations |
| Health check | `/up` |

Use these build commands, scoped to `website/` by Cloud:

```sh
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan optimize
```

There is no frontend package installation or asset build. CSS, JavaScript, SVG and illustrations are committed under `website/public/assets/`.

Production variables:

```dotenv
APP_NAME=PACT
APP_ENV=production
APP_DEBUG=false
APP_URL=https://the-assigned-cloud-domain
LOG_CHANNEL=stderr
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

Replace `APP_URL` with the actual assigned HTTPS domain before building. Retain Cloud's generated `APP_KEY`, or generate a private one in Cloud; never commit it. The page is public and read-only, so its routes deliberately omit the `web` session middleware. Its routes are `GET|HEAD /`, `/en`, `/up` and the canonical redirect `/it`; uploads and public storage serving are disabled.

Scale to zero suspends compute billing while the environment sleeps; it is not a promise of a zero total bill. Requests reset the idle timer, so frequent bots or external uptime polling can prevent sleep. The page is publicly cacheable for five minutes in browsers and one hour at shared caches; `/up` is not cacheable. No recurring wake jobs are configured.

Official references checked on 16 November 2026: [Cloud compute and scale to zero](https://laravel.com/cloud/docs/compute), [monorepo roots](https://laravel.com/cloud/docs/monorepos), [Laravel builds](https://laravel.com/cloud/docs/deploy-guides/laravel).

## Editing and verification

- Page: `website/resources/views/pact.blade.php`.
- Language configuration, routing and content: `website/config/localization.php`, `website/app/Http/Controllers/LandingController.php` and `website/lang/{it,en}/landing.php`.
- Appearance and responsive layout: `website/public/assets/pact.css`. The entire page uses physical paper surfaces, including cobalt sections, the header/footer, buttons, diagrams and the cream typewritten example. `paper-fibres.webp` supplies the shared fibrous stock; `print-grain.svg` adds dry ink wear that scrolls with the page. `paper-edge.svg` masks the torn transitions between sheets. The visual layers are disabled in forced-colors mode.
- Halftone paper-cut illustrations: `website/public/assets/overview-editorial.webp` and `website/public/assets/concept-editorial.webp`. Both retain alpha transparency and are exported as WebP for the page; the earlier illustrations remain available separately.
- Brand: `website/public/assets/pact-logo-sticker.png` is the master of the monochrome paper sticker selected for the project: a horizontal circle/triangle/square emblem and a large PACT wordmark below, all on one continuous ivory cutout. `pact-logo-sticker.webp` is its web export for the masthead and footer; `pact-symbol.svg` is the compact favicon. The full name is **Protocol for Agent Coordination and Tasks**.
- Illustration direction, built-in image-generation tool and complete prompts: `website/art-direction.json`.
- Illustrative sequence and copy interaction: `website/public/assets/pact.js`.
- Editorial sources and claim boundaries: `marketing/testi/pagina-editoriale/fonti.md`.

Verify the deployment build from `website/`:

```sh
composer validate --strict
php artisan optimize
php artisan route:list
php tests/localization.php
```

Then check `/`, `/en`, `/it` and `/up`, the language selector, all five example steps in both languages, the FAQ, image loading, mobile width, and absence of session cookies. `php artisan optimize:clear` removes local framework caches after a production-build check. Do not use a database migration or seeder to initialize this application.

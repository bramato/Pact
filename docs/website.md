# PACT editorial website

`website/` is a standalone Laravel 13 application containing one Italian editorial page. It explains Core, typed questions, progress, validated completion and optional Swarm coordination, with the existing project illustrations and a five-step browser-local example. It has no database, migrations, external cache, authentication, queue workers, scheduled tasks or model API calls. JavaScript enhances the example; the article and FAQ work without it.

The root reference demo remains a separate application with its own SQLite requirements. Laravel Cloud must deploy **only `website/`**.

## Local preview with Herd

From the repository root:

```sh
composer install --working-dir=website
```

Open `http://pact.test`. The existing root front controller forwards `/` and `/up` to the editorial app, and `public/assets` links to its local assets. Existing reference API and `/docs/` routes keep their original controller. To build those separate documentation pages, run `npm run docs:build` as before.

The editorial app defaults to `APP_URL=http://pact.test`, Italian, production-safe error display, stderr logs and in-memory cache/session drivers. It has no session middleware and does not issue session cookies. For optional local overrides, copy `website/.env.example` to `website/.env`; keep the Herd domain and scheme. Laravel Cloud supplies its own production environment variables.

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

Replace `APP_URL` with the actual assigned HTTPS domain before building. Retain Cloud's generated `APP_KEY`, or generate a private one in Cloud; never commit it. The page is public and read-only, so its routes deliberately omit the `web` session middleware. The only application routes are `GET|HEAD /` and `GET|HEAD /up`; uploads and public storage serving are disabled.

Scale to zero suspends compute billing while the environment sleeps; it is not a promise of a zero total bill. Requests reset the idle timer, so frequent bots or external uptime polling can prevent sleep. The page is publicly cacheable for five minutes in browsers and one hour at shared caches; `/up` is not cacheable. No recurring wake jobs are configured.

Official references checked on 16 November 2026: [Cloud compute and scale to zero](https://laravel.com/cloud/docs/compute), [monorepo roots](https://laravel.com/cloud/docs/monorepos), [Laravel builds](https://laravel.com/cloud/docs/deploy-guides/laravel).

## Editing and verification

- Page: `website/resources/views/pact.blade.php`.
- Appearance and responsive layout: `website/public/assets/pact.css`.
- Illustrative sequence and copy interaction: `website/public/assets/pact.js`.
- Editorial sources and claim boundaries: `marketing/testi/pagina-editoriale/fonti.md`.

Verify the deployment build from `website/`:

```sh
composer validate --strict
php artisan optimize
php artisan route:list
```

Then check `/` and `/up`, the five example steps, the FAQ, image loading, mobile width, and absence of session cookies. `php artisan optimize:clear` removes local framework caches after a production-build check. Do not use a database migration or seeder to initialize this application.

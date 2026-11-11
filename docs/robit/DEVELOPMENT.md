# Robit Handloom — local development

Ubuntu VM (UTM) with Docker Engine and Compose v2.24 or later. Host PHP and Node are not used.

## Two ways to run

| | Base (`docker-compose.yml`) | Dev (`docker-compose.yml` + `compose.dev.yaml`) |
| --- | --- | --- |
| Code | Baked into the image | VM checkout bind-mounted at `/var/www/html` |
| PHP server | Octane / RoadRunner, caches config, routes, events | `php artisan serve`, no caches |
| Code change | Rebuild the image | Picked up on the next request |
| Assets | Built in the image | Vite dev server with HMR on `5173` |
| MySQL / Redis / Mailpit ports | All interfaces | `127.0.0.1` only |
| Use for | Checking the production image | Day-to-day work |

## First start

```bash
cp .env.example .env            # once; then set the values below
docker compose -f docker-compose.yml -f compose.dev.yaml up -d --build
docker compose -f docker-compose.yml -f compose.dev.yaml logs -f app vite
```

On first start the app container runs `composer install` into `./vendor` and the vite container runs
`npm ci` into `./node_modules`. Both directories are on the VM disk and gitignored. Watch the logs
until `artisan serve` and Vite report they are listening.

`.env` values the containers need (container-to-container names, not `127.0.0.1`):

```dotenv
DB_HOST=mysql
REDIS_HOST=redis
MAIL_HOST=mailpit
APP_URL=http://localhost:8000
```

Host ports only matter when another project already uses one. On this VM `marketplace_redis_cache`
holds `127.0.0.1:6379` and another project's Vite holds `127.0.0.1:5173`, so this checkout's `.env` has:

```dotenv
FORWARD_REDIS_PORT=6380
VITE_PORT=5174
```

Containers still talk to each other on the standard ports. `VITE_PORT` also sets the origin and HMR
port the browser is told to use, so forward the same number from VS Code.

Then, once, on an empty database:

```bash
docker compose exec app php artisan key:generate     # only if APP_KEY is empty
docker compose exec app php artisan migrate --seed
```

Seeders are not all idempotent. Do not re-run `--seed` on a database you care about.

## Opening it from the Mac

Use VS Code Remote-SSH into the VM and forward ports `8000`, `5173` and `8025` from the **Ports**
tab (`5174` instead of `5173` if you set `VITE_PORT=5174`). Browse `http://localhost:8000`. The storefront resolves its store from the hostname and only
`localhost` and `127.0.0.1` are bound to the default channel, so `http://<vm-ip>:8000` returns the
app's 404. Forward the Vite port as well, or the page loads without its CSS and JS.

## Daily commands

```bash
alias dc='docker compose -f docker-compose.yml -f compose.dev.yaml'
dc ps
dc logs -f app
dc exec app php artisan migrate
dc exec app php artisan test                       # sqlite :memory:, forced by phpunit.xml
dc exec app php artisan test --filter=CheckoutServiceTest
dc exec app composer lint
dc down                                            # keeps volumes
```

`phpunit.xml` forces every value (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `CACHE_DRIVER=array`, …) as both `<server>` and `<env>`.
The container's `DB_*` variables live in `$_SERVER`, which Laravel reads first; `<env force>` alone
does not reach it, and on 2026-11-11 that let `RefreshDatabase` wipe the dev database
(details in the untracked `private/SECURITY_GATE.md` §5). Do not remove the `<server>` lines; without them the container's `CACHE_DRIVER=file` also breaks 179 tests.

Never `dc down -v` unless you mean to delete the MySQL, Redis, storage and log volumes.

## Switching back to the production image

```bash
docker compose -f docker-compose.yml -f compose.dev.yaml down
docker compose up -d --build
```

The production image needs `spiral/roadrunner-http` and `spiral/roadrunner-cli` in `composer.lock`
(see `BASELINE_AUDIT.md` §5). The dev overlay does not use RoadRunner.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Page has no styles | `VITE_PORT` forwarded; `public/hot` exists while Vite runs and is removed when it stops |
| Edits not showing | You are on the dev overlay (`dc ps` shows `vite`); `.docker/dev/php.ini` mounted |
| `404` on the storefront | Hostname is not `localhost`/`127.0.0.1`; see "Opening it from the Mac" |
| App exits on start | `dc logs app` — usually `composer install` failing |
| Database unreachable | `DB_HOST=mysql`, not `localhost` |
| Vite `ENOSPC` watcher errors | `vite.config.js` `server.watch.ignored` must keep `storage/` and `vendor/` out |

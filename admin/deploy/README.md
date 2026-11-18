# Deploy artifacts

The React build writes directly into the docroot
(`/home/arkeonet/wiz.arkeonethiopia.com`), which would otherwise overwrite the
PHP front controller and the routing rules.

`index.php` and `.htaccess` here are the canonical copies. The deploy script
copies them into the docroot **after** every React build, so a frontend deploy
can never break the admin panel or the API.

| File | Destination | Purpose |
|---|---|---|
| `index.php` | docroot `/index.php` | bootstraps the app from `../wiz-app/public/index.php` |
| `.htaccess` | docroot `/.htaccess` | route precedence: `/api`, `/admin`, static files, SPA fallback |

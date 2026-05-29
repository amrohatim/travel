# Travel

Laravel 12 application for managing flights, bookings, offices, travelers, and admin workflows.

## Render deployment

This repository now includes a Docker-based deployment path for Render. That is the correct setup for this app because Render does not provide a native PHP runtime for Laravel services.

### Create the service

In Render, create a new Web Service from this repository and choose the `Docker` runtime.

### Required environment variables

Set these in Render before deploying:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=<output of php artisan key:generate --show>`
- `APP_URL=https://your-service-name.onrender.com`
- `RUN_MIGRATIONS=true` if you want migrations to run during startup

### Database configuration

Use a real database in production. Set the corresponding Laravel database variables for your Render database, for example:

- `DB_CONNECTION=pgsql`
- `DB_HOST=...`
- `DB_PORT=5432`
- `DB_DATABASE=...`
- `DB_USERNAME=...`
- `DB_PASSWORD=...`

If you create a Render Postgres instance, use its internal connection details.

### App behavior on Render

- Frontend assets are built during the Docker image build.
- The container starts Laravel with `php artisan serve` bound to `0.0.0.0:$PORT`, which satisfies Render's port requirement.
- Migrations are optional and controlled by `RUN_MIGRATIONS=true`.

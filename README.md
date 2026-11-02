# POC - ODS Reader + Validation

This repository contains:
- `symfony_api`: Symfony 6.4 backend API (ODS upload, validation, filtering, download)
- `poc-read-ods`: Vue 3 + Vite + Vuetify frontend
- Docker setup for running the backend with Nginx + PHP-FPM

## Prerequisites

Install the following tools:
- Docker Desktop (or Docker Engine + Docker Compose)
- Node.js 20+ and npm (for frontend local development)

Optional (if you want to run backend outside Docker):
- PHP 8.2+
- Composer 2+

## Project Structure

- `docker-compose.yml`: starts backend containers
- `Dockerfile`: PHP-FPM image with required extensions and Composer
- `docker/nginx/default.conf`: Nginx config
- `symfony_api/`: backend source
- `poc-read-ods/`: frontend source

## Installation and Run (Recommended: Docker + Local Frontend)

### 1. Clone and enter the repository

```bash
git clone <YOUR_REPO_URL>
cd POC
```

### 2. Build and start backend containers

From the project root:

```bash
docker compose up --build -d
```

This starts:
- `symfony_api_app` (PHP-FPM)
- `symfony_api_nginx` (Nginx, exposed on `http://localhost:8080`)

### 3. Install backend dependencies (inside container)

```bash
docker compose exec app composer install
```

### 4. Install and run frontend

In another terminal:

```bash
cd poc-read-ods
npm install
npm run dev
```

By default, Vite runs on `http://localhost:5173`.

## Access URLs

- Frontend: `http://localhost:5173`
- Backend API: `http://localhost:8080`

## Useful Commands

### View container logs

```bash
docker compose logs -f
```

### Restart services

```bash
docker compose restart
```

### Stop services

```bash
docker compose down
```

### Rebuild from scratch

```bash
docker compose down
docker compose up --build -d
```

## Run Without Docker (Optional)

### Backend (local PHP)

```bash
cd symfony_api
composer install
php -S 127.0.0.1:8080 -t public
```

### Frontend

```bash
cd poc-read-ods
npm install
npm run dev
```

## Common Troubleshooting

### Port 8080 already in use

Change the port mapping in `docker-compose.yml` (service `web`):
- from `8080:80`
- to another host port, e.g. `8081:80`

Then access backend at `http://localhost:8081`.

### Frontend cannot reach backend

Check that backend containers are running:

```bash
docker compose ps
```

Also confirm the frontend calls `http://localhost:8080` (as used in `poc-read-ods/src/components/HelloWorld.vue`).

### Permission/cache issues in Symfony

Run inside container:

```bash
docker compose exec app php bin/console cache:clear
```

## Notes

- The Docker image installs a custom certificate (`empresa.crt`) during build.
- If your environment uses a corporate proxy/certificate chain, keep `empresa.crt` at the repository root.

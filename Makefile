afl-schedules:
	php artisan api:afl:schedules

afl-schedule:
	php artisan api:afl:schedule

afl-standings:
	php artisan api:afl:standings

fetch-afl:
	php artisan api:afl --recurring

afl-all:
	php artisan api:afl:all

websocket-run:
	php artisan reverb:start --debug

api-afl-recurring:
	php artisan api:afl --recurring
api-afl-one-time:
	php artisan api:afl

nfl-scores:
	php artisan nfl:fetch-scores --force

nfl-standings:
	php artisan nfl:fetch-standings --force

clear-all:
	php artisan cache:clear
	php artisan config:clear
	php artisan route:clear
	php artisan view:clear

start-worker:
	php artisan queue:work
api-afl-boradcast:
	php artisan api:afl:broadcast:afl

migrate:
	php artisan migrate

docker-afl-schedules:
	docker compose exec app php artisan api:afl:schedules

docker-afl-schedule:
	docker compose exec app php artisan api:afl:schedule

docker-afl-standings:
	docker compose exec app php artisan api:afl:standings

docker-fetch-afl:
	docker compose exec app php artisan api:afl --recurring

docker-afl-all:
	docker compose exec app php artisan api:afl:all

docker-websocket-run:
	docker compose exec app php artisan reverb:start --debug

docker-api-afl-recurring:
	docker compose exec app php artisan api:afl --recurring
docker-api-afl-one-time:
	docker compose exec app php artisan api:afl

docker-nfl-scores:
	docker compose exec app php artisan nfl:fetch-scores --force --store

docker-nfl-schedules:
	docker compose exec app php artisan nfl:api:fetch-schedules  --store

docker-nfl-standings:
	docker compose exec app php artisan nfl:fetch-standings --force --season=2024
	docker compose exec app php artisan nfl:fetch-standings --force --season=2025

docker-clear-all:
	docker compose exec app php artisan cache:clear
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear

docker-start-worker:
	docker compose exec app php artisan queue:work
docker-api-afl-boradcast:
	docker compose exec app php artisan api:afl:broadcast:afl

docker-migrate:
	docker compose exec app php artisan migrate

docker-rollback:
	docker compose exec app php artisan migrate:rollback

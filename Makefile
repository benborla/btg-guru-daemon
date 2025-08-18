afl-schedules:
	docker compose exec app php artisan api:afl:schedules

afl-schedule:
	docker compose exec app php artisan api:afl:schedule

afl-standings:
	docker compose exec app php artisan api:afl:standings

fetch-afl:
	docker compose exec app php artisan api:sync --recurring

afl-all:
	docker compose exec app php artisan api:afl:all

websocket-run:
	docker compose exec app php artisan reverb:start --debug

api-afl-recurring:
	docker compose exec app php artisan api:afl --recurring
api-afl-one-time:
	docker compose exec app php artisan api:afl

clear-all:
	docker compose exec app php artisan cache:clear
	docker compose exec app php artisan config:clear
	docker compose exec app php artisan route:clear
	docker compose exec app php artisan view:clear
	
start-worker:
	docker compose exec app php artisan queue:work
api-afl-boradcast:
	docker compose exec app php artisan api:afl:broadcast:afl

migrate:
	docker compose exec app php artisan migrate


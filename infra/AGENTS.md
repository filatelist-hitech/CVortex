# Infrastructure scope

Keep Nginx as the only host-facing service and bind it to loopback by default. PostgreSQL, Redis, PHP-FPM, Horizon and private storage remain internal. Never make `make down` remove volumes or add production deployment machinery inside M0.

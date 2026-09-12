# Backend scope

M0 is a technical Laravel shell only. Keep `/api/v1` versioned, health output sanitized, private files outside `public/`, and request IDs correlated in responses and logs. Do not add authentication, business models, domain migrations, provider credentials or public Horizon routes before their owning task.

Run backend tests, Pint and Larastan after backend changes.

# Sanctum authentication migration

The API uses **Laravel Sanctum personal access tokens** (Bearer), not JWT.

## Backend

- **Middleware:** `auth:sanctum` on all protected routes (`routes/api.php`)
- **User model:** `HasApiTokens` on `App\Models\User`
- **Login:** `POST /api/auth/login` → `data.access_token` (format `{id}|{plainText}`)
- **Logout:** `POST /api/auth/logout` → revokes the current token
- **Refresh:** `POST /api/auth/refresh` → revokes old token, issues new one
- **Env:** `SANCTUM_TOKEN_EXPIRATION=1440` (minutes), `SANCTUM_STATEFUL_DOMAINS`, `FRONTEND_URL`

## Frontend (SMEFrontend)

No URL changes. Still send:

```
Authorization: Bearer {access_token}
Accept: application/json
```

Store `access_token` from login/refresh the same way (`irip_access_token` cookie + localStorage).

## After deploy

All existing JWT sessions are invalid. Users must log in again.

## Removed

- `php-open-source-saver/jwt-auth`
- `config/jwt.php`
- `JWT_*` environment variables

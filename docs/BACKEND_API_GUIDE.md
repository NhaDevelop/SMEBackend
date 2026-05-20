# 🚀 API Reference Guide for Frontend (Nuxt.js) Integration

This document outlines the complete RESTful backend API built in Laravel 11. It provides everything the frontend needs to integrate authentication, user profiles, and the admin dashboard.

---

## 🔒 1. Authentication Flow & Headers

The backend uses **JWT (JSON Web Tokens)** for authentication.

**Frontend Requirements:**

1. Upon successful login, store the returned `access_token` (e.g., in Nuxt cookies or localStorage).
2. For all protected routes (everything except `login` and `register`), you MUST attach the token to the HTTP Request Headers:
    ```json
    {
        "Authorization": "Bearer YOUR_JWT_ACCESS_TOKEN",
        "Accept": "application/json"
    }
    ```
3. Always include `Accept: application/json` so Laravel returns proper JSON error messages (e.g., `422 Unprocessable Entity`) instead of trying to redirect.

## 👥 2. Mock Test Accounts (Ready to Use)

The database has been seeded with three test accounts. Use these to log in and test your Nuxt UI.

| Role         | Email                  | Password   | Allowed Access                                              |
| :----------- | :--------------------- | :--------- | :---------------------------------------------------------- |
| **ADMIN**    | `admin@example.com`    | `password` | Can access _all_ endpoints.                                 |
| **SME**      | `sme@example.com`      | `password` | Can only access Auth endpoints. Profile is fully populated. |
| **INVESTOR** | `investor@example.com` | `password` | Can only access Auth endpoints. Profile is fully populated. |

---

## 🚀 3. Core Auth Endpoints (Public)

### Register a New User

- **Endpoint:** `POST /api/auth/register`
- **Description:** Creates a new user with `status: PENDING`. Generates an empty `sme_profile` or `investor_profile` structure in the database based on the selected role so you can PATCH to it later.

**Request Payload:**

```json
{
    "full_name": "Demo Company Name",
    "email": "demo@example.com",
    "password": "securepassword",
    "role": "SME" // MUST be exactly 'SME' or 'INVESTOR'
}
```

**Success Response (201 Created):**

```json
{
    "message": "Registration successful, awaiting admin approval"
}
```

### Log in

- **Endpoint:** `POST /api/auth/login`
- **Description:** Authenticates the user. **IMPORTANT:** If the user's status is `PENDING` or `REJECTED`, the API will deny the login with a 403 error.

**Request Payload:**

```json
{
    "email": "sme@example.com",
    "password": "password"
}
```

**Success Response (200 OK):**

```json
{
    "access_token": "eyJ0eXAiOiJKV1QiLC...",
    "token_type": "bearer",
    "expires_in": 3600
}
```

**Failed Login - Pending Approval (403 Forbidden):**

```json
{
    "error": "Account is pending approval or inactive"
}
```

---

## 🛡 4. Protected User Endpoints (Requires JWT)

### Get Current User Profile (Target: `auth('api')->user()`)

- **Endpoint:** `GET /api/auth/profile`
- **Description:** Returns the logged-in user. It automatically eager-loads their specific profile data (`sme_profile` or `investor_profile`) so you have it immediately available in Vuex/Pinia.

**Response Example (SME User):**

```json
{
    "id": 2,
    "full_name": "John SME Owner",
    "email": "sme@example.com",
    "role": "SME",
    "status": "ACTIVE",
    "sme_profile": {
        "company_name": "Tech Solutions Cambodia",
        "registration_number": "REG-123456789",
        "industry": "Technology",
        "stage": "Growth",
        "years_in_business": "3-5 Years",
        "team_size": "11-50",
        "readiness_score": 85,
        "risk_level": "Low Risk"
    }
}
```

### Log Out

- **Endpoint:** `POST /api/auth/logout`
- **Description:** Invalidates the current JWT token. The frontend should immediately clear the stored token and redirect to `/login`.

---

## 🏢 4.5 Profile Update Endpoints (Requires JWT)

### Update SME Profile

- **Endpoint:** `PATCH /api/sme/profile`
- **Description:** Allows an authenticated SME to update their company details. Returns 403 if the user is not an SME.

**Request Payload (All fields optional):**

```json
{
    "company_name": "New Name",
    "registration_number": "123-ABC",
    "industry": "Fintech",
    "stage": "Seed",
    "years_in_business": "1-2 Years",
    "team_size": "1-10",
    "address": "Phnom Penh"
}
```

### Update Investor Profile

- **Endpoint:** `PATCH /api/investor/profile`
- **Description:** Allows an authenticated Investor to update their organization details. Returns 403 if the user is not an Investor.

**Request Payload (All fields optional):**

```json
{
    "organization_name": "Capital Group",
    "investor_type": "Angel",
    "min_ticket_size": 10000,
    "max_ticket_size": 50000
}
```

---

## 👑 5. Admin Dashboard Endpoints (Requires JWT + ADMIN Role)

These endpoints will forcefully reject (403 Forbidden) any request made by an `SME` or `INVESTOR` token. Only the `ADMIN` can call these to populate the admin dashboard tables.

### Get Pending Registrations

- **Endpoint:** `GET /api/admin/users/pending`
- **Description:** Fetches all users who are waiting for approval (`status = PENDING`). Good for the "Pending Approvals" tab.

**Response (200 OK):**

```json
[
  {
    "id": 4,
    "full_name": "New Startup SME",
    "email": "new@example.com",
    "role": "SME",
    "status": "PENDING",
    "sme_profile": { ... },
    "investor_profile": null
  }
]
```

### Get Active Directory

- **Endpoint:** `GET /api/admin/users`
- **Description:** Fetches all users currently active in the system (`status = ACTIVE`). Good for the main admin user management table. Returns the same array format as above.

### Create User Manually

- **Endpoint:** `POST /api/admin/users`
- **Description:** Manually create any user (SME, Investor, or Admin) with full profile details.
- **Request Payload:**

```json
{
    "full_name": "John SME",
    "email": "john.sme@example.com",
    "password": "password123",
    "role": "SME",
    "status": "ACTIVE",
    "phone": "+855 12 345 678",
    "company_name": "Tech Solutions Cambodia",
    "industry": "Technology",
    "stage": "MVP",
    "years_in_business": "3-5 years",
    "team_size": "11-50",
    "registration_number": "REG-999888",
    "address": "Phnom Penh, Cambodia"
}
```

- **Success Response (201 Created):**

```json
{
    "message": "User created with profile successfully",
    "user": {
        "id": 10,
        "full_name": "John SME",
        "email": "john.sme@example.com",
        "role": "SME",
        "status": "ACTIVE",
        "sme_profile": {
            "company_name": "Tech Solutions Cambodia",
            "industry": "Technology",
            ...
        }
    }
}
```

### Approve or Reject a User

- **Endpoint:** `PATCH /api/admin/users/{id}/status`
- **Description:** The core action for the Admin. Changes the user's status and automatically logs the action in the `audit_logs` table on the backend.

**Request Payload:**

```json
{
    "action": "approve" // MUST be exactly 'approve' or 'reject'
}
```

**Response (200 OK):**

```json
{
    "message": "Status updated"
}
```

### Change User Role (Optional / Edge case)

- **Endpoint:** `PATCH /api/admin/users/{id}/role`
- **Request Payload:**

```json
{
    "role": "INVESTOR" // MUST be 'SME', 'INVESTOR', or 'ADMIN'
}
```

### Manual Password Reset (Set new password)

- **Endpoint:** `POST /api/admin/users/{id}/reset-password`
- **Description:** Allows the Admin to forcefully set a new password for a user.

**Request Payload:**

```json
{
    "password": "newsecurepassword123" // Minimum 8 characters
}
```

**Response (200 OK):**

```json
{
    "message": "User password updated successfully"
}
```

### Delete User (Revoke Access)

- **Endpoint:** `DELETE /api/admin/users/{id}`
- **Description:** Hard deletes the user from the database. My backend is configured with MySQL `ON DELETE CASCADE`, which means their SME Profile or Investor Profile will automatically be deleted alongside them without throwing foreign key errors.

**Response (200 OK):**

```json
{
    "message": "User deleted successfully"
}
```

### Get Audit Logs (Activity Trail)

- **Endpoint:** `GET /api/admin/audit-logs`
- **Description:** Fetches a paginated list of all admin actions (status changes, role updates, etc.) stored in the `audit_logs` table.

**Response (200 OK):**

```json
{
    "current_page": 1,
    "data": [
        {
            "id": 1,
            "action": "UPDATE_STATUS",
            "target_entity": "User",
            "target_id": 4,
            "details": "{\"old_status\":\"PENDING\",\"new_status\":\"ACTIVE\"}",
            "user": {
                "id": 1,
                "full_name": "System Admin"
            }
        }
    ],
    "total": 2
}
```

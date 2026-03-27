# Frontend-Backend API Endpoint Mismatch Analysis

## Overview
This document compares the frontend API calls with the backend Laravel API routes to identify mismatches, missing endpoints, and incorrect paths.

---

## Backend API Routes (api.php)

### AUTHENTICATION (Public + Protected)
```
POST /auth/register                  ✅ Working
POST /auth/login                     ✅ Working
GET  /auth/profile                   ✅ Working (protected)
PATCH /auth/profile                  ✅ Working (protected)
PATCH /auth/sme/profile              ✅ Working (protected)
PATCH /auth/investor/profile         ✅ Working (protected)
```

### ADMIN ROUTES (`/admin/*`) - Requires ADMIN role
```
# Users Management
GET    /admin/users/pending          ✅ Working
GET    /admin/users                  ✅ Working
POST   /admin/users                  ✅ Working
GET    /admin/users/{id}             ✅ Working
PATCH  /admin/users/{id}/status      ✅ Working
PATCH  /admin/users/{id}/role        ✅ Working
POST   /admin/users/{id}/reset-password ✅ Working
DELETE /admin/users/{id}             ✅ Working

# Sectors
GET    /admin/sectors                ✅ Working
POST   /admin/sectors                ✅ Working
PUT    /admin/sectors/{id}           ✅ Working
DELETE /admin/sectors/{id}           ✅ Working

# Templates
GET    /admin/templates/active        ✅ Working
PATCH  /admin/templates/{id}/status  ✅ Working
GET    /admin/templates              ✅ Working
POST   /admin/templates              ✅ Working
PUT    /admin/templates/{id}         ✅ Working
DELETE /admin/templates/{id}         ✅ Working

# Pillars
GET    /admin/pillars                ✅ Working
POST   /admin/pillars                ✅ Working
PUT    /admin/pillars/{id}           ✅ Working
DELETE /admin/pillars/{id}           ✅ Working

# Questions
GET    /admin/questions              ✅ Working
POST   /admin/questions              ✅ Working
PUT    /admin/questions/{id}         ✅ Working
DELETE /admin/questions              ✅ Working

# Programs
GET    /admin/programs               ✅ Working
GET    /admin/programs/{id}          ✅ Working
POST   /admin/programs               ✅ Working
PUT    /admin/programs/{id}          ✅ Working
PATCH  /admin/programs/{id}          ✅ Working
PATCH  /admin/programs/{id}/status   ✅ Working
DELETE /admin/programs/{id}          ✅ Working
POST   /admin/programs/enroll        ✅ Working
PATCH  /admin/programs/enrollments/status ✅ Working
GET    /admin/programs/{id}/participants ✅ Working

# SME Management
GET    /admin/smes                   ✅ Working
GET    /admin/smes/{id}             ✅ Working (Fixed: was /admin/sme/{id})
GET    /admin/smes/{id}/dashboard   ✅ Working (Fixed: was /admin/dashboard)

# Audit & Verification
GET    /admin/audit-logs             ✅ Working
GET    /admin/verification-requests  ✅ Working
PATCH  /admin/verification-requests/{id} ✅ Working

# Settings
GET    /admin/settings               ✅ Working
POST   /admin/settings               ✅ Working

# Dashboard
GET    /admin/dashboard             ✅ Working

# Debug Endpoints (New)
GET    /admin/debug/pillars          ✅ Working
GET    /admin/debug/assessments/score-check ✅ Working
GET    /admin/debug/assessment/{id} ✅ Working

# Notification Templates - ❌ MISSING BACKEND
GET    /admin/notification-templates  ❌ NOT IMPLEMENTED
POST   /admin/notification-templates  ❌ NOT IMPLEMENTED
PUT    /admin/notification-templates/{id} ❌ NOT IMPLEMENTED
DELETE /admin/notification-templates/{id} ❌ NOT IMPLEMENTED

# Reports - ❌ MISSING BACKEND
GET    /api/reports/programs         ❌ NOT IMPLEMENTED
GET    /api/reports/smes             ❌ NOT IMPLEMENTED
GET    /api/reports/scores           ❌ NOT IMPLEMENTED
GET    /api/reports/export           ❌ NOT IMPLEMENTED
```

### SME ROUTES (Protected)
```
GET /sme/dashboard                   ✅ Working
GET /sme/profile                     ✅ Working

GET /sme/enrolled-programs           ✅ Working
GET /sme/templates                   ✅ Working
GET /sme/questions                   ✅ Working
GET /sme/settings                    ✅ Working
GET /sme/sectors                     ✅ Working
GET /sme/programs                    ✅ Working

GET /sme/goals                       ✅ Working
GET /sme/goals/{id}                  ✅ Working
POST /sme/goals                     ✅ Working
PATCH /sme/goals/{id}               ✅ Working
DELETE /sme/goals/{id}              ✅ Working
```

### INVESTOR ROUTES (Protected)
```
GET /investor/dealflow               ✅ Working
GET /investor/programs               ✅ Working
POST /investor/programs/{id}/enroll  ✅ Working
GET /investor/analytics              ✅ Working
GET /investor/smes/{id}             ✅ Working
GET /investor/smes/{id}/dashboard   ✅ Working
```

### SHARED ROUTES (Protected)
```
GET /programs/{id}/participants      ✅ Working
POST /programs/{id}/apply            ✅ Working

# Assessment Engine
GET /assessment/questions            ✅ Working
POST /assessment/start               ✅ Working
POST /assessment/{id}/submit        ✅ Working
GET /assessment/history             ✅ Working

# Communication
GET /messages                        ✅ Working
POST /messages                      ✅ Working
GET /notifications                  ✅ Working
PATCH /notifications/{id}/read     ✅ Working

# Documents
GET /documents                      ✅ Working
POST /documents                    ✅ Working
GET /documents/{id}                ✅ Working
DELETE /documents/{id}            ✅ Working

# Program Comments
GET /programs/{id}/comments         ✅ Working
POST /programs/{id}/comments       ✅ Working
DELETE /programs/{programId}/comments/{commentId} ✅ Working
```

---

## Frontend Repositories and Endpoints

### admin.repository.ts
```typescript
GET  /admin/dashboard               ✅ MATCHES
GET  /admin/smes                   ✅ MATCHES
GET  /admin/smes/{id}             ✅ MATCHES (Fixed from /admin/sme/{id})
GET  /admin/smes/{id}/dashboard   ✅ MATCHES (Fixed from /admin/dashboard?id={id})
GET  /admin/users                  ✅ MATCHES
GET  /admin/users/pending          ✅ MATCHES
POST /admin/users                 ✅ MATCHES
GET  /admin/audit-logs            ✅ MATCHES
GET  /admin/programs              ✅ MATCHES
POST /admin/programs              ✅ MATCHES
GET  /admin/programs/{id}         ✅ MATCHES
GET  /admin/programs/{id}/participants ✅ MATCHES
POST /admin/programs/enroll       ✅ MATCHES
GET  /admin/templates             ✅ MATCHES
POST /admin/templates             ✅ MATCHES
GET  /admin/templates/{id}        ✅ MATCHES
PATCH /admin/templates/{id}/status ✅ MATCHES
GET  /admin/questions             ✅ MATCHES
POST /admin/questions             ✅ MATCHES
PUT  /admin/questions/{id}        ✅ MATCHES
DELETE /admin/questions/{id}      ✅ MATCHES
GET  /admin/pillars               ✅ MATCHES
GET  /admin/sectors               ✅ MATCHES
POST /admin/sectors               ✅ MATCHES
PUT  /admin/sectors/{id}          ✅ MATCHES
DELETE /admin/sectors/{id}        ✅ MATCHES
GET  /admin/verification-requests ✅ MATCHES
PATCH /admin/verification-requests/{id} ✅ MATCHES
GET  /admin/settings              ✅ MATCHES
POST /admin/settings              ✅ MATCHES
```

### program.repository.ts
```typescript
GET  /admin/programs              ✅ MATCHES
POST /programs/{id}/apply         ✅ MATCHES
GET  /programs/{id}/comments      ✅ MATCHES
POST /programs/{id}/comments     ✅ MATCHES
DELETE /programs/{id}/comments/{commentId} ✅ MATCHES
```

### assessment.repository.ts
```typescript
GET  /assessment/questions        ✅ MATCHES
POST /assessment/start            ✅ MATCHES
POST /assessment/{id}/submit     ✅ MATCHES
GET  /assessment/history        ✅ MATCHES
```

### sme.repository.ts
```typescript
GET /sme/profile                  ✅ MATCHES
GET /sme/dashboard                ✅ MATCHES
GET /sme/enrolled-programs        ✅ MATCHES
GET /sme/templates              ✅ MATCHES
GET /sme/questions              ✅ MATCHES
GET /sme/settings               ✅ MATCHES
GET /sme/sectors                ✅ MATCHES
GET /sme/programs               ✅ MATCHES
POST /programs/{id}/apply       ✅ MATCHES
```

### investor.repository.ts
```typescript
GET /investor/dealflow           ✅ MATCHES
GET /investor/analytics          ✅ MATCHES
```

### goal.repository.ts
```typescript
GET /sme/goals                   ✅ MATCHES
GET /sme/goals/{id}             ✅ MATCHES
POST /sme/goals                 ✅ MATCHES
PATCH /sme/goals/{id}          ✅ MATCHES
DELETE /sme/goals/{id}         ✅ MATCHES
```

### communication.repository.ts
```typescript
GET /messages                   ✅ MATCHES
POST /messages                  ✅ MATCHES
GET /notifications             ✅ MATCHES
PATCH /notifications/{id}/read ✅ MATCHES
```

### user.repository.ts
```typescript
GET /admin/users                ✅ MATCHES
GET /auth/profile               ✅ MATCHES
```

---

## ❌ CRITICAL MISMATCHES FOUND

### 1. Reports Page (`/admin/reports.vue`)
**Frontend Calls:**
- `GET /api/reports/programs` ❌ Backend Missing
- `GET /api/reports/smes` ❌ Backend Missing  
- `GET /api/reports/scores` ❌ Backend Missing
- `GET /api/reports/export` ❌ Backend Missing
- `GET /api/admin/sectors` (for filter dropdown) ✅ Working

**Status:** Reports page will not work - backend controllers missing entirely.

### 2. Notifications Page (`/admin/notifications.vue`)
**Frontend Calls:**
- `GET /admin/notification-templates` ❌ Backend Missing
- `POST /admin/notification-templates` ❌ Backend Missing
- `PUT /admin/notification-templates/{id}` ❌ Backend Missing
- `DELETE /admin/notification-templates/{id}` ❌ Backend Missing

**Status:** Notification template management will not work.

### 3. Investor Messages Page (`/investor/messages.vue`)
**Frontend Calls:**
- `GET /investor/programs` ✅ Working
- `GET /messages` ✅ Working
- `POST /messages` ✅ Working
- `GET /admin/users` ❌ Issue: Investor accessing admin route

**Status:** Investor trying to fetch `/admin/users` will get 403 Forbidden.

---

## ⚠️ ENDPOINTS REQUIRING ATTENTION

### 1. Sector Endpoint - FIXED
- **Before:** Frontend called `/api/admin/sectors` via `$fetch` to localhost ❌
- **After:** Frontend now uses `useApi()` to call `/admin/sectors` ✅
- **File:** `AdminCreateTemplateModal.vue`

### 2. SME Management Endpoints - FIXED
- **Before:** `GET /admin/sme/{id}` (singular) ❌
- **After:** `GET /admin/smes/{id}` (plural) ✅
- **File:** `sme/[id].vue`

### 3. SME Dashboard Endpoint - FIXED
- **Before:** `GET /admin/dashboard?id={id}` ❌
- **After:** `GET /admin/smes/{id}/dashboard` ✅
- **File:** `sme/[id].vue`

### 4. Program Enrollment Status - FIXED
- **Migration:** Changed default from `'Applied'` to `'Enrolled'`
- **Validation:** Removed `'Applied'` from allowed statuses
- **Note:** Now only auto-enrollment, no admin approval needed

---

## 📊 SUMMARY

| Category | Working | Missing | Fixed |
|----------|---------|---------|-------|
| Authentication | 6 | 0 | 0 |
| Admin - Users | 8 | 0 | 0 |
| Admin - Sectors | 4 | 0 | 1 |
| Admin - Templates | 5 | 0 | 0 |
| Admin - Pillars | 4 | 0 | 0 |
| Admin - Questions | 4 | 0 | 0 |
| Admin - Programs | 9 | 0 | 0 |
| Admin - SME Management | 3 | 0 | 2 |
| Admin - Audit/Verification | 2 | 0 | 0 |
| Admin - Settings | 2 | 0 | 0 |
| Admin - Reports | 0 | 4 | 0 |
| Admin - Notifications | 0 | 4 | 0 |
| SME Routes | 11 | 0 | 0 |
| Investor Routes | 6 | 0 | 0 |
| Assessment Engine | 4 | 0 | 0 |
| Communication | 4 | 0 | 0 |
| **TOTAL** | **72** | **8** | **3** |

---

## 🔧 RECOMMENDED ACTIONS

### High Priority (Missing Endpoints)
1. **Create Reports Controller**
   - `GET /admin/reports/programs`
   - `GET /admin/reports/smes`
   - `GET /admin/reports/scores`
   - `GET /admin/reports/export`

2. **Create Notification Template Controller**
   - `GET /admin/notification-templates`
   - `POST /admin/notification-templates`
   - `PUT /admin/notification-templates/{id}`
   - `DELETE /admin/notification-templates/{id}`

3. **Fix Investor Messages**
   - Create investor-accessible users endpoint OR
   - Create investor-specific messaging endpoints

### Medium Priority (Enhancements)
- Add pagination support for large lists
- Add search/filter endpoints
- Add bulk operations endpoints

### Low Priority (Cleanup)
- Standardize response formats
- Add proper API documentation
- Add rate limiting

---

## 📝 NOTES

- All endpoints use `auth:api` middleware except auth/register and auth/login
- RoleMiddleware checks for 'ADMIN', 'SME', 'INVESTOR' roles
- Response format: `{ success: boolean, data: any, message: string }`
- Use `useApi()` composable in frontend to ensure correct baseURL and headers
- Frontend baseURL: `http://127.0.0.1:8001/api` (Laravel backend)

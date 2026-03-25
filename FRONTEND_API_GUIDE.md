# 🚀 Frontend API Integration Guide

This guide explains how to connect the frontend to the Laravel backend.

## 📍 Base Configuration
- **Base URL:** `http://localhost:8000/api`
- **Auth Mode:** JWT (Bearer Token)

---

## 📋 Standard Response Format
Every API response now follows this consistent structure:
```json
{
  "success": true,
  "message": "Descriptive message here",
  "data": { ... } 
}
```
If an error occurs, `success` will be `false` and details will be in `message` or `errors`.

---

## 🔐 1. Authentication
All users (Admin, SME, Investor) start here.

### **Login**
- **URL:** `POST /auth/login`
- **Body:** `{ "email": "...", "password": "..." }`
- **Response `200`:**
  ```json
  {
    "success": true,
    "message": "Login successful",
    "data": {
      "access_token": "...",
      "token_type": "bearer",
      "expires_in": 3600
    }
  }
  ```
- **Note:** Will return `403` if the account status is not `ACTIVE`.

### **Register**
- **URL:** `POST /auth/register`
- **Body:** `{ "full_name": "...", "email": "...", "password": "...", "role": "SME" }` (Role: `SME` or `INVESTOR`)
- **Note:** Accounts start as `PENDING` and need Admin approval.

---

## 👤 2. Profile Management (Unified)
This is the **ONE route** to load all settings data.

### **Get Full Profile Info** (Loads based on your role)
- **URL:** `GET /auth/profile`
- **Description:** Returns the User object + nested profile data inside `data`.
- **Response `200`:**
  ```json
  {
    "success": true,
    "data": {
       "id": 1,
       "full_name": "...",
       "sme_profile": { ... }
    }
  }
  ```
  - **If SME:** Response includes `sme_profile: { ... }`
  - **If Investor:** Response includes `investor_profile: { ... }`

### **Update Profile**
- **SME:** `PATCH /api/auth/sme/profile`
- **Investor:** `PATCH /api/auth/investor/profile`
- **Security:** `POST /api/auth/change-password`

---

## 📄 3. Template Management
Manage the assessment questionnaires and their readiness lifecycle.

### **List All Templates**
- **URL:** `GET /api/admin/templates`
- **Response:** All templates with question counts.

### **List Active Templates**
- **URL:** `GET /api/admin/templates/active`
- **Use Case:** Use this for the dropdown in the **Create Program** modal.

### **Update Template Status**
- **URL:** `PATCH /api/admin/templates/{id}/status`
- **Body:** `{ "status": "Active" }` (Valid: `Draft`, `Active`, `Archived`)

---

## 🛠️ 4. Admin Tools
Only for users with `role: ADMIN`.

### **User Approvals**
- **List Pending:** `GET /admin/users/pending`
- **Approve/Reject:** `PATCH /admin/users/{id}/status`
  - Body: `{ "action": "approve" }` or `{ "action": "reject" }`

### **User Database**
- **List All Active:** `GET /admin/users`
- **Create User Manually:** `POST /admin/users`
- **Delete User:** `DELETE /admin/users/{id}`

---

## 📅 4. Program & Cohort Management
Handled by Admins, browsed by SMEs.

### **List All Programs**
- **URL:** `GET /admin/programs`
- **Response `200`:**
  ```json
  {
    "success": true,
    "data": {
      "programs": [
        {
          "id": 1,
          "name": "AgriTech 2026",
          "status": "Published",
          "startDate": "2026-01-01",
          "endDate": "2026-06-01",
          "duration": "5 Months"
        }
      ]
    }
  }
  ```

### **Create/Update Program**
- **POST/PATCH** `/admin/programs`
- **Fields:** `name`, `description`, `templateId`, `startDate`, `endDate`, `status`, `sector`, `investmentAmount`, `benefits`
- **Note on Duration:** Do **NOT** send `duration` in the request. The backend calculates it automatically from dates.
- **Note on Status:** 
  - `Coming Soon` (Default)
  - `Published` (Visible)
  - `Unpublished` (Hidden)
  - `Finished` (**Automation:** Backend auto-sets this if `endDate` passes).

### **Update Program Status**
- **URL:** `PATCH /admin/programs/{id}/status`
- **Body:** `{ "status": "Finished" }`

---

## ⚠️ 4. Common Error Codes
- `401 Unauthorized`: Token missing or expired.
- `403 Forbidden`: You don't have the right role or account is `PENDING`.
- `422 Unprocessable Content`: Validation error.
  ```json
  {
    "success": false,
    "message": "The email has already been taken.",
    "errors": { "email": ["..."] }
  }
  ```

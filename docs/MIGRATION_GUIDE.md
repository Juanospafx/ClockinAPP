# BrainWebApp v1 Migration Guide

## Page Routes
OLD → NEW

/frontend/ → /pages/

/frontend/index.html → /pages/index.html

/frontend/login/login.html → /pages/login/login.html

/frontend/login/logout.html → /pages/login/logout.html

/frontend/Registros/registros.html → /pages/registros/registros.html

/frontend/Users/admin/admin.html → /pages/users/admin/admin.html

/frontend/Users/add_user.php → /pages/users/add_user.php

## Asset Routes
OLD → NEW

/frontend/libs/* → /assets/*

/libs/* → /assets/*

## API Routes (Legacy → v1)
OLD → NEW

POST /backend/Login.php → POST /api/v1/auth/login

GET /backend/Users.php → GET /api/v1/users

POST /backend/Users.php (action=create) → POST /api/v1/users

POST /backend/Users.php (action=update) → PUT /api/v1/users/{id}

POST /backend/Users.php (action=delete) → DELETE /api/v1/users/{id}

GET /backend/Projects.php → GET /api/v1/projects

POST /backend/Projects.php (action=create) → POST /api/v1/projects

POST /backend/Projects.php (action=delete) → DELETE /api/v1/projects/{id}

GET /backend/Registros.php?all=true → GET /api/v1/attendance?all=true

GET /backend/Registros.php?user_id=ID → GET /api/v1/attendance?user_id=ID

GET /backend/Registros.php?summary=true → GET /api/v1/attendance?summary=true

POST /backend/Registros.php → POST /api/v1/attendance

PUT /backend/Registros.php → PUT /api/v1/attendance/{id}

DELETE /backend/Registros.php → DELETE /api/v1/attendance/{id}

POST /backend/Admin.php (action=recalculate_daily_duration) → POST /api/v1/attendance/recalculate

GET /backend/get_my_timer.php → GET /api/v1/timers/me

GET /backend/get_active_timers.php → GET /api/v1/timers/active

POST /backend/update_timer_status.php → POST /api/v1/timers/{id}/status

POST /backend/GenerateQR.php → POST /api/v1/qrs

GET /backend/GeneratedQRs.php → GET /api/v1/qrs

DELETE /backend/GeneratedQRs.php → DELETE /api/v1/qrs/{id}

GET /backend/GetUserLocations.php → GET /api/v1/locations/users

GET /backend/GetUserLocations.php?user_id=ID&start_date=...&end_date=... → GET /api/v1/locations/history?user_id=ID&start_date=...&end_date=...

POST /backend/LogLocation.php → POST /api/v1/locations/log

GET /backend/roles.php → GET /api/v1/roles

GET /backend/get_api_key.php → GET /api/v1/maps/key

POST /uploads/upload.php → POST /api/v1/uploads/profile

## Response Format
All API v1 responses now follow:

- Success: `{ ok: true, data: {...} }`
- Error: `{ ok: false, error: { code, message, details? } }`

## Breaking Changes
- Legacy `/backend/*.php` endpoints are removed from the repo. Update any external consumers to `/api/v1/*`.
- Legacy `/uploads/upload.php` is removed. Use `/api/v1/uploads/profile`.

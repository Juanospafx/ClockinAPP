# BrainWebApp v1 Refactor Renames

OLD_PATH → NEW_PATH — Reason

frontend/index.html → pages/index.html — UI entrypoint moved to `pages/` standard.

frontend/login/login.html → pages/login/login.html — Pages grouped by feature.

frontend/login/logout.html → pages/login/logout.html — Pages grouped by feature.

frontend/Registros/registros.html → pages/registros/registros.html — Pages grouped by feature.

frontend/Users/admin/admin.html → pages/users/admin/admin.html — Pages grouped by feature.

frontend/Users/add_user.php → pages/users/add_user.php — Pages grouped by feature.

libs/css/* → assets/css/* — Standardized assets location.

libs/js/script.js → assets/js/script.js — Standardized assets location.

libs/js/functions.js → (removed) — Unused legacy script referencing `ajax.php`.

libs/images/* → assets/images/* — Standardized assets location.

backend/.env → .env — Centralized config in repo root for core bootstrap.

backend/includes/config.php → core/config.php — Centralized config loader.

backend/includes/db.php → core/db.php — Centralized PDO connection.

backend/includes/cors.php → core/cors.php — Centralized CORS.

backend/includes/auth.php → core/services/AuthService.php + core/middlewares/auth.php — Auth normalized into services/middleware.

backend/includes/attendance.php → core/services/AttendanceService.php + funciones/time.php — Business logic moved to services, pure time helpers isolated.

backend/Login.php → api/v1/controllers/auth.php — API v1 controller.

backend/Users.php → api/v1/controllers/users.php — API v1 controller.

backend/Projects.php → api/v1/controllers/projects.php — API v1 controller.

backend/Registros.php → api/v1/controllers/attendance.php — API v1 controller.

backend/get_active_timers.php → api/v1/controllers/timers.php — API v1 controller.

backend/get_my_timer.php → api/v1/controllers/timers.php — API v1 controller.

backend/update_timer_status.php → api/v1/controllers/timers.php — API v1 controller.

backend/GenerateQR.php → api/v1/controllers/qrs.php — API v1 controller.

backend/GeneratedQRs.php → api/v1/controllers/qrs.php — API v1 controller.

backend/GetUserLocations.php → api/v1/controllers/locations.php — API v1 controller.

backend/LogLocation.php → api/v1/controllers/locations.php — API v1 controller.

backend/roles.php → api/v1/controllers/roles.php — API v1 controller.

backend/get_api_key.php → api/v1/controllers/maps.php — API v1 controller.

uploads/upload.php → api/v1/controllers/uploads.php — Standardized upload endpoint.

backend/alter_attendance_records_table.php → scripts/db/alter_attendance_records_table.php — DB utility moved to scripts.

backend/alter_table_add_lunch_duration.php → scripts/db/alter_table_add_lunch_duration.php — DB utility moved to scripts.

backend/create_location_history_table.php → scripts/db/create_location_history_table.php — DB utility moved to scripts.

backend/create_table.php → scripts/db/create_table.php — DB utility moved to scripts.

backend/describe_attendance_records.php → scripts/db/describe_attendance_records.php — DB utility moved to scripts.

backend/get_users_schema.php → scripts/db/get_users_schema.php — DB utility moved to scripts.

backend/update_schema_for_projects.php → scripts/db/update_schema_for_projects.php — DB utility moved to scripts.

backend/update_users_table.php → scripts/db/update_users_table.php — DB utility moved to scripts.

backend/check_or_create_admin.php → scripts/security/check_or_create_admin.php — Admin utility moved to scripts.

backend/hash_admin_password.php → scripts/security/hash_admin_password.php — Admin utility moved to scripts.

backend/hash_passwords.php → scripts/security/hash_passwords.php — Admin utility moved to scripts.

backend/reset_admin_password.php → scripts/security/reset_admin_password.php — Admin utility moved to scripts.

backend/reset_admin_password_plaintext.php → scripts/security/reset_admin_password_plaintext.php — Admin utility moved to scripts.

backend/debug_test.php → scripts/diagnostics/debug_test.php — Diagnostics moved to scripts.

backend/test_write.php → scripts/diagnostics/test_write.php — Diagnostics moved to scripts.

brightro_qrapp_inv (1).sql → docs/db/brightro_qrapp_inv.sql — Centralized DB dump under docs.

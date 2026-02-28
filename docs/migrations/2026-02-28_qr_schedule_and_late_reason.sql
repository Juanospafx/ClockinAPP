-- Schedule config on project QR + late reason in attendance
ALTER TABLE project_qrs
  ADD COLUMN entry_time_required TIME NULL,
  ADD COLUMN exit_time_optional TIME NULL;

ALTER TABLE attendance_records
  ADD COLUMN late_reason VARCHAR(255) NULL;

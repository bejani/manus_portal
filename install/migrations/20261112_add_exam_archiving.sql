-- Soft archive exams without deleting their attempts, answers, questions or reports.
ALTER TABLE exams
  ADD COLUMN archived_at DATETIME NULL DEFAULT NULL AFTER status,
  ADD INDEX idx_exams_archived_status (archived_at, status);

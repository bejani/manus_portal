-- Ensure teacher/class links exist on older installs and backfill from explicit course assignments.
CREATE TABLE IF NOT EXISTS teacher_classes (
  teacher_id INT UNSIGNED NOT NULL,
  class_id INT UNSIGNED NOT NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (teacher_id, class_id),
  INDEX idx_teacher_classes_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO teacher_classes (teacher_id, class_id)
SELECT DISTINCT teacher_id, class_id FROM class_subjects;

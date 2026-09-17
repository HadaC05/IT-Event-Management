-- Auditable staging for large student-roster imports.
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS tbl_student_import_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    original_filename VARCHAR(255) NOT NULL,
    file_sha256 CHAR(64) NOT NULL,
    mode ENUM('preview', 'replace') NOT NULL DEFAULT 'preview',
    status ENUM('previewed', 'applying', 'completed', 'failed') NOT NULL DEFAULT 'previewed',
    total_rows INT UNSIGNED NOT NULL DEFAULT 0,
    ready_rows INT UNSIGNED NOT NULL DEFAULT 0,
    warning_rows INT UNSIGNED NOT NULL DEFAULT 0,
    review_rows INT UNSIGNED NOT NULL DEFAULT 0,
    blocked_rows INT UNSIGNED NOT NULL DEFAULT 0,
    imported_rows INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_rows INT UNSIGNED NOT NULL DEFAULT 0,
    imported_by BIGINT UNSIGNED NULL,
    failure_message VARCHAR(1000) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    applied_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY student_import_batches_status_index (status),
    KEY student_import_batches_imported_by_index (imported_by),
    CONSTRAINT student_import_batches_imported_by_foreign
        FOREIGN KEY (imported_by) REFERENCES tbl_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_student_profiles (
    user_id BIGINT UNSIGNED NOT NULL,
    record_key VARCHAR(255) NOT NULL,
    gender VARCHAR(50) NULL,
    campus VARCHAR(255) NULL,
    program VARCHAR(255) NULL,
    section_name VARCHAR(255) NULL,
    school_year_label VARCHAR(100) NULL,
    enrollment_status VARCHAR(255) NULL,
    source_files TEXT NULL,
    last_import_batch_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY student_profiles_record_key_unique (record_key),
    KEY student_profiles_last_import_batch_index (last_import_batch_id),
    CONSTRAINT student_profiles_user_foreign
        FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE,
    CONSTRAINT student_profiles_last_import_batch_foreign
        FOREIGN KEY (last_import_batch_id) REFERENCES tbl_student_import_batches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_student_import_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    source_row INT UNSIGNED NOT NULL,
    record_key VARCHAR(255) NULL,
    student_id VARCHAR(255) NULL,
    official_name VARCHAR(500) NULL,
    email VARCHAR(255) NULL,
    gender VARCHAR(50) NULL,
    campus VARCHAR(255) NULL,
    program VARCHAR(255) NULL,
    year_level VARCHAR(50) NULL,
    section_name VARCHAR(255) NULL,
    tribe VARCHAR(255) NULL,
    school_year VARCHAR(100) NULL,
    enrollment_status VARCHAR(255) NULL,
    source_files TEXT NULL,
    source_issues TEXT NULL,
    review_resolution VARCHAR(100) NULL,
    source_row_status VARCHAR(50) NULL,
    validation_status ENUM('ready', 'warning', 'review', 'blocked', 'imported') NOT NULL,
    flags_json LONGTEXT NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY student_import_rows_batch_status_index (batch_id, validation_status),
    KEY student_import_rows_student_id_index (student_id),
    KEY student_import_rows_record_key_index (record_key),
    KEY student_import_rows_user_index (user_id),
    CONSTRAINT student_import_rows_batch_foreign
        FOREIGN KEY (batch_id) REFERENCES tbl_student_import_batches(id) ON DELETE CASCADE,
    CONSTRAINT student_import_rows_user_foreign
        FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

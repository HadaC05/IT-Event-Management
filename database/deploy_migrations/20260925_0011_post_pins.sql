-- Adviser-pinned approved posts appear first in the media feed. Safe to retry.
SET @post_pin_column_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tbl_posts' AND column_name='is_pinned'),
  'SELECT 1',
  'ALTER TABLE tbl_posts ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE post_pin_column_statement FROM @post_pin_column_sql;
EXECUTE post_pin_column_statement;
DEALLOCATE PREPARE post_pin_column_statement;

SET @post_pin_time_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tbl_posts' AND column_name='pinned_at'),
  'SELECT 1',
  'ALTER TABLE tbl_posts ADD COLUMN pinned_at DATETIME DEFAULT NULL'
);
PREPARE post_pin_time_statement FROM @post_pin_time_sql;
EXECUTE post_pin_time_statement;
DEALLOCATE PREPARE post_pin_time_statement;

SET @post_pin_index_sql = IF(
  EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='tbl_posts' AND index_name='posts_pinned_feed_index'),
  'SELECT 1',
  'ALTER TABLE tbl_posts ADD KEY posts_pinned_feed_index (status,deleted_at,is_pinned,pinned_at,id)'
);
PREPARE post_pin_index_statement FROM @post_pin_index_sql;
EXECUTE post_pin_index_statement;
DEALLOCATE PREPARE post_pin_index_statement;

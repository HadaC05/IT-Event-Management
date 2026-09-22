-- Keep existing comments and mark them unpinned by default. Safe to retry.
SET @post_comments_pin_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.columns
    WHERE table_schema=DATABASE()
      AND table_name='tbl_post_comments'
      AND column_name='is_pinned'
  ),
  'SELECT 1',
  'ALTER TABLE tbl_post_comments ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE post_comments_pin_statement FROM @post_comments_pin_sql;
EXECUTE post_comments_pin_statement;
DEALLOCATE PREPARE post_comments_pin_statement;

-- Preserve existing comments while allowing one level of replies beneath a comment.
SET @comment_parent_column_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.columns
    WHERE table_schema=DATABASE()
      AND table_name='tbl_post_comments'
      AND column_name='parent_comment_id'
  ),
  'SELECT 1',
  'ALTER TABLE tbl_post_comments ADD COLUMN parent_comment_id BIGINT UNSIGNED NULL AFTER post_id'
);
PREPARE comment_parent_column_statement FROM @comment_parent_column_sql;
EXECUTE comment_parent_column_statement;
DEALLOCATE PREPARE comment_parent_column_statement;

SET @comment_parent_index_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema=DATABASE()
      AND table_name='tbl_post_comments'
      AND index_name='post_comments_parent_created_index'
  ),
  'SELECT 1',
  'ALTER TABLE tbl_post_comments ADD INDEX post_comments_parent_created_index (parent_comment_id,created_at,id)'
);
PREPARE comment_parent_index_statement FROM @comment_parent_index_sql;
EXECUTE comment_parent_index_statement;
DEALLOCATE PREPARE comment_parent_index_statement;

SET @comment_parent_foreign_sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.table_constraints
    WHERE constraint_schema=DATABASE()
      AND table_name='tbl_post_comments'
      AND constraint_name='post_comments_parent_foreign'
      AND constraint_type='FOREIGN KEY'
  ),
  'SELECT 1',
  'ALTER TABLE tbl_post_comments ADD CONSTRAINT post_comments_parent_foreign FOREIGN KEY (parent_comment_id) REFERENCES tbl_post_comments(id) ON DELETE CASCADE'
);
PREPARE comment_parent_foreign_statement FROM @comment_parent_foreign_sql;
EXECUTE comment_parent_foreign_statement;
DEALLOCATE PREPARE comment_parent_foreign_statement;

-- The shared feed now uses one clear heart-based Like reaction.
-- Preserve all existing engagement by converting earlier reaction types.
UPDATE tbl_post_reactions
SET type = 'like',
    updated_at = CURRENT_TIMESTAMP
WHERE type <> 'like';

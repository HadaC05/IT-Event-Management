-- Associate old event-level scoring criteria with the event's only activity.
-- Events with multiple activities are intentionally left unchanged because the
-- correct activity cannot be inferred safely.
UPDATE tbl_score_categories AS category
JOIN (
    SELECT event_id, MIN(id) AS activity_id
    FROM tbl_event_activities
    GROUP BY event_id
    HAVING COUNT(*) = 1
) AS only_activity ON only_activity.event_id = category.event_id
SET category.activity_id = only_activity.activity_id,
    category.updated_at = CURRENT_TIMESTAMP
WHERE category.activity_id IS NULL;

-- Common criterion names (for example, Creativity) may be reused by different
-- activities while remaining unique inside the same activity.
ALTER TABLE tbl_score_categories
    DROP INDEX score_categories_event_id_name_unique,
    ADD UNIQUE KEY score_categories_event_activity_name_unique (event_id, activity_id, name);

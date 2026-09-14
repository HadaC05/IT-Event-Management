<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';

final class EventRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function homepageEvents(): array
    {
        $statement = $this->database->prepare(
            <<<'SQL'
                SELECT
                    tbl_events.id,
                    tbl_events.title,
                    tbl_events.description,
                    tbl_events.location,
                    tbl_events.poster_path,
                    tbl_events.is_featured,
                    tbl_events.featured_order,
                    tbl_events.featured_until,
                    tbl_events.start_at,
                    tbl_events.end_at,
                    COALESCE(tbl_event_types.label, 'Community Event') AS type,
                    tbl_event_statuses.label AS status
                FROM tbl_events
                LEFT JOIN tbl_event_types ON tbl_event_types.id = tbl_events.event_type_id
                LEFT JOIN tbl_event_statuses ON tbl_event_statuses.id = tbl_events.event_status_id
                WHERE tbl_events.deleted_at IS NULL
                  AND tbl_events.end_at >= CURRENT_TIMESTAMP
                  AND tbl_event_statuses.label IN ('upcoming', 'ongoing')
                ORDER BY tbl_events.start_at, tbl_events.id
                LIMIT 8
            SQL
        );
        $statement->execute();

        $events = array_map([$this, 'normalizeEvent'], $statement->fetchAll());
        $featured = array_values(array_filter(
            $events,
            static fn (array $event): bool => $event['is_featured']
                && ($event['featured_until'] === null || strtotime($event['featured_until']) >= time())
        ));

        usort($featured, static function (array $left, array $right): int {
            $leftOrder = $left['featured_order'] ?? PHP_INT_MAX;
            $rightOrder = $right['featured_order'] ?? PHP_INT_MAX;

            return [$leftOrder, $left['start_at']] <=> [$rightOrder, $right['start_at']];
        });

        return [
            'upcoming' => array_slice($events, 0, 5),
            'calendar' => array_slice($events, 0, 3),
            'featured' => array_slice($featured, 0, 6),
        ];
    }

    private function normalizeEvent(array $event): array
    {
        $now = time();
        $start = strtotime((string) $event['start_at']);
        $end = strtotime((string) $event['end_at']);

        $event['id'] = (int) $event['id'];
        $event['is_featured'] = (bool) $event['is_featured'];
        $event['featured_order'] = $event['featured_order'] === null ? null : (int) $event['featured_order'];
        $event['location'] = $event['location'] ?: 'CITE Campus';
        $event['timing'] = $start > $now ? 'upcoming' : ($end < $now ? 'completed' : 'current');

        return $event;
    }
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $database = new Database();
    $repository = new EventRepository($database->connection());

    echo json_encode([
        'success' => true,
        'data' => $repository->homepageEvents(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(500);
    error_log($exception->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'The event data could not be loaded.',
    ]);
}

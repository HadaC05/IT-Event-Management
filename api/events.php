<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';

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
                    events.id,
                    events.title,
                    events.description,
                    events.location,
                    events.poster_path,
                    events.is_featured,
                    events.featured_order,
                    events.featured_until,
                    events.start_at,
                    events.end_at,
                    COALESCE(event_types.label, 'Community Event') AS type,
                    event_statuses.label AS status
                FROM events
                LEFT JOIN event_types ON event_types.id = events.event_type_id
                LEFT JOIN event_statuses ON event_statuses.id = events.event_status_id
                WHERE events.deleted_at IS NULL
                  AND datetime(events.end_at) >= datetime('now', 'localtime')
                  AND event_statuses.label IN ('upcoming', 'ongoing')
                ORDER BY datetime(events.start_at), events.id
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

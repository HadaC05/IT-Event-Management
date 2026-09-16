<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class AdviserLocationRepository
{
    public function __construct(private readonly PDO $db) {}

    public function index(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $type = in_array(($filters['type'] ?? ''), ['general', 'specific'], true) ? (string) $filters['type'] : '';
        $status = in_array(($filters['status'] ?? ''), ['configured', 'pending'], true) ? (string) $filters['status'] : '';
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = PageSize::from($filters, 10);
        $where = [];
        $parameters = [];

        if ($search !== '') {
            $where[] = "(l.name LIKE ? ESCAPE '\\\\' OR parent.name LIKE ? ESCAPE '\\\\')";
            $parameters[] = '%'.addcslashes($search, '%_\\').'%';
            $parameters[] = '%'.addcslashes($search, '%_\\').'%';
        }
        if ($type !== '') {
            $where[] = 'l.type = ?';
            $parameters[] = $type;
        }
        if ($status === 'configured') {
            $where[] = 'l.latitude IS NOT NULL AND l.longitude IS NOT NULL AND l.radius IS NOT NULL';
        } elseif ($status === 'pending') {
            $where[] = '(l.latitude IS NULL OR l.longitude IS NULL OR l.radius IS NULL)';
        }

        $whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';
        $countStatement = $this->db->prepare('SELECT COUNT(*) FROM tbl_locations l LEFT JOIN tbl_locations parent ON parent.id=l.parent_location_id'.$whereSql);
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $statement = $this->db->prepare(
            "SELECT l.id, l.name, l.type, l.parent_location_id, parent.name parent_location_name,
                    l.latitude, l.longitude, l.radius, l.created_at, l.updated_at
             FROM tbl_locations l
             LEFT JOIN tbl_locations parent ON parent.id=l.parent_location_id{$whereSql}
             ORDER BY COALESCE(parent.name,l.name),l.type,l.name
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $statement->execute($parameters);
        $rows = $statement->fetchAll();

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['parent_location_id'] = $row['parent_location_id'] === null ? null : (int) $row['parent_location_id'];
            $row['latitude'] = $row['latitude'] === null ? null : (float) $row['latitude'];
            $row['longitude'] = $row['longitude'] === null ? null : (float) $row['longitude'];
            $row['radius'] = $row['radius'] === null ? null : (float) $row['radius'];
            $row['configured'] = $row['latitude'] !== null && $row['longitude'] !== null && $row['radius'] !== null;
        }
        unset($row);

        return [
            'locations' => $rows,
            'general_locations' => $this->generalLocations(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }

    public function create(array $input): array
    {
        $data = $this->validate($input);
        $statement = $this->db->prepare(
            "INSERT INTO tbl_locations (name, type, parent_location_id, latitude, longitude, radius, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );

        try {
            $statement->execute([$data['name'], $data['type'], $data['parent_location_id'], $data['latitude'], $data['longitude'], $data['radius']]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new InvalidArgumentException('A location with this name already exists.');
            }
            throw $exception;
        }

        return ['id' => (int) $this->db->lastInsertId()] + $data;
    }

    public function update(int $id, array $input): array
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Select a valid location to edit.');
        }
        $exists = $this->db->prepare('SELECT COUNT(*) FROM tbl_locations WHERE id = ?');
        $exists->execute([$id]);
        if (!(bool) $exists->fetchColumn()) {
            throw new InvalidArgumentException('The selected location was not found.');
        }

        $data = $this->validate($input, $id);
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                "UPDATE tbl_locations
                 SET name = ?, type = ?, parent_location_id = ?, latitude = ?, longitude = ?, radius = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $statement->execute([$data['name'], $data['type'], $data['parent_location_id'], $data['latitude'], $data['longitude'], $data['radius'], $id]);
            $this->db->prepare('UPDATE tbl_events SET location=?,updated_at=CURRENT_TIMESTAMP WHERE location_id=?')
                ->execute([$data['name'], $id]);
            $this->db->commit();
        } catch (PDOException $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ((string) $exception->getCode() === '23000') {
                throw new InvalidArgumentException('A location with this name already exists.');
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }

        return ['id' => $id] + $data;
    }

    private function validate(array $input, ?int $editingId = null): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Location name is required and may not exceed 255 characters.');
        }
        $type = (string) ($input['type'] ?? '');
        if (!in_array($type, ['general', 'specific'], true)) {
            throw new InvalidArgumentException('Select either a general or specific location type.');
        }
        $parentLocationId = $type === 'specific' ? (int) ($input['parent_location_id'] ?? 0) : null;
        if ($type === 'specific') {
            if ($parentLocationId < 1) {
                throw new InvalidArgumentException('Select the general location that contains this specific location.');
            }
            $parent = $this->db->prepare("SELECT COUNT(*) FROM tbl_locations WHERE id=? AND type='general'");
            $parent->execute([$parentLocationId]);
            if (!(bool) $parent->fetchColumn()) {
                throw new InvalidArgumentException('Select a valid general parent location.');
            }
            if ($editingId !== null && $parentLocationId === $editingId) {
                throw new InvalidArgumentException('A specific location cannot use itself as its general location.');
            }
            if ($editingId !== null) {
                $children = $this->db->prepare('SELECT COUNT(*) FROM tbl_locations WHERE parent_location_id=?');
                $children->execute([$editingId]);
                if ((int) $children->fetchColumn() > 0) {
                    throw new InvalidArgumentException('Move the specific locations to another general location before changing this location type.');
                }
            }
        }

        foreach (['latitude', 'longitude', 'radius'] as $field) {
            if (!array_key_exists($field, $input) || $input[$field] === '' || !is_numeric($input[$field])) {
                throw new InvalidArgumentException(ucfirst($field).' is required and must be a number.');
            }
        }

        $latitude = (float) $input['latitude'];
        $longitude = (float) $input['longitude'];
        $radius = (float) $input['radius'];
        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException('Latitude must be between -90 and 90.');
        }
        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Longitude must be between -180 and 180.');
        }
        if ($radius <= 0 || $radius > 100000) {
            throw new InvalidArgumentException('Radius must be greater than 0 and no more than 100,000 meters.');
        }

        return [
            'name' => $name,
            'type' => $type,
            'parent_location_id' => $parentLocationId,
            'latitude' => round($latitude, 7),
            'longitude' => round($longitude, 7),
            'radius' => round($radius, 2),
        ];
    }

    private function generalLocations(): array
    {
        $rows = $this->db->query("SELECT id,name FROM tbl_locations WHERE type='general' ORDER BY name")->fetchAll();
        foreach ($rows as &$row) $row['id'] = (int) $row['id'];
        unset($row);
        return $rows;
    }
}

$actor = AuthGuard::requireRole('SBO Adviser');
$repository = new AdviserLocationRepository((new Database())->connection());

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        JsonResponse::send(['success' => true, 'data' => $repository->index($_GET)]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        JsonResponse::send(['success' => false, 'message' => 'Your session expired.'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $action = (string) ($input['action'] ?? 'create');
    if ($action === 'create') {
        $location = $repository->create($input);
        JsonResponse::send(['success' => true, 'message' => 'Location and geofence created.', 'location' => $location], 201);
    }
    if ($action === 'update') {
        $location = $repository->update((int) ($input['id'] ?? 0), $input);
        JsonResponse::send(['success' => true, 'message' => 'Location and geofence updated.', 'location' => $location]);
    }
    throw new InvalidArgumentException('Unknown location action.');
} catch (InvalidArgumentException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'Location request failed. Ensure the geofence migration has been imported.'], 500);
}

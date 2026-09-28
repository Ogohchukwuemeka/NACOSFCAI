<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$portal = new Portal(Database::getInstance());
$pdo = Database::getInstance()->getConnection();
$executiveTenures = $portal->listExecutiveTenures();
$errors = [];
$success = '';
$activeTab = trim($_GET['tab'] ?? ($_SESSION['content_tab'] ?? 'events'));
$activeTab = in_array($activeTab, ['events', 'resources', 'executives', 'gallery', 'announcements'], true) ? $activeTab : 'events';
$_SESSION['content_tab'] = $activeTab;

function ensureUploadDirectory(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function writeJsonMetadata(string $path, array $data): void
{
    ensureUploadDirectory(dirname($path));
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function readJsonMetadata(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $raw = json_decode((string)file_get_contents($path), true);
    return is_array($raw) ? $raw : [];
}

function eventStatusLabel(array $row): string
{
    $meta = readJsonMetadata(__DIR__ . '/../assets/uploads/events/_meta/' . $row['id'] . '.json');
    $status = strtolower((string)($meta['status'] ?? ''));
    if ($status !== '') {
        return $status;
    }
    $isUpcoming = (int)($row['is_upcoming'] ?? 0);
    if ($isUpcoming === 2) {
        return 'archived';
    }
    if ($isUpcoming === 1) {
        $date = $row['event_date'] ?? '';
        if ($date !== '' && strtotime($date) < strtotime('today')) {
            return 'completed';
        }
        return 'upcoming';
    }
    $date = $row['event_date'] ?? '';
    if ($date !== '' && strtotime($date) === strtotime('today')) {
        return 'ongoing';
    }
    return 'completed';
}

function eventVisibilityLabel(array $row): string
{
    $meta = readJsonMetadata(__DIR__ . '/../assets/uploads/events/_meta/' . $row['id'] . '.json');
    return strtolower((string)($meta['visibility'] ?? 'published'));
}

function resourceMetadata(array $row): array
{
    return readJsonMetadata(__DIR__ . '/../assets/uploads/resources/_meta/' . $row['id'] . '.json');
}

function executiveMetadata(array $row): array
{
    return readJsonMetadata(__DIR__ . '/../assets/uploads/executives/_meta/' . $row['id'] . '.json');
}

function galleryMetadata(array $row): array
{
    return readJsonMetadata(__DIR__ . '/../assets/uploads/gallery/_meta/' . $row['id'] . '.json');
}

function announcementMetadata(array $row): array
{
    return readJsonMetadata(__DIR__ . '/../assets/uploads/announcements/_meta/' . $row['id'] . '.json');
}

function buildPagination(array $items, int $page, int $perPage): array
{
    $total = count($items);
    $pages = max(1, (int)ceil($total / max(1, $perPage)));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;
    $slice = array_slice($items, $offset, $perPage);
    return ['items' => $slice, 'page' => $page, 'pages' => $pages, 'total' => $total];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = $_POST['action'] ?? '';
        $adminId = (int)($_SESSION['user_id'] ?? 0);

        if ($action === 'create_executive_tenure') {
          $tenureName = trim($_POST['tenure_name'] ?? '');
          $startsOn = trim($_POST['starts_on'] ?? '') ?: null;
          $endsOn = trim($_POST['ends_on'] ?? '') ?: null;
          if ($tenureName === '') {
            throw new Exception('Executive year or tenure name is required.');
          }
          $pdo->prepare('UPDATE executive_tenures SET is_current = 0 WHERE is_current = 1')->execute();
          $pdo->prepare('INSERT INTO executive_tenures (name, starts_on, ends_on, is_current) VALUES (:name, :starts_on, :ends_on, 1)')->execute([
            'name' => $tenureName,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
          ]);
          $success = 'Executive tenure created successfully.';
        } elseif ($action === 'create_event') {
            $title = trim($_POST['title'] ?? '');
            $venue = trim($_POST['venue'] ?? '');
            $eventDate = trim($_POST['event_date'] ?? '');
            $eventTime = trim($_POST['event_time'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $registrationLink = trim($_POST['registration_link'] ?? '');
            $status = trim($_POST['event_status'] ?? 'upcoming');
            $visibility = trim($_POST['visibility'] ?? 'published');
            $category = trim($_POST['event_category'] ?? 'General');
            $registrationDeadline = trim($_POST['registration_deadline'] ?? '');
            $posterPath = null;
            if (!empty($_FILES['poster']['name'])) {
                $targetDir = dirname(__DIR__) . '/assets/uploads/events/';
                ensureUploadDirectory($targetDir);
                $upload = uploadFile($_FILES['poster'], $targetDir, ['jpg', 'jpeg', 'png', 'webp', 'gif'], 8 * 1024 * 1024);
                if ($upload['success']) {
                    $posterPath = 'uploads/events/' . $upload['filename'];
                } else {
                    throw new Exception($upload['message']);
                }
            }

            $eventId = (int)$portal->createEvent([
                'title' => $title,
                'venue' => $venue,
                'event_date' => $eventDate,
                'event_time' => $eventTime,
                'description' => $description,
                'registration_link' => $registrationLink,
                'poster_path' => $posterPath,
                'is_upcoming' => $status === 'archived' ? 2 : ($status === 'upcoming' ? 1 : 0),
            ]);
            $metaPath = dirname(__DIR__) . '/assets/uploads/events/_meta/' . $eventId . '.json';
            writeJsonMetadata($metaPath, [
                'category' => $category,
                'venue' => $venue,
                'event_date' => $eventDate,
                'event_time' => $eventTime,
                'registration_deadline' => $registrationDeadline,
                'description' => $description,
                'registration_link' => $registrationLink,
                'status' => $status,
                'visibility' => $visibility,
                'poster_path' => $posterPath,
            ]);
            $success = 'Event created successfully.';
        } elseif ($action === 'update_event') {
            $eventId = (int)($_POST['event_id'] ?? 0);
            if ($eventId > 0) {
                $title = trim($_POST['title'] ?? '');
                $venue = trim($_POST['venue'] ?? '');
                $eventDate = trim($_POST['event_date'] ?? '');
                $eventTime = trim($_POST['event_time'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $registrationLink = trim($_POST['registration_link'] ?? '');
                $status = trim($_POST['event_status'] ?? 'upcoming');
                $visibility = trim($_POST['visibility'] ?? 'published');
                $category = trim($_POST['event_category'] ?? 'General');
                $registrationDeadline = trim($_POST['registration_deadline'] ?? '');
                $posterPath = null;
                if (!empty($_FILES['poster']['name'])) {
                    $targetDir = dirname(__DIR__) . '/assets/uploads/events/';
                    ensureUploadDirectory($targetDir);
                    $upload = uploadFile($_FILES['poster'], $targetDir, ['jpg', 'jpeg', 'png', 'webp', 'gif'], 8 * 1024 * 1024);
                    if ($upload['success']) {
                        $posterPath = 'uploads/events/' . $upload['filename'];
                    } else {
                        throw new Exception($upload['message']);
                    }
                }
                $stmt = $pdo->prepare('UPDATE events SET title = :title, venue = :venue, event_date = :event_date, event_time = :event_time, description = :description, registration_link = :registration_link, poster_path = COALESCE(:poster_path, poster_path), is_upcoming = :is_upcoming WHERE id = :id');
                $stmt->execute([
                    'title' => $title,
                    'venue' => $venue,
                    'event_date' => $eventDate,
                    'event_time' => $eventTime,
                    'description' => $description,
                    'registration_link' => $registrationLink,
                    'poster_path' => $posterPath,
                    'is_upcoming' => $status === 'archived' ? 2 : ($status === 'upcoming' ? 1 : 0),
                    'id' => $eventId,
                ]);
                $metaPath = dirname(__DIR__) . '/assets/uploads/events/_meta/' . $eventId . '.json';
                $meta = readJsonMetadata($metaPath);
                $meta['category'] = $category;
                $meta['venue'] = $venue;
                $meta['event_date'] = $eventDate;
                $meta['event_time'] = $eventTime;
                $meta['registration_deadline'] = $registrationDeadline;
                $meta['description'] = $description;
                $meta['registration_link'] = $registrationLink;
                $meta['status'] = $status;
                $meta['visibility'] = $visibility;
                if ($posterPath !== null) {
                    $meta['poster_path'] = $posterPath;
                }
                writeJsonMetadata($metaPath, $meta);
                $success = 'Event updated successfully.';
            }
        } elseif ($action === 'delete_event') {
            $eventId = (int)($_POST['event_id'] ?? 0);
            if ($eventId > 0) {
                $pdo->prepare('DELETE FROM events WHERE id = :id')->execute(['id' => $eventId]);
                @unlink(dirname(__DIR__) . '/assets/uploads/events/_meta/' . $eventId . '.json');
                $success = 'Event deleted.';
            }
        } elseif ($action === 'mark_event_status') {
            $eventId = (int)($_POST['event_id'] ?? 0);
            $status = trim($_POST['event_status'] ?? 'upcoming');
            if ($eventId > 0) {
                $pdo->prepare('UPDATE events SET is_upcoming = :is_upcoming WHERE id = :id')->execute([
                    'is_upcoming' => $status === 'archived' ? 2 : ($status === 'upcoming' ? 1 : 0),
                    'id' => $eventId,
                ]);
                $metaPath = dirname(__DIR__) . '/assets/uploads/events/_meta/' . $eventId . '.json';
                $meta = readJsonMetadata($metaPath);
                $meta['status'] = $status;
                writeJsonMetadata($metaPath, $meta);
                $success = 'Event status updated.';
            }
        } elseif ($action === 'toggle_event_visibility') {
            $eventId = (int)($_POST['event_id'] ?? 0);
            if ($eventId > 0) {
                $metaPath = dirname(__DIR__) . '/assets/uploads/events/_meta/' . $eventId . '.json';
                $meta = readJsonMetadata($metaPath);
                $meta['visibility'] = (($meta['visibility'] ?? 'published') === 'hidden') ? 'published' : 'hidden';
                writeJsonMetadata($metaPath, $meta);
                $success = 'Event visibility updated.';
            }
        } elseif ($action === 'create_resource') {
            $title = trim($_POST['title'] ?? '');
            $programme = trim($_POST['programme'] ?? '');
            $level = trim($_POST['level'] ?? '');
            $semester = trim($_POST['semester'] ?? '');
            $course = trim($_POST['course'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $academicSession = trim($_POST['academic_session'] ?? '');
            $visibility = trim($_POST['visibility'] ?? 'visible');
            $resourceType = trim($_POST['resource_type'] ?? 'file');
            $uploadedPath = null;
            $thumbnailPath = null;
            if (!empty($_FILES['file']['name'])) {
                $targetDir = dirname(__DIR__) . '/assets/uploads/resources/';
                ensureUploadDirectory($targetDir);
                $upload = uploadFile($_FILES['file'], $targetDir, ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'], 12 * 1024 * 1024);
                if ($upload['success']) {
                    $uploadedPath = 'uploads/resources/' . $upload['filename'];
                } else {
                    throw new Exception($upload['message']);
                }
            }
            if (!empty($_FILES['thumbnail']['name'])) {
                $thumbDir = dirname(__DIR__) . '/assets/uploads/resources/thumbnails/';
                ensureUploadDirectory($thumbDir);
                $thumbUpload = uploadFile($_FILES['thumbnail'], $thumbDir, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                if ($thumbUpload['success']) {
                    $thumbnailPath = 'uploads/resources/thumbnails/' . $thumbUpload['filename'];
                } else {
                    throw new Exception($thumbUpload['message']);
                }
            }
            $resourceId = (int)$portal->createResource([
                'title' => $title,
                'programme' => $programme,
                'level' => $level,
                'semester' => $semester,
                'course' => $course,
                'file_path' => $uploadedPath,
                'file_type' => $resourceType,
                'uploaded_by' => $adminId,
            ]);
            $metaPath = dirname(__DIR__) . '/assets/uploads/resources/_meta/' . $resourceId . '.json';
            writeJsonMetadata($metaPath, [
                'description' => $description,
                'academic_session' => $academicSession,
                'visibility' => $visibility,
                'download_count' => 0,
                'thumbnail_path' => $thumbnailPath,
                'resource_type' => $resourceType,
            ]);
            $success = 'Resource uploaded successfully.';
        } elseif ($action === 'update_resource') {
            $resourceId = (int)($_POST['resource_id'] ?? 0);
            if ($resourceId > 0) {
                $title = trim($_POST['title'] ?? '');
                $programme = trim($_POST['programme'] ?? '');
                $level = trim($_POST['level'] ?? '');
                $semester = trim($_POST['semester'] ?? '');
                if (strtolower($semester) === 'first') {
                    $semester = 'First Semester';
                } elseif (strtolower($semester) === 'second') {
                    $semester = 'Second Semester';
                }
                $course = trim($_POST['course'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $academicSession = trim($_POST['academic_session'] ?? '');
                $visibility = trim($_POST['visibility'] ?? 'visible');
                $resourceType = trim($_POST['resource_type'] ?? 'file');
                $uploadedPath = null;
                if (!empty($_FILES['file']['name'])) {
                    $targetDir = dirname(__DIR__) . '/assets/uploads/resources/';
                    ensureUploadDirectory($targetDir);
                    $upload = uploadFile($_FILES['file'], $targetDir, ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'txt', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'], 12 * 1024 * 1024);
                    if ($upload['success']) {
                        $uploadedPath = 'uploads/resources/' . $upload['filename'];
                    } else {
                        throw new Exception($upload['message']);
                    }
                }
                $thumbnailPath = null;
                if (!empty($_FILES['thumbnail']['name'])) {
                    $thumbDir = dirname(__DIR__) . '/assets/uploads/resources/thumbnails/';
                    ensureUploadDirectory($thumbDir);
                    $thumbUpload = uploadFile($_FILES['thumbnail'], $thumbDir, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                    if ($thumbUpload['success']) {
                        $thumbnailPath = 'uploads/resources/thumbnails/' . $thumbUpload['filename'];
                    } else {
                        throw new Exception($thumbUpload['message']);
                    }
                }
                $updates = [
                    'title' => $title,
                    'programme' => $programme,
                    'level' => $level,
                    'semester' => $semester,
                    'course' => $course,
                    'file_type' => $resourceType,
                    'id' => $resourceId,
                ];
                if ($uploadedPath !== null) {
                    $updates['file_path'] = $uploadedPath;
                    $pdo->prepare('UPDATE resources SET title = :title, programme = :programme, level = :level, semester = :semester, course = :course, file_path = :file_path, file_type = :file_type WHERE id = :id')->execute($updates + ['file_path' => $uploadedPath]);
                } else {
                    $pdo->prepare('UPDATE resources SET title = :title, programme = :programme, level = :level, semester = :semester, course = :course, file_type = :file_type WHERE id = :id')->execute($updates);
                }
                $metaPath = dirname(__DIR__) . '/assets/uploads/resources/_meta/' . $resourceId . '.json';
                $meta = readJsonMetadata($metaPath);
                $meta['description'] = $description;
                $meta['academic_session'] = $academicSession;
                $meta['visibility'] = $visibility;
                $meta['resource_type'] = $resourceType;
                if ($thumbnailPath !== null) {
                    $meta['thumbnail_path'] = $thumbnailPath;
                }
                writeJsonMetadata($metaPath, $meta);
                $success = 'Resource updated successfully.';
            }
        } elseif ($action === 'delete_resource') {
            $resourceId = (int)($_POST['resource_id'] ?? 0);
            if ($resourceId > 0) {
                $pdo->prepare('DELETE FROM resources WHERE id = :id')->execute(['id' => $resourceId]);
                @unlink(dirname(__DIR__) . '/assets/uploads/resources/_meta/' . $resourceId . '.json');
                $success = 'Resource deleted.';
            }
        } elseif ($action === 'create_executive') {
            $fullName = trim($_POST['full_name'] ?? '');
            $position = trim($_POST['position'] ?? '');
          $tenureId = (int)($_POST['tenure_id'] ?? 0);
            $department = trim($_POST['department'] ?? '');
            $academicLevel = trim($_POST['academic_level'] ?? '');
            $biography = trim($_POST['biography'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $visibility = trim($_POST['visibility'] ?? 'active');
            $passportPath = null;
            if (!empty($_FILES['passport']['name'])) {
                $targetDir = dirname(__DIR__) . '/assets/uploads/executives/';
                ensureUploadDirectory($targetDir);
                $upload = uploadFile($_FILES['passport'], $targetDir, ['jpg', 'jpeg', 'png', 'webp', 'gif'], 8 * 1024 * 1024);
                if ($upload['success']) {
                    $passportPath = 'uploads/executives/' . $upload['filename'];
                } else {
                    throw new Exception($upload['message']);
                }
            }
            $stmt = $pdo->prepare('INSERT INTO executives (tenure_id, full_name, position, department, academic_level, biography, contact, passport_path, created_at) VALUES (:tenure_id, :full_name, :position, :department, :academic_level, :biography, :contact, :passport_path, NOW())');
            $stmt->execute([
              'tenure_id' => $tenureId > 0 ? $tenureId : null,
                'full_name' => $fullName,
                'position' => $position,
                'department' => $department,
                'academic_level' => $academicLevel,
                'biography' => $biography,
                'contact' => $contact,
                'passport_path' => $passportPath,
            ]);
            $executiveId = (int)$pdo->lastInsertId();
            writeJsonMetadata(dirname(__DIR__) . '/assets/uploads/executives/_meta/' . $executiveId . '.json', ['visibility' => $visibility]);
            $success = 'Executive added successfully.';
        } elseif ($action === 'update_executive') {
            $executiveId = (int)($_POST['executive_id'] ?? 0);
            if ($executiveId > 0) {
                $fullName = trim($_POST['full_name'] ?? '');
                $position = trim($_POST['position'] ?? '');
            $tenureId = (int)($_POST['tenure_id'] ?? 0);
                $department = trim($_POST['department'] ?? '');
                $academicLevel = trim($_POST['academic_level'] ?? '');
                $biography = trim($_POST['biography'] ?? '');
                $contact = trim($_POST['contact'] ?? '');
                $visibility = trim($_POST['visibility'] ?? 'active');
                $passportPath = null;
                if (!empty($_FILES['passport']['name'])) {
                    $targetDir = dirname(__DIR__) . '/assets/uploads/executives/';
                    ensureUploadDirectory($targetDir);
                    $upload = uploadFile($_FILES['passport'], $targetDir, ['jpg', 'jpeg', 'png', 'webp', 'gif'], 8 * 1024 * 1024);
                    if ($upload['success']) {
                        $passportPath = 'uploads/executives/' . $upload['filename'];
                    } else {
                        throw new Exception($upload['message']);
                    }
                }
                $stmt = $pdo->prepare('UPDATE executives SET tenure_id = :tenure_id, full_name = :full_name, position = :position, department = :department, academic_level = :academic_level, biography = :biography, contact = :contact, passport_path = COALESCE(:passport_path, passport_path) WHERE id = :id');
                $stmt->execute([
                  'tenure_id' => $tenureId > 0 ? $tenureId : null,
                    'full_name' => $fullName,
                    'position' => $position,
                    'department' => $department,
                    'academic_level' => $academicLevel,
                    'biography' => $biography,
                    'contact' => $contact,
                    'passport_path' => $passportPath,
                    'id' => $executiveId,
                ]);
                $metaPath = dirname(__DIR__) . '/assets/uploads/executives/_meta/' . $executiveId . '.json';
                $meta = readJsonMetadata($metaPath);
                $meta['visibility'] = $visibility;
                writeJsonMetadata($metaPath, $meta);
                $success = 'Executive updated successfully.';
            }
        } elseif ($action === 'delete_executive') {
            $executiveId = (int)($_POST['executive_id'] ?? 0);
            if ($executiveId > 0) {
                $pdo->prepare('DELETE FROM executives WHERE id = :id')->execute(['id' => $executiveId]);
                @unlink(dirname(__DIR__) . '/assets/uploads/executives/_meta/' . $executiveId . '.json');
                $success = 'Executive deleted.';
            }
        } elseif ($action === 'toggle_executive_visibility') {
            $executiveId = (int)($_POST['executive_id'] ?? 0);
            if ($executiveId > 0) {
                $metaPath = dirname(__DIR__) . '/assets/uploads/executives/_meta/' . $executiveId . '.json';
                $meta = readJsonMetadata($metaPath);
                $meta['visibility'] = (($meta['visibility'] ?? 'active') === 'active') ? 'hidden' : 'active';
                writeJsonMetadata($metaPath, $meta);
                $success = 'Executive visibility updated.';
            }
        } elseif ($action === 'create_gallery') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? 'Others');
            $visibility = trim($_POST['visibility'] ?? 'visible');
            $displayOrder = (int)($_POST['display_order'] ?? 0);
            if (!empty($_FILES['gallery_files']['name'][0])) {
                $files = $_FILES['gallery_files'];
                for ($i = 0; $i < count($files['name']); $i++) {
                    if (empty($files['name'][$i])) {
                        continue;
                    }
                    $targetDir = dirname(__DIR__) . '/assets/uploads/gallery/';
                    ensureUploadDirectory($targetDir);
                    $upload = uploadFile([
                        'tmp_name' => $files['tmp_name'][$i],
                        'name' => $files['name'][$i],
                        'size' => $files['size'][$i],
                        'error' => $files['error'][$i],
                    ], $targetDir, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'], 12 * 1024 * 1024);
                    if (!$upload['success']) {
                        throw new Exception($upload['message']);
                    }
                    $stmt = $pdo->prepare('INSERT INTO gallery (title, description, image_path, category, created_at) VALUES (:title, :description, :image_path, :category, NOW())');
                    $stmt->execute([
                        'title' => $title ?: 'Gallery Item',
                        'description' => $description,
                        'image_path' => 'uploads/gallery/' . $upload['filename'],
                        'category' => $category,
                    ]);
                    $galleryId = (int)$pdo->lastInsertId();
                    writeJsonMetadata(dirname(__DIR__) . '/assets/uploads/gallery/_meta/' . $galleryId . '.json', [
                        'visibility' => $visibility,
                        'display_order' => $displayOrder,
                        'uploaded_at' => date('Y-m-d H:i:s'),
                    ]);
                    $displayOrder++;
                }
            }
            $success = 'Gallery uploads added successfully.';
        } elseif ($action === 'update_gallery') {
            $galleryId = (int)($_POST['gallery_id'] ?? 0);
            if ($galleryId > 0) {
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $category = trim($_POST['category'] ?? 'Others');
                $visibility = trim($_POST['visibility'] ?? 'visible');
                $displayOrder = (int)($_POST['display_order'] ?? 0);
                $stmt = $pdo->prepare('UPDATE gallery SET title = :title, description = :description, category = :category WHERE id = :id');
                $stmt->execute([
                    'title' => $title,
                    'description' => $description,
                    'category' => $category,
                    'id' => $galleryId,
                ]);
                $metaPath = dirname(__DIR__) . '/assets/uploads/gallery/_meta/' . $galleryId . '.json';
                $meta = readJsonMetadata($metaPath);
                $meta['visibility'] = $visibility;
                $meta['display_order'] = $displayOrder;
                writeJsonMetadata($metaPath, $meta);
                $success = 'Gallery item updated.';
            }
        } elseif ($action === 'delete_gallery') {
            $galleryId = (int)($_POST['gallery_id'] ?? 0);
            if ($galleryId > 0) {
                $stmt = $pdo->prepare('SELECT image_path FROM gallery WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $galleryId]);
                $row = $stmt->fetch();
                if ($row && !empty($row['image_path'])) {
                    $disk = dirname(__DIR__) . '/assets/' . ltrim($row['image_path'], '/');
                    if (is_file($disk)) {
                        @unlink($disk);
                    }
                }
                $pdo->prepare('DELETE FROM gallery WHERE id = :id')->execute(['id' => $galleryId]);
                @unlink(dirname(__DIR__) . '/assets/uploads/gallery/_meta/' . $galleryId . '.json');
                $success = 'Gallery item deleted.';
            }
        } elseif ($action === 'create_announcement') {
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $audience = trim($_POST['audience'] ?? 'Everyone');
            $level = trim($_POST['level'] ?? 'All');
            $expiryDate = trim($_POST['expiry_date'] ?? '');
            $status = trim($_POST['status'] ?? 'normal');
            $pinned = isset($_POST['pinned']) ? 1 : 0;
            $stmt = $pdo->prepare('INSERT INTO announcements (title, body, created_at) VALUES (:title, :body, NOW())');
            $stmt->execute(['title' => $title, 'body' => $message]);
            $announcementId = (int)$pdo->lastInsertId();
            writeJsonMetadata(dirname(__DIR__) . '/assets/uploads/announcements/_meta/' . $announcementId . '.json', [
                'audience' => $audience,
                'level' => $level,
                'expiry_date' => $expiryDate,
                'status' => $status,
                'pinned' => $pinned,
            ]);
            $success = 'Announcement created.';
        } elseif ($action === 'update_announcement') {
            $announcementId = (int)($_POST['announcement_id'] ?? 0);
            if ($announcementId > 0) {
                $title = trim($_POST['title'] ?? '');
                $message = trim($_POST['message'] ?? '');
                $audience = trim($_POST['audience'] ?? 'Everyone');
                $level = trim($_POST['level'] ?? 'All');
                $expiryDate = trim($_POST['expiry_date'] ?? '');
                $status = trim($_POST['status'] ?? 'normal');
                $pinned = isset($_POST['pinned']) ? 1 : 0;
                $pdo->prepare('UPDATE announcements SET title = :title, body = :body WHERE id = :id')->execute(['title' => $title, 'body' => $message, 'id' => $announcementId]);
                $metaPath = dirname(__DIR__) . '/assets/uploads/announcements/_meta/' . $announcementId . '.json';
                $meta = readJsonMetadata($metaPath);
                $meta['audience'] = $audience;
                $meta['level'] = $level;
                $meta['expiry_date'] = $expiryDate;
                $meta['status'] = $status;
                $meta['pinned'] = $pinned;
                writeJsonMetadata($metaPath, $meta);
                $success = 'Announcement updated.';
            }
        } elseif ($action === 'delete_announcement') {
            $announcementId = (int)($_POST['announcement_id'] ?? 0);
            if ($announcementId > 0) {
                $pdo->prepare('DELETE FROM announcements WHERE id = :id')->execute(['id' => $announcementId]);
                @unlink(dirname(__DIR__) . '/assets/uploads/announcements/_meta/' . $announcementId . '.json');
                $success = 'Announcement deleted.';
            }
        }

        $_SESSION['flash_message'] = $success !== '' ? $success : 'Action completed.';
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
        $_SESSION['flash_message'] = $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
}

$events = $pdo->query('SELECT * FROM events ORDER BY event_date DESC, created_at DESC')->fetchAll();
$resources = $pdo->query('SELECT * FROM resources ORDER BY created_at DESC')->fetchAll();
$executives = $pdo->query('SELECT * FROM executives ORDER BY id ASC')->fetchAll();
$gallery = $pdo->query('SELECT * FROM gallery ORDER BY created_at DESC')->fetchAll();
$announcements = $pdo->query('SELECT * FROM announcements ORDER BY created_at DESC')->fetchAll();

$search = trim($_GET['q'] ?? '');
$sort = trim($_GET['sort'] ?? 'recent');
$eventStatusFilter = trim($_GET['event_status'] ?? '');
$eventDateFilter = trim($_GET['event_date'] ?? '');
$resourceStatusFilter = trim($_GET['resource_status'] ?? '');
$galleryCategoryFilter = trim($_GET['gallery_category'] ?? '');
$announcementStatusFilter = trim($_GET['announcement_status'] ?? '');
$announcementAudienceFilter = trim($_GET['announcement_audience'] ?? '');

$filteredEvents = [];
foreach ($events as $event) {
    $meta = readJsonMetadata(dirname(__DIR__) . '/assets/uploads/events/_meta/' . $event['id'] . '.json');
    $event['meta'] = $meta;
    $event['status'] = eventStatusLabel($event);
    $event['visibility'] = eventVisibilityLabel($event);
    if ($search !== '' && stripos($event['title'], $search) === false && stripos($event['venue'], $search) === false && stripos($event['description'], $search) === false && stripos($event['registration_link'] ?? '', $search) === false) {
        continue;
    }
    if ($eventStatusFilter !== '' && $event['status'] !== $eventStatusFilter) {
        continue;
    }
    if ($eventDateFilter !== '' && ($event['event_date'] ?? '') !== $eventDateFilter) {
        continue;
    }
    $filteredEvents[] = $event;
}
if ($sort === 'oldest') {
    usort($filteredEvents, fn($a, $b) => strtotime($a['event_date'] ?? 'now') <=> strtotime($b['event_date'] ?? 'now'));
} else {
    usort($filteredEvents, fn($a, $b) => strtotime($b['event_date'] ?? 'now') <=> strtotime($a['event_date'] ?? 'now'));
}
$eventsPage = buildPagination($filteredEvents, (int)($_GET['events_page'] ?? 1), 10);

$filteredResources = [];
foreach ($resources as $resource) {
    $meta = resourceMetadata($resource);
    $resource['meta'] = $meta;
    $resource['visibility'] = strtolower((string)($meta['visibility'] ?? 'visible'));
    $resource['download_count'] = (int)($meta['download_count'] ?? 0);
    $resource['resource_type'] = strtolower((string)($meta['resource_type'] ?? $resource['file_type'] ?? 'file'));
    $resource['description'] = $meta['description'] ?? '';
    $resource['academic_session'] = $meta['academic_session'] ?? '';
    if ($search !== '' && stripos($resource['title'], $search) === false && stripos($resource['course'], $search) === false && stripos($resource['programme'], $search) === false) {
        continue;
    }
    if ($resourceStatusFilter !== '' && $resource['visibility'] !== $resourceStatusFilter) {
        continue;
    }
    $filteredResources[] = $resource;
}
$resourcesPage = buildPagination($filteredResources, (int)($_GET['resources_page'] ?? 1), 10);

$filteredExecutives = [];
foreach ($executives as $executive) {
    $meta = executiveMetadata($executive);
    $executive['meta'] = $meta;
    $executive['visibility'] = strtolower((string)($meta['visibility'] ?? 'active'));
    if ($search !== '' && stripos($executive['full_name'], $search) === false && stripos($executive['position'], $search) === false && stripos($executive['department'], $search) === false) {
        continue;
    }
    $filteredExecutives[] = $executive;
}
$executivesPage = buildPagination($filteredExecutives, (int)($_GET['executives_page'] ?? 1), 8);

$filteredGallery = [];
foreach ($gallery as $item) {
    $meta = galleryMetadata($item);
    $item['meta'] = $meta;
    $item['visibility'] = strtolower((string)($meta['visibility'] ?? 'visible'));
    $item['display_order'] = (int)($meta['display_order'] ?? 0);
    if ($search !== '' && stripos($item['title'], $search) === false && stripos($item['description'], $search) === false) {
        continue;
    }
    if ($galleryCategoryFilter !== '' && strtolower((string)$item['category']) !== strtolower($galleryCategoryFilter)) {
        continue;
    }
    $filteredGallery[] = $item;
}
usort($filteredGallery, fn($a, $b) => $a['display_order'] <=> $b['display_order']);
$galleryPage = buildPagination($filteredGallery, (int)($_GET['gallery_page'] ?? 1), 8);

$filteredAnnouncements = [];
foreach ($announcements as $announcement) {
    $meta = announcementMetadata($announcement);
    $announcement['meta'] = $meta;
    $announcement['audience'] = $meta['audience'] ?? 'Everyone';
    $announcement['level'] = $meta['level'] ?? 'All';
    $announcement['expiry_date'] = $meta['expiry_date'] ?? '';
    $announcement['status'] = $meta['status'] ?? 'normal';
    $announcement['pinned'] = (int)($meta['pinned'] ?? 0);
    if ($search !== '' && stripos($announcement['title'], $search) === false && stripos($announcement['body'], $search) === false) {
        continue;
    }
    if ($announcementStatusFilter !== '' && $announcement['status'] !== $announcementStatusFilter) {
        continue;
    }
    if ($announcementAudienceFilter !== '' && $announcement['audience'] !== $announcementAudienceFilter) {
        continue;
    }
    $filteredAnnouncements[] = $announcement;
}
$announcementsPage = buildPagination($filteredAnnouncements, (int)($_GET['announcements_page'] ?? 1), 10);

$stats = [
    'events' => count($events),
    'upcoming' => count(array_filter($events, fn($item) => eventStatusLabel($item) === 'upcoming')),
    'resources' => count($resources),
    'executives' => count($executives),
    'gallery_images' => count(array_filter($gallery, fn($item) => strtolower(pathinfo($item['image_path'] ?? '', PATHINFO_EXTENSION)) !== 'mp4' && strtolower(pathinfo($item['image_path'] ?? '', PATHINFO_EXTENSION)) !== 'webm' && strtolower(pathinfo($item['image_path'] ?? '', PATHINFO_EXTENSION)) !== 'ogg' && strtolower(pathinfo($item['image_path'] ?? '', PATHINFO_EXTENSION)) !== 'mov' && strtolower(pathinfo($item['image_path'] ?? '', PATHINFO_EXTENSION)) !== 'avi' && strtolower(pathinfo($item['image_path'] ?? '', PATHINFO_EXTENSION)) !== 'mkv')),
    'gallery_videos' => count(array_filter($gallery, fn($item) => in_array(strtolower(pathinfo($item['image_path'] ?? '', PATHINFO_EXTENSION)), ['mp4','webm','ogg','mov','avi','mkv'], true))),
    'announcements' => count($announcements),
];
?>
<div class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-3">
      <?php require_once dirname(__DIR__) . '/templates/admin-sidebar.php'; ?>
    </div>
    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <div class="hero-badge"><i data-lucide="layers-3"></i> Content Management System</div>
          <h2 class="fw-bold mt-3 mb-1">Manage Events, Resources, Executives, Gallery and Announcements</h2>
          <p class="text-secondary mb-0">A polished College CMS that stays compatible with the existing NACOS portal tables.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/dashboard.php" class="btn btn-outline-success btn-sm"><i data-lucide="arrow-left"></i> Back to Dashboard</a>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="glass-card p-3"><div class="small text-secondary">Total Events</div><h4 class="fw-bold mb-0"><?= (int)$stats['events'] ?></h4></div></div>
        <div class="col-md-4"><div class="glass-card p-3"><div class="small text-secondary">Upcoming Events</div><h4 class="fw-bold mb-0"><?= (int)$stats['upcoming'] ?></h4></div></div>
        <div class="col-md-4"><div class="glass-card p-3"><div class="small text-secondary">Resources</div><h4 class="fw-bold mb-0"><?= (int)$stats['resources'] ?></h4></div></div>
        <div class="col-md-4"><div class="glass-card p-3"><div class="small text-secondary">Executives</div><h4 class="fw-bold mb-0"><?= (int)$stats['executives'] ?></h4></div></div>
        <div class="col-md-4"><div class="glass-card p-3"><div class="small text-secondary">Gallery Images</div><h4 class="fw-bold mb-0"><?= (int)$stats['gallery_images'] ?></h4></div></div>
        <div class="col-md-4"><div class="glass-card p-3"><div class="small text-secondary">Gallery Videos</div><h4 class="fw-bold mb-0"><?= (int)$stats['gallery_videos'] ?></h4></div></div>
        <div class="col-md-4"><div class="glass-card p-3"><div class="small text-secondary">Announcements</div><h4 class="fw-bold mb-0"><?= (int)$stats['announcements'] ?></h4></div></div>
      </div>

      <?php if ($errors): foreach ($errors as $error): ?><div class="d-none" data-alert-error="<?= e($error) ?>"></div><?php endforeach; endif; ?>
      <?php if (!empty($_SESSION['flash_message'])): ?><div class="d-none" data-flash-message="<?= e($_SESSION['flash_message']) ?>" data-flash-type="<?= e($_SESSION['flash_type'] ?? 'success') ?>"></div><?php endif; ?>
      <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>

      <ul class="nav nav-pills mb-4" id="cmsTabs">
        <li class="nav-item"><a class="nav-link <?= $activeTab === 'events' ? 'active' : '' ?>" href="?tab=events">Events</a></li>
        <li class="nav-item"><a class="nav-link <?= $activeTab === 'resources' ? 'active' : '' ?>" href="?tab=resources">Resources</a></li>
        <li class="nav-item"><a class="nav-link <?= $activeTab === 'executives' ? 'active' : '' ?>" href="?tab=executives">Executives</a></li>
        <li class="nav-item"><a class="nav-link <?= $activeTab === 'gallery' ? 'active' : '' ?>" href="?tab=gallery">Gallery</a></li>
        <li class="nav-item"><a class="nav-link <?= $activeTab === 'announcements' ? 'active' : '' ?>" href="?tab=announcements">Announcements</a></li>
      </ul>

      <?php if ($activeTab === 'events'): ?>
      <div class="glass-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h4 class="fw-bold mb-1">Create Event</h4>
            <p class="text-secondary mb-0">Add poster, timeline, registration info and status.</p>
          </div>
        </div>
        <form method="post" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create_event">
          <div class="col-md-4"><input type="text" name="title" class="form-control" placeholder="Event title" required></div>
          <div class="col-md-4"><input type="text" name="venue" class="form-control" placeholder="Venue"></div>
          <div class="col-md-4"><input type="text" name="event_category" class="form-control" placeholder="Category"></div>
          <div class="col-md-3"><input type="date" name="event_date" class="form-control" required></div>
          <div class="col-md-3"><input type="time" name="event_time" class="form-control"></div>
          <div class="col-md-3"><input type="date" name="registration_deadline" class="form-control"></div>
          <div class="col-md-3"><select name="event_status" class="form-select"><option value="upcoming">Upcoming</option><option value="ongoing">Ongoing</option><option value="completed">Completed</option><option value="archived">Archived</option></select></div>
          <div class="col-md-4"><select name="visibility" class="form-select"><option value="published">Published</option><option value="hidden">Hidden</option></select></div>
          <div class="col-md-8"><input type="text" name="registration_link" class="form-control" placeholder="Registration link"></div>
          <div class="col-12"><textarea name="description" class="form-control" rows="3" placeholder="Description"></textarea></div>
          <div class="col-md-6"><input type="file" name="poster" class="form-control" id="eventPosterInput" accept="image/*"></div>
          <div class="col-md-6"><div class="border rounded p-3 text-center" id="eventPosterPreview">Preview will appear here.</div></div>
          <div class="col-12"><button type="submit" class="btn btn-success">Save Event</button></div>
        </form>
      </div>
      <div class="glass-card p-4">
        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
          <div>
            <h4 class="fw-bold mb-1">Events Table</h4>
            <p class="text-secondary mb-0">Filter, search and manage event records.</p>
          </div>
          <form method="get" class="d-flex flex-wrap gap-2">
            <input type="hidden" name="tab" value="events">
            <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Search events">
            <select name="event_status" class="form-select"><option value="">All Status</option><option value="upcoming" <?= $eventStatusFilter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option><option value="ongoing" <?= $eventStatusFilter === 'ongoing' ? 'selected' : '' ?>>Ongoing</option><option value="completed" <?= $eventStatusFilter === 'completed' ? 'selected' : '' ?>>Completed</option><option value="archived" <?= $eventStatusFilter === 'archived' ? 'selected' : '' ?>>Archived</option></select>
            <input type="date" name="event_date" class="form-control" value="<?= e($eventDateFilter) ?>">
            <button class="btn btn-success">Filter</button>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead><tr><th>Title</th><th>Category</th><th>Date</th><th>Status</th><th>Visibility</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($eventsPage['items'] as $event): ?>
                <tr>
                  <td><div class="fw-semibold"><?= e($event['title']) ?></div><div class="small text-secondary"><?= e($event['venue']) ?></div></td>
                  <td><?= e($event['meta']['category'] ?? 'General') ?></td>
                  <td><?= e($event['event_date']) ?></td>
                  <td><span class="badge-soft"><?= e($event['status']) ?></span></td>
                  <td><span class="badge-soft"><?= e($event['visibility']) ?></span></td>
                  <td>
                    <div class="dropdown">
                      <button class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
                      <ul class="dropdown-menu">
                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#viewEventModal<?= (int)$event['id'] ?>">View</button></li>
                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editEventModal<?= (int)$event['id'] ?>">Edit</button></li>
                        <li><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="mark_event_status"><input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>"><input type="hidden" name="event_status" value="upcoming"><button class="dropdown-item">Mark Upcoming</button></form></li>
                        <li><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="mark_event_status"><input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>"><input type="hidden" name="event_status" value="completed"><button class="dropdown-item">Mark Past</button></form></li>
                        <li><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle_event_visibility"><input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>"><button class="dropdown-item">Publish/Hide</button></form></li>
                        <li><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="mark_event_status"><input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>"><input type="hidden" name="event_status" value="archived"><button class="dropdown-item">Archive</button></form></li>
                        <li><form method="post" class="d-inline" onsubmit="return confirm('Delete this event?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_event"><input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>"><button class="dropdown-item text-danger">Delete</button></form></li>
                      </ul>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($eventsPage['pages'] > 1): ?><nav class="mt-3"><ul class="pagination pagination-sm justify-content-center"><?php for ($page = 1; $page <= $eventsPage['pages']; $page++): ?><li class="page-item <?= $page === $eventsPage['page'] ? 'active' : '' ?>"><a class="page-link" href="?tab=events&q=<?= urlencode($search) ?>&event_status=<?= urlencode($eventStatusFilter) ?>&event_date=<?= urlencode($eventDateFilter) ?>&events_page=<?= $page ?>"><?= $page ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
      </div>
      <?php foreach ($eventsPage['items'] as $event): ?>
        <div class="modal fade" id="viewEventModal<?= (int)$event['id'] ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><?= e($event['title']) ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p><strong>Category:</strong> <?= e($event['meta']['category'] ?? 'General') ?></p><p><strong>Venue:</strong> <?= e($event['venue']) ?></p><p><strong>Date:</strong> <?= e($event['event_date']) ?> <?= e($event['event_time']) ?></p><p><strong>Registration Deadline:</strong> <?= e($event['meta']['registration_deadline'] ?? '') ?></p><p><strong>Status:</strong> <?= e($event['status']) ?></p><p><strong>Visibility:</strong> <?= e($event['visibility']) ?></p><p><?= nl2br(e($event['description'])) ?></p><?php if (!empty($event['poster_path'])): ?><img src="<?= asset($event['poster_path']) ?>" class="img-fluid rounded" alt="Poster"><?php endif; ?></div></div></div></div>
        <div class="modal fade" id="editEventModal<?= (int)$event['id'] ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Event</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_event"><input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>"><div class="row g-3"><div class="col-md-6"><input type="text" class="form-control" name="title" value="<?= e($event['title']) ?>" required></div><div class="col-md-6"><input type="text" class="form-control" name="venue" value="<?= e($event['venue']) ?>"></div><div class="col-md-4"><input type="text" class="form-control" name="event_category" value="<?= e($event['meta']['category'] ?? 'General') ?>"></div><div class="col-md-4"><input type="date" class="form-control" name="event_date" value="<?= e($event['event_date']) ?>" required></div><div class="col-md-4"><input type="time" class="form-control" name="event_time" value="<?= e($event['event_time']) ?>"></div><div class="col-md-4"><input type="date" class="form-control" name="registration_deadline" value="<?= e($event['meta']['registration_deadline'] ?? '') ?>"></div><div class="col-md-4"><select name="event_status" class="form-select"><option value="upcoming" <?= $event['status'] === 'upcoming' ? 'selected' : '' ?>>Upcoming</option><option value="ongoing" <?= $event['status'] === 'ongoing' ? 'selected' : '' ?>>Ongoing</option><option value="completed" <?= $event['status'] === 'completed' ? 'selected' : '' ?>>Completed</option><option value="archived" <?= $event['status'] === 'archived' ? 'selected' : '' ?>>Archived</option></select></div><div class="col-md-4"><select name="visibility" class="form-select"><option value="published" <?= ($event['visibility'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option><option value="hidden" <?= ($event['visibility'] ?? '') === 'hidden' ? 'selected' : '' ?>>Hidden</option></select></div><div class="col-12"><input type="text" class="form-control" name="registration_link" value="<?= e($event['meta']['registration_link'] ?? '') ?>" placeholder="Registration link"></div><div class="col-12"><textarea class="form-control" rows="4" name="description"><?= e($event['description']) ?></textarea></div><div class="col-12"><input type="file" class="form-control" name="poster" accept="image/*"></div><div class="col-12"><button class="btn btn-success">Save Changes</button></div></div></form></div></div></div></div>
      <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($activeTab === 'resources'): ?>
      <div class="glass-card p-4 mb-4">
        <h4 class="fw-bold mb-3">Upload Resource</h4>
        <form method="post" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create_resource">
          <div class="col-md-4"><input type="text" name="title" class="form-control" placeholder="Title" required></div>
          <div class="col-md-4"><input type="text" name="programme" class="form-control" placeholder="Programme"></div>
          <div class="col-md-4"><input type="text" name="course" class="form-control" placeholder="Course"></div>
          <div class="col-md-3"><select name="level" class="form-select"><option value="">Select Level</option><option value="ND I">ND I</option><option value="ND II">ND II</option><option value="HND I">HND I</option><option value="HND II">HND II</option></select></div>
          <div class="col-md-3"><select name="semester" class="form-select"><option value="">Select Semester</option><option value="First Semester">First Semester</option><option value="Second Semester">Second Semester</option></select></div>
          <div class="col-md-3"><input type="text" name="academic_session" class="form-control" placeholder="Academic Session"></div>
          <div class="col-md-3"><select name="visibility" class="form-select"><option value="visible">Visible</option><option value="hidden">Hidden</option><option value="draft">Draft</option></select></div>
          <div class="col-md-6"><input type="text" name="resource_type" class="form-control" placeholder="Type (PDF, DOCX, Video, etc.)"></div>
          <div class="col-md-6"><input type="file" name="file" class="form-control" required></div>
          <div class="col-md-6"><input type="file" name="thumbnail" class="form-control" accept="image/*"></div>
          <div class="col-md-6"><textarea name="description" class="form-control" rows="3" placeholder="Description"></textarea></div>
          <div class="col-12"><button class="btn btn-success">Upload Resource</button></div>
        </form>
      </div>
      <div class="glass-card p-4">
        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
          <div>
            <h4 class="fw-bold mb-1">Resources Table</h4>
            <p class="text-secondary mb-0">Professional table with preview, edit and download actions.</p>
          </div>
          <form method="get" class="d-flex flex-wrap gap-2">
            <input type="hidden" name="tab" value="resources">
            <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Search resources">
            <select name="resource_status" class="form-select"><option value="">All Status</option><option value="visible" <?= $resourceStatusFilter === 'visible' ? 'selected' : '' ?>>Visible</option><option value="hidden" <?= $resourceStatusFilter === 'hidden' ? 'selected' : '' ?>>Hidden</option><option value="draft" <?= $resourceStatusFilter === 'draft' ? 'selected' : '' ?>>Draft</option></select>
            <button class="btn btn-success">Filter</button>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead><tr><th>Title</th><th>Course</th><th>Programme</th><th>Level</th><th>Semester</th><th>Downloads</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($resourcesPage['items'] as $resource): ?>
                <tr>
                  <td><div class="fw-semibold"><?= e($resource['title']) ?></div><div class="small text-secondary"><?= e($resource['description']) ?></div></td>
                  <td><?= e($resource['course']) ?></td>
                  <td><?= e($resource['programme']) ?></td>
                  <td><?= e($resource['level']) ?></td>
                  <td><?= e($resource['semester']) ?></td>
                  <td><?= (int)$resource['download_count'] ?></td>
                  <td><span class="badge-soft"><?= e($resource['visibility']) ?></span></td>
                  <td>
                    <div class="dropdown">
                      <button class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
                      <ul class="dropdown-menu">
                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#viewResourceModal<?= (int)$resource['id'] ?>">Preview</button></li>
                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editResourceModal<?= (int)$resource['id'] ?>">Edit</button></li>
                        <?php if (!empty($resource['file_path'])): ?><li><a class="dropdown-item" href="<?= asset($resource['file_path']) ?>" target="_blank" rel="noopener noreferrer">Download</a></li><?php endif; ?>
                        <li><form method="post" class="d-inline" onsubmit="return confirm('Delete this resource?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_resource"><input type="hidden" name="resource_id" value="<?= (int)$resource['id'] ?>"><button class="dropdown-item text-danger">Delete</button></form></li>
                      </ul>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($resourcesPage['pages'] > 1): ?><nav class="mt-3"><ul class="pagination pagination-sm justify-content-center"><?php for ($page = 1; $page <= $resourcesPage['pages']; $page++): ?><li class="page-item <?= $page === $resourcesPage['page'] ? 'active' : '' ?>"><a class="page-link" href="?tab=resources&q=<?= urlencode($search) ?>&resource_status=<?= urlencode($resourceStatusFilter) ?>&resources_page=<?= $page ?>"><?= $page ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
      </div>
      <?php foreach ($resourcesPage['items'] as $resource): ?>
        <div class="modal fade" id="viewResourceModal<?= (int)$resource['id'] ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><?= e($resource['title']) ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p><strong>Programme:</strong> <?= e($resource['programme']) ?></p><p><strong>Course:</strong> <?= e($resource['course']) ?></p><p><strong>Level:</strong> <?= e($resource['level']) ?></p><p><strong>Semester:</strong> <?= e($resource['semester']) ?></p><p><strong>Academic Session:</strong> <?= e($resource['academic_session']) ?></p><p><strong>Status:</strong> <?= e($resource['visibility']) ?></p><p><?= nl2br(e($resource['description'])) ?></p><?php if (!empty($resource['file_path'])): ?><a class="btn btn-success" href="<?= asset($resource['file_path']) ?>" target="_blank" rel="noopener noreferrer">Open File</a><?php endif; ?></div></div></div></div>
        <div class="modal fade" id="editResourceModal<?= (int)$resource['id'] ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Resource</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_resource"><input type="hidden" name="resource_id" value="<?= (int)$resource['id'] ?>"><div class="row g-3"><div class="col-md-6"><input type="text" class="form-control" name="title" value="<?= e($resource['title']) ?>" required></div><div class="col-md-6"><input type="text" class="form-control" name="programme" value="<?= e($resource['programme']) ?>"></div><div class="col-md-4"><input type="text" class="form-control" name="course" value="<?= e($resource['course']) ?>"></div><div class="col-md-4"><input type="text" class="form-control" name="level" value="<?= e($resource['level']) ?>"></div><div class="col-md-4"><input type="text" class="form-control" name="semester" value="<?= e($resource['semester']) ?>"></div><div class="col-md-4"><input type="text" class="form-control" name="academic_session" value="<?= e($resource['academic_session']) ?>"></div><div class="col-md-4"><select class="form-select" name="visibility"><option value="visible" <?= ($resource['visibility'] ?? '') === 'visible' ? 'selected' : '' ?>>Visible</option><option value="hidden" <?= ($resource['visibility'] ?? '') === 'hidden' ? 'selected' : '' ?>>Hidden</option><option value="draft" <?= ($resource['visibility'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option></select></div><div class="col-md-4"><input type="text" class="form-control" name="resource_type" value="<?= e($resource['resource_type'] ?? $resource['file_type'] ?? 'file') ?>"></div><div class="col-12"><textarea class="form-control" rows="3" name="description"><?= e($resource['description']) ?></textarea></div><div class="col-md-6"><input type="file" class="form-control" name="file"></div><div class="col-md-6"><input type="file" class="form-control" name="thumbnail" accept="image/*"></div><div class="col-12"><button class="btn btn-success">Save Changes</button></div></div></form></div></div></div></div>
      <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($activeTab === 'executives'): ?>
      <div class="glass-card p-4 mb-4">
        <h4 class="fw-bold mb-3">Create Executive</h4>
        <form method="post" class="row g-3 mb-4">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create_executive_tenure">
          <div class="col-md-5"><label for="tenureName" class="form-label">Executive year or tenure</label><input type="text" id="tenureName" name="tenure_name" class="form-control" required></div>
          <div class="col-md-3"><label for="tenureStarts" class="form-label">Starts on</label><input type="date" id="tenureStarts" name="starts_on" class="form-control"></div>
          <div class="col-md-3"><label for="tenureEnds" class="form-label">Ends on</label><input type="date" id="tenureEnds" name="ends_on" class="form-control"></div>
          <div class="col-md-1 d-flex align-items-end"><button class="btn btn-outline-success w-100" title="Create tenure">Add</button></div>
        </form>
        <form method="post" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create_executive">
          <div class="col-md-4"><label for="executiveName" class="form-label">Full name</label><input type="text" id="executiveName" name="full_name" class="form-control" required></div>
          <div class="col-md-4"><label for="executivePosition" class="form-label">Position</label><input type="text" id="executivePosition" name="position" class="form-control" required></div>
          <div class="col-md-4"><label for="executiveTenure" class="form-label">Executive year or tenure</label><select id="executiveTenure" name="tenure_id" class="form-select"><?php foreach ($executiveTenures as $tenure): ?><option value="<?= (int)$tenure['id'] ?>" <?= (int)$tenure['is_current'] === 1 ? 'selected' : '' ?>><?= e($tenure['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-4"><label for="executiveDepartment" class="form-label">Department</label><input type="text" id="executiveDepartment" name="department" class="form-control"></div>
          <div class="col-md-4"><label for="executiveLevel" class="form-label">Academic level</label><input type="text" id="executiveLevel" name="academic_level" class="form-control"></div>
          <div class="col-md-4"><label for="executiveContact" class="form-label">Contact</label><input type="text" id="executiveContact" name="contact" class="form-control"></div>
          <div class="col-md-4"><select name="visibility" class="form-select"><option value="active">Active</option><option value="hidden">Hidden</option></select></div>
          <div class="col-md-6"><input type="file" name="passport" class="form-control" id="executivePassportInput" accept="image/*"></div>
          <div class="col-md-6"><div class="border rounded p-3 text-center" id="executivePassportPreview">Preview will appear here.</div></div>
          <div class="col-12"><label for="executiveBiography" class="form-label">Biography</label><textarea name="biography" id="executiveBiography" class="form-control" rows="3"></textarea></div>
          <div class="col-12"><button class="btn btn-success">Save Executive</button></div>
        </form>
      </div>
      <div class="glass-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div><h4 class="fw-bold mb-1">Executive Cards</h4><p class="text-secondary mb-0">Using the same visual treatment as the public executives page.</p></div>
          <form method="get" class="d-flex gap-2"><input type="hidden" name="tab" value="executives"><label for="executiveSearch" class="visually-hidden">Search executives</label><input type="text" id="executiveSearch" name="q" class="form-control" value="<?= e($search) ?>"><button class="btn btn-success">Search</button></form>
        </div>
        <div class="row g-4">
          <?php foreach ($executivesPage['items'] as $executive): ?>
            <?php $imagePath = null; if (!empty($executive['passport_path'])) { $candidate = ltrim($executive['passport_path'], '/'); $diskPath = __DIR__ . '/../assets/' . $candidate; if (file_exists($diskPath)) { $imagePath = asset($candidate); }} if ($imagePath === null) { $imagePath = asset('images/AI.png'); } ?>
            <div class="col-sm-6 col-lg-4">
              <article class="executive-card glass-card h-100">
                <div class="executive-image-wrap"><img src="<?= e($imagePath) ?>" alt="<?= e($executive['full_name']) ?>" class="executive-photo"></div>
                <div class="executive-content">
                  <div class="position-badge"><i data-lucide="award"></i><span><?= e($executive['position']) ?></span></div>
                  <h3 class="executive-name mb-0"><?= e($executive['full_name']) ?></h3>
                  <p class="text-secondary small mb-2"><?= e($executive['department']) ?> • <?= e($executive['academic_level']) ?></p>
                  <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#editExecutiveModal<?= (int)$executive['id'] ?>">Edit</button>
                    <form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle_executive_visibility"><input type="hidden" name="executive_id" value="<?= (int)$executive['id'] ?>"><button class="btn btn-outline-warning btn-sm">Hide/Activate</button></form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this executive?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_executive"><input type="hidden" name="executive_id" value="<?= (int)$executive['id'] ?>"><button class="btn btn-outline-danger btn-sm">Delete</button></form>
                  </div>
                </div>
              </article>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($executivesPage['pages'] > 1): ?><nav class="mt-3"><ul class="pagination pagination-sm justify-content-center"><?php for ($page = 1; $page <= $executivesPage['pages']; $page++): ?><li class="page-item <?= $page === $executivesPage['page'] ? 'active' : '' ?>"><a class="page-link" href="?tab=executives&q=<?= urlencode($search) ?>&executives_page=<?= $page ?>"><?= $page ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
      </div>
      <?php foreach ($executivesPage['items'] as $executive): ?>
        <div class="modal fade" id="editExecutiveModal<?= (int)$executive['id'] ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Executive</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_executive"><input type="hidden" name="executive_id" value="<?= (int)$executive['id'] ?>"><div class="row g-3"><div class="col-md-6"><label class="form-label">Full name</label><input type="text" class="form-control" name="full_name" value="<?= e($executive['full_name']) ?>" required></div><div class="col-md-6"><label class="form-label">Position</label><input type="text" class="form-control" name="position" value="<?= e($executive['position']) ?>" required></div><div class="col-md-4"><label class="form-label">Executive year or tenure</label><select class="form-select" name="tenure_id"><?php foreach ($executiveTenures as $tenure): ?><option value="<?= (int)$tenure['id'] ?>" <?= (int)($executive['tenure_id'] ?? 0) === (int)$tenure['id'] ? 'selected' : '' ?>><?= e($tenure['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Department</label><input type="text" class="form-control" name="department" value="<?= e($executive['department']) ?>"></div><div class="col-md-4"><label class="form-label">Academic level</label><input type="text" class="form-control" name="academic_level" value="<?= e($executive['academic_level']) ?>"></div><div class="col-md-4"><label class="form-label">Visibility</label><select class="form-select" name="visibility"><option value="active" <?= ($executive['visibility'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option><option value="hidden" <?= ($executive['visibility'] ?? '') === 'hidden' ? 'selected' : '' ?>>Hidden</option></select></div><div class="col-md-4"><label class="form-label">Contact</label><input type="text" class="form-control" name="contact" value="<?= e($executive['contact']) ?>"></div><div class="col-md-4"><label class="form-label">Passport photo</label><input type="file" class="form-control" name="passport" accept="image/*"></div><div class="col-12"><label class="form-label">Biography</label><textarea class="form-control" rows="3" name="biography"><?= e($executive['biography']) ?></textarea></div><div class="col-12"><button class="btn btn-success">Save Changes</button></div></div></form></div></div></div></div>
      <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($activeTab === 'gallery'): ?>
      <div class="glass-card p-4 mb-4">
        <h4 class="fw-bold mb-3">Upload Gallery Media</h4>
        <form method="post" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create_gallery">
          <div class="col-md-4"><input type="text" name="title" class="form-control" placeholder="Title"></div>
          <div class="col-md-4"><select name="category" class="form-select"><option value="Convocation">Convocation</option><option value="Matriculation">Matriculation</option><option value="Workshop">Workshop</option><option value="Seminar">Seminar</option><option value="Competition">Competition</option><option value="Conference">Conference</option><option value="Department">Department</option><option value="Others">Others</option></select></div>
          <div class="col-md-4"><select name="visibility" class="form-select"><option value="visible">Visible</option><option value="hidden">Hidden</option></select></div>
          <div class="col-md-6"><input type="file" name="gallery_files[]" class="form-control" multiple required></div>
          <div class="col-md-6"><input type="number" name="display_order" class="form-control" placeholder="Display order"></div>
          <div class="col-12"><textarea name="description" class="form-control" rows="3" placeholder="Description"></textarea></div>
          <div class="col-12"><button class="btn btn-success">Upload Media</button></div>
        </form>
      </div>
      <div class="glass-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div><h4 class="fw-bold mb-1">Gallery Manager</h4><p class="text-secondary mb-0">Preview, edit and delete images or videos.</p></div>
          <form method="get" class="d-flex gap-2"><input type="hidden" name="tab" value="gallery"><input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Search gallery"><select name="gallery_category" class="form-select"><option value="">All Categories</option><option value="Convocation" <?= $galleryCategoryFilter === 'Convocation' ? 'selected' : '' ?>>Convocation</option><option value="Matriculation" <?= $galleryCategoryFilter === 'Matriculation' ? 'selected' : '' ?>>Matriculation</option><option value="Workshop" <?= $galleryCategoryFilter === 'Workshop' ? 'selected' : '' ?>>Workshop</option><option value="Seminar" <?= $galleryCategoryFilter === 'Seminar' ? 'selected' : '' ?>>Seminar</option><option value="Competition" <?= $galleryCategoryFilter === 'Competition' ? 'selected' : '' ?>>Competition</option><option value="Conference" <?= $galleryCategoryFilter === 'Conference' ? 'selected' : '' ?>>Conference</option><option value="Department" <?= $galleryCategoryFilter === 'Department' ? 'selected' : '' ?>>Department</option><option value="Others" <?= $galleryCategoryFilter === 'Others' ? 'selected' : '' ?>>Others</option></select><button class="btn btn-success">Filter</button></form>
        </div>
        <div class="row g-4">
          <?php foreach ($galleryPage['items'] as $item): ?>
            <div class="col-md-6 col-xl-4">
              <div class="glass-card p-3 h-100">
                <?php $ext = strtolower(pathinfo($item['image_path'] ?? '', PATHINFO_EXTENSION)); $isVideo = in_array($ext, ['mp4','webm','ogg','mov','avi','mkv'], true); ?>
                <?php if ($isVideo): ?><video class="img-fluid rounded mb-3 w-100" controls><source src="<?= asset($item['image_path']) ?>" type="video/<?= $ext ?>"></video><?php else: ?><img src="<?= asset($item['image_path']) ?>" class="img-fluid rounded mb-3" alt="<?= e($item['title']) ?>"><?php endif; ?>
                <div class="fw-semibold"><?= e($item['title']) ?></div>
                <div class="small text-secondary mb-2"><?= e($item['category']) ?> • <?= e($item['visibility']) ?></div>
                <div class="small text-secondary mb-3"><?= e($item['description']) ?></div>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#editGalleryModal<?= (int)$item['id'] ?>">Edit</button>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this gallery item?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_gallery"><input type="hidden" name="gallery_id" value="<?= (int)$item['id'] ?>"><button class="btn btn-outline-danger btn-sm">Delete</button></form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($galleryPage['pages'] > 1): ?><nav class="mt-3"><ul class="pagination pagination-sm justify-content-center"><?php for ($page = 1; $page <= $galleryPage['pages']; $page++): ?><li class="page-item <?= $page === $galleryPage['page'] ? 'active' : '' ?>"><a class="page-link" href="?tab=gallery&q=<?= urlencode($search) ?>&gallery_category=<?= urlencode($galleryCategoryFilter) ?>&gallery_page=<?= $page ?>"><?= $page ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
      </div>
      <?php foreach ($galleryPage['items'] as $item): ?>
        <div class="modal fade" id="editGalleryModal<?= (int)$item['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Gallery Item</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_gallery"><input type="hidden" name="gallery_id" value="<?= (int)$item['id'] ?>"><div class="mb-3"><input type="text" class="form-control" name="title" value="<?= e($item['title']) ?>"></div><div class="mb-3"><textarea class="form-control" rows="3" name="description"><?= e($item['description']) ?></textarea></div><div class="mb-3"><select class="form-select" name="category"><option value="Convocation" <?= ($item['category'] ?? '') === 'Convocation' ? 'selected' : '' ?>>Convocation</option><option value="Matriculation" <?= ($item['category'] ?? '') === 'Matriculation' ? 'selected' : '' ?>>Matriculation</option><option value="Workshop" <?= ($item['category'] ?? '') === 'Workshop' ? 'selected' : '' ?>>Workshop</option><option value="Seminar" <?= ($item['category'] ?? '') === 'Seminar' ? 'selected' : '' ?>>Seminar</option><option value="Competition" <?= ($item['category'] ?? '') === 'Competition' ? 'selected' : '' ?>>Competition</option><option value="Conference" <?= ($item['category'] ?? '') === 'Conference' ? 'selected' : '' ?>>Conference</option><option value="Department" <?= ($item['category'] ?? '') === 'Department' ? 'selected' : '' ?>>Department</option><option value="Others" <?= ($item['category'] ?? '') === 'Others' ? 'selected' : '' ?>>Others</option></select></div><div class="mb-3"><select class="form-select" name="visibility"><option value="visible" <?= ($item['visibility'] ?? '') === 'visible' ? 'selected' : '' ?>>Visible</option><option value="hidden" <?= ($item['visibility'] ?? '') === 'hidden' ? 'selected' : '' ?>>Hidden</option></select></div><div class="mb-3"><input type="number" class="form-control" name="display_order" value="<?= (int)$item['display_order'] ?>"></div><button class="btn btn-success">Save Changes</button></form></div></div></div></div>
      <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($activeTab === 'announcements'): ?>
      <div class="glass-card p-4 mb-4">
        <h4 class="fw-bold mb-3">Create Announcement</h4>
        <form method="post" class="row g-3">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create_announcement">
          <div class="col-md-6"><input type="text" name="title" class="form-control" placeholder="Title" required></div>
          <div class="col-md-3"><select name="audience" class="form-select"><option>Everyone</option><option>Students</option><option>Executives</option><option>Admins</option></select></div>
          <div class="col-md-3"><select name="level" class="form-select"><option>All</option><option>100</option><option>200</option><option>300</option><option>400</option></select></div>
          <div class="col-md-3"><input type="date" name="expiry_date" class="form-control"></div>
          <div class="col-md-3"><select name="status" class="form-select"><option value="normal">Normal</option><option value="pinned">Pinned</option><option value="hidden">Hidden</option></select></div>
          <div class="col-md-3"><label class="form-check-label"><input type="checkbox" name="pinned" value="1" class="form-check-input"> Pin Announcement</label></div>
          <div class="col-12"><textarea name="message" class="form-control" rows="4" placeholder="Message"></textarea></div>
          <div class="col-12"><button class="btn btn-success">Create Announcement</button></div>
        </form>
      </div>
      <div class="glass-card p-4">
        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
          <div><h4 class="fw-bold mb-1">Announcements Table</h4><p class="text-secondary mb-0">Search and manage notices.</p></div>
          <form method="get" class="d-flex flex-wrap gap-2"><input type="hidden" name="tab" value="announcements"><input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="Search announcements"><select name="announcement_status" class="form-select"><option value="">All Status</option><option value="normal" <?= $announcementStatusFilter === 'normal' ? 'selected' : '' ?>>Normal</option><option value="pinned" <?= $announcementStatusFilter === 'pinned' ? 'selected' : '' ?>>Pinned</option><option value="hidden" <?= $announcementStatusFilter === 'hidden' ? 'selected' : '' ?>>Hidden</option></select><select name="announcement_audience" class="form-select"><option value="">All Audience</option><option value="Everyone" <?= $announcementAudienceFilter === 'Everyone' ? 'selected' : '' ?>>Everyone</option><option value="Students" <?= $announcementAudienceFilter === 'Students' ? 'selected' : '' ?>>Students</option><option value="Executives" <?= $announcementAudienceFilter === 'Executives' ? 'selected' : '' ?>>Executives</option><option value="Admins" <?= $announcementAudienceFilter === 'Admins' ? 'selected' : '' ?>>Admins</option></select><button class="btn btn-success">Filter</button></form>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead><tr><th>Title</th><th>Audience</th><th>Level</th><th>Status</th><th>Expiry</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($announcementsPage['items'] as $announcement): ?>
                <tr>
                  <td><div class="fw-semibold"><?= e($announcement['title']) ?></div><div class="small text-secondary"><?= e($announcement['body']) ?></div></td>
                  <td><?= e($announcement['audience']) ?></td>
                  <td><?= e($announcement['level']) ?></td>
                  <td><span class="badge-soft"><?= e($announcement['status']) ?></span></td>
                  <td><?= e($announcement['expiry_date']) ?></td>
                  <td>
                    <div class="dropdown">
                      <button class="btn btn-outline-success btn-sm dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
                      <ul class="dropdown-menu">
                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editAnnouncementModal<?= (int)$announcement['id'] ?>">Edit</button></li>
                        <li><form method="post" class="d-inline" onsubmit="return confirm('Delete this announcement?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_announcement"><input type="hidden" name="announcement_id" value="<?= (int)$announcement['id'] ?>"><button class="dropdown-item text-danger">Delete</button></form></li>
                      </ul>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($announcementsPage['pages'] > 1): ?><nav class="mt-3"><ul class="pagination pagination-sm justify-content-center"><?php for ($page = 1; $page <= $announcementsPage['pages']; $page++): ?><li class="page-item <?= $page === $announcementsPage['page'] ? 'active' : '' ?>"><a class="page-link" href="?tab=announcements&q=<?= urlencode($search) ?>&announcement_status=<?= urlencode($announcementStatusFilter) ?>&announcement_audience=<?= urlencode($announcementAudienceFilter) ?>&announcements_page=<?= $page ?>"><?= $page ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
      </div>
      <?php foreach ($announcementsPage['items'] as $announcement): ?>
        <div class="modal fade" id="editAnnouncementModal<?= (int)$announcement['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Announcement</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_announcement"><input type="hidden" name="announcement_id" value="<?= (int)$announcement['id'] ?>"><div class="mb-3"><input type="text" class="form-control" name="title" value="<?= e($announcement['title']) ?>" required></div><div class="mb-3"><select class="form-select" name="audience"><option value="Everyone" <?= ($announcement['audience'] ?? '') === 'Everyone' ? 'selected' : '' ?>>Everyone</option><option value="Students" <?= ($announcement['audience'] ?? '') === 'Students' ? 'selected' : '' ?>>Students</option><option value="Executives" <?= ($announcement['audience'] ?? '') === 'Executives' ? 'selected' : '' ?>>Executives</option><option value="Admins" <?= ($announcement['audience'] ?? '') === 'Admins' ? 'selected' : '' ?>>Admins</option></select></div><div class="mb-3"><select class="form-select" name="level"><option value="All" <?= ($announcement['level'] ?? '') === 'All' ? 'selected' : '' ?>>All</option><option value="100" <?= ($announcement['level'] ?? '') === '100' ? 'selected' : '' ?>>100</option><option value="200" <?= ($announcement['level'] ?? '') === '200' ? 'selected' : '' ?>>200</option><option value="300" <?= ($announcement['level'] ?? '') === '300' ? 'selected' : '' ?>>300</option><option value="400" <?= ($announcement['level'] ?? '') === '400' ? 'selected' : '' ?>>400</option></select></div><div class="mb-3"><input type="date" class="form-control" name="expiry_date" value="<?= e($announcement['expiry_date']) ?>"></div><div class="mb-3"><select class="form-select" name="status"><option value="normal" <?= ($announcement['status'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option><option value="pinned" <?= ($announcement['status'] ?? '') === 'pinned' ? 'selected' : '' ?>>Pinned</option><option value="hidden" <?= ($announcement['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>Hidden</option></select></div><div class="mb-3"><label class="form-check-label"><input type="checkbox" name="pinned" value="1" class="form-check-input" <?= !empty($announcement['pinned']) ? 'checked' : '' ?>> Pin Announcement</label></div><div class="mb-3"><textarea class="form-control" rows="4" name="message"><?= e($announcement['body']) ?></textarea></div><button class="btn btn-success">Save Changes</button></form></div></div></div></div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const flashMessage = document.querySelector('[data-flash-message]');
    const errorMessage = document.querySelector('[data-alert-error]');
    const message = flashMessage ? flashMessage.getAttribute('data-flash-message') : (errorMessage ? errorMessage.getAttribute('data-alert-error') : '');
    const type = flashMessage ? (flashMessage.getAttribute('data-flash-type') === 'error' ? 'error' : 'success') : 'success';
    if (message && window.Swal) {
      Swal.fire({
        title: type === 'error' ? 'Action failed' : 'Success',
        text: message,
        icon: type,
        confirmButtonColor: '#14532d'
      });
    }

    const tabs = document.querySelectorAll('#cmsTabs .nav-link');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        localStorage.setItem('adminContentTab', this.getAttribute('href').split('tab=')[1]);
      });
    });
    const saved = localStorage.getItem('adminContentTab');
    if (saved) {
      const target = document.querySelector('#cmsTabs .nav-link[href*="tab=' + saved + '"]');
      if (target) {
        // Only navigate if the current URL does not already include the saved tab.
        // This prevents an automatic click from triggering a reload loop.
        if (!window.location.search.includes('tab=' + saved)) {
          target.click();
        }
      }
    }

    document.querySelectorAll('form').forEach(function (form) {
      const hasFileInput = Array.from(form.querySelectorAll('input[type="file"]')).length > 0;
      if (!hasFileInput) return;
      form.addEventListener('submit', function () {
        const progressWrap = document.createElement('div');
        progressWrap.className = 'upload-progress mb-3';
        progressWrap.innerHTML = '<div class="progress" style="height: 8px;"><div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div></div><div class="small text-secondary mt-2">Preparing upload…</div>';
        if (!form.querySelector('.upload-progress')) {
          form.insertBefore(progressWrap, form.firstChild);
        }
        const bar = progressWrap.querySelector('.progress-bar');
        const label = progressWrap.querySelector('.small');
        let width = 0;
        const interval = setInterval(function () {
          width += Math.floor(Math.random() * 15) + 8;
          if (width > 100) width = 100;
          if (bar) bar.style.width = width + '%';
          if (label) label.textContent = width < 100 ? 'Uploading files… ' + width + '%' : 'Upload complete, processing request…';
          if (width >= 100) {
            clearInterval(interval);
          }
        }, 120);
      });
    });

    function previewFile(inputId, previewId) {
      const input = document.getElementById(inputId);
      const preview = document.getElementById(previewId);
      if (!input || !preview) return;
      input.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) {
          preview.innerHTML = 'Preview will appear here.';
          return;
        }
        if (file.type.startsWith('image/')) {
          const reader = new FileReader();
          reader.onload = function (e) {
            preview.innerHTML = '<img src="' + e.target.result + '" class="img-fluid rounded" alt="Preview">';
          };
          reader.readAsDataURL(file);
        } else {
          preview.innerHTML = '<div class="text-secondary">Selected file: ' + file.name + '</div>';
        }
      });
    }
    previewFile('eventPosterInput', 'eventPosterPreview');
    previewFile('executivePassportInput', 'executivePassportPreview');
  });
</script>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>

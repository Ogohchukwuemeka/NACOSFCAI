<?php require_once dirname(__DIR__) . '/templates/header.php'; ?>
<?php
$auth = new Auth(Database::getInstance());
$auth->requireLogin('student');
$user = $auth->currentUser();
$portal = new Portal(Database::getInstance());
$level = (string)($user['level'] ?? '');
$semester = $_GET['semester'] ?? $user['semester'] ?? 'First Semester';
if (strtolower($semester) === 'first') {
    $semester = 'First Semester';
} elseif (strtolower($semester) === 'second') {
    $semester = 'Second Semester';
}
$resources = $portal->listResourcesByLevel($level, $semester);

$levelOptions = ['ND I', 'ND II', 'HND I', 'HND II'];
$semesterOptions = ['First Semester', 'Second Semester'];
?>
<div class="student-page-shell container py-5">
    <div class="row g-4">
        <div class="col-lg-3">
            <?php require_once dirname(__DIR__) . '/templates/student-sidebar.php'; ?>
        </div>
        <div class="col-lg-9">
            <div class="resources-page">
                <section class="resources-hero mb-4">
                    <div class="container py-3">
                        <a href="<?= APP_URL ?>/student/dashboard.php" class="btn btn-outline-success btn-sm"><i data-lucide="arrow-left"></i> Back to Dashboard</a>
                    </div>
                    <div class="container py-4">
                        <div class="row align-items-center g-4">
                            <div class="col-lg-7" data-aos="fade-up">
                                <div class="hero-badge"><i data-lucide="book-open"></i> Digital Learning Library</div>
                                <h1 class="display-5 fw-bold mt-3 mb-3">Resources for Your Academic Journey</h1>
                                <p class="lead mb-4">Browse lecture notes, study materials, and departmental resources organized by level and semester.</p>
                            </div>
                            <div class="col-lg-5" data-aos="zoom-in" data-aos-delay="100">
                                <div class="resources-hero-panel glass-card p-4">
                                    <div class="hero-ring"></div>
                                    <div class="hero-ring hero-ring-alt"></div>
                                    <div class="hero-visual-copy">
                                        <div class="eyebrow">Study Smart</div>
                                        <h3 class="mt-2 mb-3">Access premium academic materials in a polished learning hub.</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="container py-4">
                    <div class="row g-4">
                        <div class="col-lg-4">
                            <div class="glass-card p-4" data-aos="fade-up">
                                <h4 class="fw-bold mb-3">Browse Resources</h4>
                                <form method="get" class="resource-filter-form">
                                    <div class="mb-3">
                                        <label class="form-label">Level</label>
                                            <select name="level" class="form-select" disabled aria-describedby="resourceLevelHelp">
                                            <?php foreach ($levelOptions as $option): ?>
                                                <option value="<?= e($option) ?>" <?= $level === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div id="resourceLevelHelp" class="form-text">Materials are restricted to your registered level.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Semester</label>
                                        <select name="semester" class="form-select">
                                            <?php foreach ($semesterOptions as $option): ?>
                                                <option value="<?= e($option) ?>" <?= $semester === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button class="btn btn-success w-100">Filter Resources</button>
                                </form>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="glass-card p-4" data-aos="fade-up" data-aos-delay="100">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h4 class="fw-bold mb-1">Resources for <?= e($level) ?> • <?= e($semester) ?></h4>
                                        <p class="text-secondary mb-0">Curated learning materials for your course level.</p>
                                    </div>
                                    <span class="badge-soft"><?= count($resources) ?> items</span>
                                </div>
                                <div class="resource-list">
                                    <?php foreach ($resources as $resource):
                                        $filePath = $resource['file_path'] ?? '';
                                        $fullUrl = APP_URL . '/student/resource-download.php?id=' . (int)$resource['id'];
                                        $downloadUrl = $fullUrl . '&download=1';
                                        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                                    ?>
                                        <div class="resource-card-item d-flex align-items-center gap-3">
                                            <div class="resource-card-icon"><i data-lucide="file-text"></i></div>
                                            <div class="resource-card-main flex-grow-1">
                                                <h6 class="fw-bold mb-1"><?= e($resource['title']) ?></h6>
                                                <p class="text-secondary small mb-2"><?= e($resource['course'] ?? '') ?></p>
                                                <div class="resource-card-meta">
                                                    <span class="badge-soft"><?= e($resource['programme'] ?? 'General') ?></span>
                                                    <span class="badge-soft badge-soft-alt"><?= e($resource['semester'] ?? $semester) ?></span>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-outline-primary btn-sm preview-resource-btn" data-file="<?= e($fullUrl) ?>" data-ext="<?= e($ext) ?>" data-title="<?= e($resource['title']) ?>">Preview</button>
                                                <a class="btn btn-success btn-sm" href="<?= e($downloadUrl) ?>">Download</a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/templates/footer.php'; ?>

<!-- Resource preview modal -->
<div class="modal fade" id="resourcePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Resource Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="min-height:60vh;">
                <div id="resourcePreviewContainer" style="width:100%;height:70vh;display:flex;align-items:center;justify-content:center;padding:12px;box-sizing:border-box;">
                    <!-- preview content injected here -->
                </div>
            </div>
            <div class="modal-footer">
                <a id="resourceDownloadLink" class="btn btn-success" href="#" download>Download</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('click', function (e) {
        const btn = e.target.closest('.preview-resource-btn');
        if (!btn) return;
        const url = btn.getAttribute('data-file');
        const ext = (btn.getAttribute('data-ext') || '').toLowerCase();
        const title = btn.getAttribute('data-title') || 'Resource Preview';
        const container = document.getElementById('resourcePreviewContainer');
        const downloadLink = document.getElementById('resourceDownloadLink');
        container.innerHTML = '';
        downloadLink.href = url;
        downloadLink.setAttribute('download', '');
        const modalEl = document.getElementById('resourcePreviewModal');
        modalEl.querySelector('.modal-title').textContent = title;

        const imageExts = ['jpg','jpeg','png','webp','gif','bmp','svg'];
        const videoExts = ['mp4','webm','ogg','mov','avi','mkv'];

        if (ext === 'pdf') {
                const iframe = document.createElement('iframe');
                iframe.src = url;
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = '0';
                container.appendChild(iframe);
        } else if (imageExts.indexOf(ext) !== -1) {
                const img = document.createElement('img');
                img.src = url;
                img.style.maxWidth = '100%';
                img.style.maxHeight = '100%';
                img.alt = title;
                container.appendChild(img);
        } else if (videoExts.indexOf(ext) !== -1) {
                const video = document.createElement('video');
                video.src = url;
                video.controls = true;
                video.style.maxWidth = '100%';
                video.style.maxHeight = '100%';
                container.appendChild(video);
        } else {
                const p = document.createElement('div');
                p.className = 'p-4';
                p.innerHTML = '<p class="mb-2">Preview not available for this file type.</p>'; 
                const link = document.createElement('a');
                link.className = 'btn btn-success';
                link.href = url;
                link.textContent = 'Download File';
                link.setAttribute('download','');
                p.appendChild(link);
                container.appendChild(p);
        }

        const modal = new bootstrap.Modal(modalEl);
        modal.show();
});
</script>


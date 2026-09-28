<?php

function asset(string $path): string
{
    return APP_URL . '/assets/' . ltrim($path, '/');
}

function resolveAssetUrl(string $path): string
{
    if (empty($path)) {
        return '';
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    if (str_starts_with($path, '/')) {
        return APP_URL . $path;
    }
    if (str_starts_with($path, 'assets/')) {
        return APP_URL . '/' . ltrim($path, '/');
    }
    return APP_URL . '/assets/' . ltrim($path, '/');
}

function imageDataUri(string $path, string $fallbackPath = ''): string
{
    $candidatePaths = [];
    if ($path !== '') {
        $candidatePaths[] = $path;
    }
    if ($fallbackPath !== '' && $fallbackPath !== $path) {
        $candidatePaths[] = $fallbackPath;
    }

    foreach ($candidatePaths as $candidatePath) {
        if ($candidatePath === '') {
            continue;
        }

        if (str_starts_with($candidatePath, 'http://') || str_starts_with($candidatePath, 'https://')) {
            return $candidatePath;
        }

        $localPath = APP_ROOT . '/' . ltrim($candidatePath, '/');
        if (is_file($localPath) && is_readable($localPath)) {
            $mime = function_exists('mime_content_type') ? mime_content_type($localPath) : 'image/png';
            if ($mime === false || trim($mime) === '') {
                $mime = 'image/png';
            }
            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($localPath));
        }
    }

    return '';
}

function resolveImageSource(string $path, string $fallbackPath = ''): string
{
    if (empty($path)) {
        return imageDataUri($fallbackPath);
    }
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    $localPath = APP_ROOT . '/' . ltrim($path, '/');
    if (file_exists($localPath) && is_readable($localPath)) {
        return imageDataUri($path, $fallbackPath);
    }
    if (str_starts_with($path, 'assets/')) {
        return APP_URL . '/' . ltrim($path, '/');
    }
    return asset($path);
}

function buildQrCodeUrl(string $payload, int $size = 320): string
{
    return generateQrCodeDataUri($payload, $size);
}

function fetchRemoteImageDataUri(string $url): string
{
    return $url;
}

function redirect(string $path): void
{
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

function e(string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function detectMediaType(string $path): string
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($extension, ['mp4', 'webm', 'ogg', 'mov'], true) ? 'video' : 'image';
}

function getGalleryCategory(string $title): string
{
    $value = strtolower($title);
    if (str_contains($value, 'video') || str_contains($value, 'clip')) {
        return 'Videos';
    }
    if (str_contains($value, 'matric') || str_contains($value, 'orientation')) {
        return 'Matriculation';
    }
    if (str_contains($value, 'workshop') || str_contains($value, 'seminar')) {
        return 'Workshops';
    }
    if (str_contains($value, 'tech') || str_contains($value, 'coding') || str_contains($value, 'project')) {
        return 'Tech Events';
    }
    return 'All';
}

function isActive(string $page): bool
{
    return basename($_SERVER['PHP_SELF']) === $page;
}

function uploadFile(array $file, string $targetDir, array $allowedExtensions = [], int $maxSize = 5 * 1024 * 1024): array
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No valid file uploaded.'];
    }

    $allowedImages = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowedDocs = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'txt'];
    $allowedVideos = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = @mime_content_type($file['tmp_name']) ?: '';

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File exceeds 5MB limit.'];
    }

    $allowed = $allowedExtensions;
    if (empty($allowed)) {
        $allowed = array_merge($allowedImages, $allowedDocs, $allowedVideos);
    }

    if (!in_array($extension, $allowed, true)) {
        return ['success' => false, 'message' => 'Unsupported file type.'];
    }

    $isImage = in_array($extension, $allowedImages, true) && str_contains($mime, 'image/');
    $isDocument = in_array($extension, $allowedDocs, true) && (str_contains($mime, 'application/') || str_contains($mime, 'text/') || str_contains($mime, 'octet-stream'));
    $isVideo = in_array($extension, $allowedVideos, true) && str_contains($mime, 'video/');

    if (!$isImage && !$isDocument && !$isVideo) {
        return ['success' => false, 'message' => 'Unsupported file type.'];
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $filename = uniqid('file_', true) . '.' . $extension;
    $destination = rtrim($targetDir, '/') . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Could not save uploaded file.'];
    }

    return ['success' => true, 'path' => $destination, 'filename' => $filename];
}

function generateMembershipNumber(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE membership_number IS NOT NULL');
    $stmt->execute();
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf('NACOS/FCAI/%s/%04d', $year, $count);
}

function formatCurrency(float $value): string
{
    return '₦' . number_format($value, 2);
}

function generateReceiptNumber(PDO $pdo): string
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM receipts');
    $stmt->execute();
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf('RCP-%s-%06d', date('Y'), $count);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submittedToken = '';
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
    } elseif (isset($_POST['csrf_token'])) {
        $submittedToken = $_POST['csrf_token'];
    }

    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)$submittedToken)) {
        throw new Exception('Invalid CSRF token.');
    }
}

function generateQrCodeDataUri(string $payload, int $size = 300): string
{
    if (!class_exists('Endroid\\QrCode\\QrCode')) {
        throw new RuntimeException('Endroid QR Code is not available.');
    }

    try {
        $qrCode = new Endroid\QrCode\QrCode(
            data: $payload,
            size: max(120, $size),
            margin: 8,
            errorCorrectionLevel: Endroid\QrCode\ErrorCorrectionLevel::High
        );

        $writer = new Endroid\QrCode\Writer\PngWriter();
        $result = $writer->write($qrCode);
        $pngData = $result->getString();

        if ($pngData === '' || $pngData === false) {
            throw new RuntimeException('QR code generation returned empty output.');
        }

        return 'data:image/png;base64,' . base64_encode($pngData);
    } catch (Throwable $e) {
        throw new RuntimeException('Unable to generate local QR code: ' . $e->getMessage(), 0, $e);
    }
}

function generateMembershipCertificateHtml(array $student, array $payment = [], bool $preview = false): string
{
    $fullName = e($student['full_name'] ?? 'Student Name');
    // Prefer the system-generated membership number from the verified payment if provided.
    $membershipNumberRaw = '';
    if (!empty($payment['membership_number'])) {
        $membershipNumberRaw = $payment['membership_number'];
    } elseif (!empty($student['registration_number'])) {
        $membershipNumberRaw = $student['registration_number'];
    } elseif (!empty($student['membership_number'])) {
        $membershipNumberRaw = $student['membership_number'];
    } elseif (!empty($student['matric_number'])) {
        $membershipNumberRaw = $student['matric_number'];
    }
    $membershipNumber = e($membershipNumberRaw ?: 'Not assigned');
    $course = e($student['course'] ?? $student['course_of_study'] ?? $student['department'] ?? 'N/A');
    // Use the actual payment verification date when available, otherwise fall back to the payment timestamp.
    $issuedAtRaw = trim((string)($payment['verified_at'] ?? $payment['updated_at'] ?? $payment['created_at'] ?? ''));
    if ($issuedAtRaw !== '') {
        $issuedTs = @strtotime($issuedAtRaw);
        if ($issuedTs === false || $issuedTs === -1) {
            $issuedTs = time();
        }
    } else {
        $issuedTs = time();
    }
    $issuedAt = e(date('d M Y', $issuedTs));
    $validUntil = e(date('d M Y', strtotime('+2 years', $issuedTs)));
    $certificateNumber = $membershipNumber;

    $certConfig = [];
    $certConfigPath = APP_ROOT . '/certificate/config.php';
    if (file_exists($certConfigPath)) {
        $certConfig = include $certConfigPath;
    }

    $logoNacos = imageDataUri($certConfig['nacos_logo'] ?? 'certificate/nacos-logo.png');
    $logoFcai = imageDataUri($certConfig['fcai_logo'] ?? 'certificate/fcai-logo.png');
    $signature = imageDataUri($certConfig['signature'] ?? 'certificate/signature.png');
    $seal = imageDataUri($certConfig['seal'] ?? 'certificate/seal.png');
    $presidentName = e($certConfig['president_name'] ?? 'Comr. OGOH JOSHUA CHUKWUEMEKA');
    $presidentTitle = e($certConfig['president_title'] ?? 'President, NACOS FCAI Chapter');
    $qrPayload = "Membership Number: {$membershipNumber}\nName: {$fullName}\nCourse: {$course}";
    $qrCode = generateQrCodeDataUri($qrPayload, 260);

    $previewCss = '';
    if ($preview) {
        $previewCss = "
@media screen {
    body { background: #eef7ed; }
    .page { width: 100%; height: auto; padding: 24px; }
    .cert-wrap { min-height: auto; height: auto; padding: 24px; box-shadow: 0 20px 50px rgba(15,23,42,0.12); }
    .outer-border { display: none; }
    .watermark { font-size: 110px; }
    .header { gap: 16px; }
    .logo-stack img { width: 100px; }
    .heading h2 { font-size: 42px; }
    .name { font-size: 36px; }
    .statement { max-width: 100%; }
    .info-row { flex-wrap: wrap; justify-content: center; }
    .info { width: 180px; }
    .bottom { flex-wrap: wrap; gap: 24px; }
    .meta { width: auto; text-align: left; }
}
";
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<style>
html,body{margin:0;padding:0;font-family: 'Garamond', serif;background:#fff}
@page{size:A4 landscape;margin:0}
.page{padding:40px;box-sizing:border-box;width:100%;height:100%}
.cert-wrap{position:relative;background:#ffffff;border:6px solid #e6f4e9;padding:28px;box-sizing:border-box;min-height:100%;page-break-inside:avoid;overflow:hidden}
.outer-border{position:absolute;inset:28px;border:3px solid #14532d;pointer-events:none;z-index:0}
.watermark{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:rgba(16,100,45,0.06);font-size:140px;transform:rotate(-18deg);letter-spacing:18px}
.header{position:relative;z-index:2;display:flex;align-items:flex-start;gap:20px;margin-bottom:12px}
.logo-stack{position:relative;z-index:3;display:flex;flex-direction:row;gap:32px;justify-content:center;align-items:center;margin-bottom:24px;background:#ffffff;padding:10px 18px;border-radius:20px;box-shadow:0 14px 30px rgba(15,23,42,0.08)}
.logo-stack img{width:140px;height:auto;object-fit:contain}
.heading{flex:1;text-align:center;padding:0}
.heading h2{margin:0;font-size:56px;color:#14532d;letter-spacing:4px}
.heading .of{display:block;margin-top:6px;font-size:18px;color:#166534;letter-spacing:0.36em;text-transform:uppercase}
.seal{position:absolute;right:56px;top:64px;width:160px}
.intro{margin-top:28px;text-align:center}
.intro .label{font-size:13px;color:#374151;letter-spacing:0.45em;text-transform:uppercase}
.name{font-size:46px;color:#14532d;font-weight:700;margin:10px 0}
.statement{font-size:18px;color:#374151;max-width:920px;margin:16px auto;line-height:1.8}
.info-row{display:flex;justify-content:center;margin-top:26px}
.info{background:transparent;border:none;padding:0;width:auto}
.info strong{display:block;color:#14532d;margin-bottom:6px;font-size:18px}
.info span{font-size:28px;font-weight:700;color:#0f172a}
.bottom{display:flex;justify-content:center;align-items:flex-start;margin-top:40px;padding:0 18px;gap:32px}
.sign{display:flex;flex-direction:column;align-items:center;gap:8px}
.sign img{max-width:220px;height:auto;object-fit:contain}
.sign .who{font-size:13px;color:#475569;text-align:center}
.signature-date{width:220px;font-size:12px;color:#14532d;text-align:center;border-top:1px solid #14532d;padding-top:8px;margin-top:0;line-height:1.3}
.decor{position:absolute;right:20px;bottom:20px;width:320px;height:200px;pointer-events:none}
.qr{width:160px;height:160px;border-radius:12px;object-fit:cover}
.meta{display:none}
{$previewCss}
</style>
</head>
<body>
    <div class="cert-wrap">
        <div class="outer-border"></div>
        <div class="watermark">NACOS FCAI</div>
        <div class="header">
            <div class="logo-stack">
                <img src="{$logoNacos}" alt="NACOS Logo">
                <img src="{$logoFcai}" alt="FCAI Logo">
            </div>
            <div class="heading">
                <h2>Certificate</h2>
                <span class="of">OF MEMBERSHIP</span>
            </div>
        </div>

        <img class="seal" src="{$seal}" alt="seal">

        <div class="intro">
            <div class="label">THIS CERTIFICATE IS PROUDLY PRESENTED TO</div>
            <div class="name">{$fullName}</div>
            <div class="statement">as a bonafide MEMBER of the Nigeria Association of Computing Students, Federal College of Agriculture Ishiagu (NACOSFCAI).</div>
        </div>

        <div class="info-row">
            <div class="info"><strong>Membership Number</strong><span>{$membershipNumber}</span></div>
            </div>

        <div class="bottom">
            <div class="sign">
                <img src="{$signature}" alt="Signature">
                <div class="who"><strong>{$presidentName}</strong><br>{$presidentTitle}</div>
                <div class="signature-date">Verified on {$issuedAt}</div>
            </div>
            <img class="qr" src="{$qrCode}" alt="QR Code">
        </div>

        <svg class="decor" viewBox="0 0 400 240" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <defs>
                <linearGradient id="g1" x1="0" x2="1">
                    <stop offset="0" stop-color="#16a34a" stop-opacity="0.12" />
                    <stop offset="1" stop-color="#f3d488" stop-opacity="0.08" />
                </linearGradient>
            </defs>
            <path d="M0,200 C120,120 200,260 400,160 L400,240 L0,240 Z" fill="url(#g1)" />
        </svg>
    </div>
    </div>
</body>
</html>
HTML;
}

function generateStudentIdCardHtml(array $student, array $overrides = [], string $view = 'both'): string
{
    $fullName = trim((string)($overrides['full_name'] ?? $student['full_name'] ?? 'Student Name'));
    $membershipNumber = trim((string)($overrides['membership_number'] ?? $student['membership_number'] ?? ''));
    $course = trim((string)($overrides['course'] ?? $student['course'] ?? $student['course_of_study'] ?? $student['department'] ?? ''));
    $phone = trim((string)($overrides['phone'] ?? $student['phone'] ?? ''));
    $email = trim((string)($overrides['email'] ?? $student['email'] ?? ''));
    // Determine issued and valid-until dates. Allow overrides via $overrides for consistent printing.
    $issuedAtRaw = trim((string)($overrides['issued_at'] ?? $student['issued_at'] ?? ''));
    if ($issuedAtRaw !== '') {
        $issuedTs = @strtotime($issuedAtRaw);
        if ($issuedTs === false || $issuedTs === -1) {
            $issuedTs = time();
        }
    } else {
        $issuedTs = time();
    }
    $issuedAt = date('d M Y', $issuedTs);
    if (!empty($overrides['valid_until'])) {
        $validUntilTs = @strtotime($overrides['valid_until']);
        $validUntil = $validUntilTs ? date('d M Y', $validUntilTs) : date('d M Y', strtotime('+2 years', $issuedTs));
    } else {
        $validUntil = date('d M Y', strtotime('+2 years', $issuedTs));
    }
    $profilePhotoPath = trim((string)($overrides['profile_photo'] ?? $student['profile_photo'] ?? ''));
    $photoDataUri = $profilePhotoPath !== '' ? imageDataUri($profilePhotoPath) : '';
    $certConfig = [];
    $certConfigPath = APP_ROOT . '/certificate/config.php';
    if (file_exists($certConfigPath)) {
        $certConfig = include $certConfigPath;
    }

    $logoNacos = imageDataUri($certConfig['nacos_logo'] ?? 'certificate/nacos-logo.png');
    $logoFcai = imageDataUri($certConfig['fcai_logo'] ?? 'certificate/fcai-logo.png');
    $signature = imageDataUri($certConfig['signature'] ?? 'certificate/signature.png');
    $presidentName = e($certConfig['president_name'] ?? 'Comr. OGOH JOSHUA CHUKWUEMEKA');
    $presidentTitle = e($certConfig['president_title'] ?? 'President, NACOS FCAI Chapter');
    $chapterEmail = e($certConfig['chapter_email'] ?? 'nacosfcaishiagu@gmail.com');
    $chapterPhone = e($certConfig['chapter_phone'] ?? '+234 903 310 3319');
    $qrPayload = "Membership ID: {$membershipNumber}\nName: {$fullName}\nCourse: {$course}";
    $qrCode = generateQrCodeDataUri($qrPayload, 180);

    $fullNameDisplay = e($fullName !== '' ? $fullName : 'Student Name');
    $membershipDisplay = e($membershipNumber !== '' ? $membershipNumber : 'Not assigned');
    $courseDisplay = e($course !== '' ? $course : 'Not provided');
    $phoneDisplay = e($phone !== '' ? $phone : 'Not provided');
    $emailDisplay = e($email !== '' ? $email : 'Not provided');
    $issuedDisplay = e($issuedAt);
    $validUntilDisplay = e($validUntil);

    $photoMarkup = $photoDataUri !== ''
        ? '<img src="' . e($photoDataUri) . '" alt="Student Photo">'
        : '<div class="photo-placeholder"><span>No photo</span></div>';

        $frontCardContent = <<<FRONT
<div class="card-shell">
    <div class="header">
        <div class="logo-stack">
            <img src="{$logoNacos}" alt="NACOS Logo">
            <img src="{$logoFcai}" alt="FCAI Logo">
        </div>
        <div class="title-block">
            <div class="title">Nigeria Association of Computing Students</div>
            <div class="subtitle">NACOS FCAI</div>
        </div>
    </div>
    <div class="body">
        <div class="photo-wrap">
            <div class="badge">Member</div>
            {$photoMarkup}
        </div>
        <div class="info">
            <div class="name">{$fullNameDisplay}</div>
            <div class="info-grid">
                <div class="info-box"><span class="label">Membership ID</span><span class="value">{$membershipDisplay}</span></div>
                <div class="info-box"><span class="label">Course</span><span class="value">{$courseDisplay}</span></div>
                <div class="info-box"><span class="label">Phone</span><span class="value">{$phoneDisplay}</span></div>
                <div class="info-box"><span class="label">Email</span><span class="value">{$emailDisplay}</span></div>
            </div>
        </div>
    </div>
    <div class="qr-row">
        <div class="qr-box">
            <img src="{$qrCode}" alt="Verification QR">
        </div>
        <div class="qr-info">
            <div class="qr-caption">Scan for membership verification</div>
            <div class="qr-dates">Issued {$issuedDisplay} • Valid until {$validUntilDisplay}</div>
        </div>
    </div>
</div>
FRONT;

        $backCardContent = <<<BACK
<div class="card-shell">
    <div class="header">
        <div class="logo-stack">
            <img src="{$logoNacos}" alt="NACOS Logo">
            <img src="{$logoFcai}" alt="FCAI Logo">
        </div>
    </div>
    <div class="statement">This card is issued for the identification of the holder, whose name and photograph appears on the reverse side, as a Student Member of the Nigeria Association of Computing Students (NACOS).</div>
    <div class="block">
        <div class="block-title">Return Instructions</div>
        <div class="block-text">If found, please return this card to the NACOS FCAI Chapter or contact the chapter through the official contact details provided below.</div>
    </div>
    <div class="block">
        <div class="block-title">Official Contact</div>
        <div class="block-text">Email: {$chapterEmail}<br>Phone: {$chapterPhone}<br>Federal College of Agriculture, Ishiagu (FCAI)</div>
    </div>
    <div class="signature-block">
        <img src="{$signature}" alt="President Signature">
        <div class="signer-info">{$presidentName}<br>{$presidentTitle}</div>
    </div>
    <div class="footer-band">Official NACOS FCAI student identification card • Valid for chapter use only</div>
</div>
BACK;

    $sharedStyles = <<<STYLES
@page { size: 2.16in 3.34in; margin: 0; }
html, body { margin: 0; padding: 0; background: #ffffff; font-family: Arial, sans-serif; height: 100%; }
body { margin: 0; padding: 0; overflow: hidden; }
.page { width: 2.16in; height: 3.34in; box-sizing: border-box; padding: 0; margin: 0; overflow: hidden; page-break-after: always; }
.card-shell { width: 100%; height: 100%; box-sizing: border-box; padding: 6px 8px; background: linear-gradient(135deg, #f7fdf9 0%, #eefbf1 100%); border: 2px solid #0f7a41; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; page-break-inside: avoid; break-inside: avoid-column; }
.card-shell::before { content: ""; position: absolute; inset: 0; background-image: radial-gradient(circle at top left, rgba(8,120,63,0.14) 0 18px, transparent 19px), radial-gradient(circle at bottom right, rgba(8,120,63,0.10) 0 14px, transparent 15px); pointer-events: none; }
.header { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; position: relative; z-index: 1; }
.logo-stack { display: flex; align-items: center; gap: 8px; justify-content: center; }
.logo-stack img { max-height: 24px; width: auto; object-fit: contain; }
.title-block { flex: unset; text-align: center; }
.title-block .title { font-size: 6px; font-weight: bold; color:2 #0b5c2f; letter-spacing: 0.5px; text-transform: uppercase; }
.title-block .subtitle { font-size: 5px; color: #2f6b43; margin-top: 1px; }
.badge { display: inline-flex; justify-content: center; align-items: center; padding: 1px 6px; border-radius: 999px; background: #08783f; color: #fff; font-size: 4.2px; letter-spacing: 0.7px; text-transform: uppercase; font-weight: 800; position: absolute; top: -8px; left: 50%; transform: translateX(-50%); z-index: 2; box-shadow: 0 1px 3px rgba(0,0,0,0.18); white-space: nowrap; }
.body { display: flex; flex-direction: column; align-items: center; gap: 6px; margin-top: 10px; position: relative; z-index: 1; }
.photo-wrap { position: relative; width: 64px; height: 80px; border-radius: 10px; overflow: visible; border: 2px solid #0f7a41; background: #f3fbf6; flex-shrink: 0; margin: 0 auto; }
.photo-wrap img, .photo-placeholder { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
.photo-placeholder { display: flex; align-items: center; justify-content: center; font-size: 4.8px; color: #4b5563; text-align: center; padding: 3px; }
.info { width: 100%; max-width: 100%; }
.name { font-size: 6.5px; font-weight: bold; color: #064e2a; margin: 6px 0 2px; line-height: 1.2; text-align: center; }
.info-grid { display: grid; grid-template-columns: 1fr; gap: 2px; width: 100%; }
.info-box { background: rgba(255,255,255,0.78); border: 1px solid #d8f0e0; border-radius: 4px; padding: 2px 3px; min-height: 14px; }
.info-box .label { display: block; font-size: 4.2px; color: #0f7a41; text-transform: uppercase; letter-spacing: 0.35px; font-weight: bold; margin-bottom: 1px; }
.info-box .value { font-size: 4.4px; color: #1f2937; line-height: 1.15; word-break: break-word; }
.qr-row { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; margin-top: 5px; position: relative; z-index: 1; page-break-inside: avoid; break-inside: avoid-column; white-space: normal; }
.qr-box { display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; }
.qr-box img { width: 100%; height: 100%; border: 1px solid #d5e8dc; border-radius: 4px; }
.qr-info { display: none; }
.qr-caption { font-size: 4.3px; color: #4b5563; line-height: 1.2; text-align: right; white-space: normal; }
.qr-dates { font-size: 4.3px; color: #0b5c2f; font-weight: bold; text-align: right; white-space: normal; }
.statement { margin-top: 5px; font-size: 4.3px; line-height: 1.25; color: #23412f; position: relative; z-index: 1; }
.block { margin-top: 4px; padding: 4px 5px; background: rgba(255,255,255,0.82); border: 1px solid #dbeee1; border-radius: 5px; position: relative; z-index: 1; }
.block-title { font-size: 4.6px; font-weight: bold; color: #0b5c2f; margin-bottom: 1px; text-transform: uppercase; letter-spacing: 0.35px; }
.block-text { font-size: 4px; line-height: 1.3; color: #334155; }
.signature-block { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; margin-top: 5px; position: relative; z-index: 1; page-break-inside: avoid; break-inside: avoid-column; }
.signature-block img { width: 42px; height: auto; object-fit: contain; }
.signer-info { font-size: 4.2px; color: #1f2937; line-height: 1.2; text-align: left; width: 100%; }
.footer-band { margin-top: 6px; padding: 4px 5px; border-radius: 5px; background: #08783f; color: white; font-size: 4.4px; line-height: 1.3; position: relative; z-index: 1; }
.page-break { page-break-after: always; }
STYLES;

    if ($view === 'front') {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<style>
{$sharedStyles}
</style>
</head>
<body>
<div class="page">
{$frontCardContent}
</div>
</body>
</html>
HTML;
    }

    if ($view === 'back') {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<style>
{$sharedStyles}
</style>
</head>
<body>
<div class="page">
{$backCardContent}
</div>
</body>
</html>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<style>
{$sharedStyles}
</style>
</head>
<body>
<div class="page page-break">
{$frontCardContent}
</div>
<div class="page">
{$backCardContent}
</div>
</body>
</html>
HTML;
}

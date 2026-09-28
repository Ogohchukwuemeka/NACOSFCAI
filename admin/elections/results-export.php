<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

use Dompdf\Dompdf;

$auth = new Auth(Database::getInstance());
$auth->requireLogin('admin');
$election = new Election(Database::getInstance());

$electionId = (int)($_GET['election_id'] ?? 0);
$format = strtolower(trim((string)($_GET['format'] ?? '')));

if (!$electionId || !in_array($format, ['pdf', 'excel'], true)) {
    http_response_code(400);
    echo 'Invalid export request.';
    exit;
}

$elec = $election->getElection($electionId);
if (!$elec) {
    http_response_code(404);
    echo 'Election not found.';
    exit;
}

if ($format === 'pdf') {
    $content = $election->generateResultsPdf($electionId);
    if ($content === null) {
        http_response_code(500);
        echo 'PDF library is unavailable.';
        exit;
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="election-results-' . $electionId . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    echo $content;
    exit;
}

$csv = $election->generateResultsCsv($electionId);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="election-results-' . $electionId . '.csv"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
echo $csv;
exit;

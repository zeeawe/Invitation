<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

$imagePath = __DIR__ . '/assets/invitation.jpeg';
if (!is_file($imagePath) || !is_readable($imagePath)) {
    error_log('WHS invitation image is missing or unreadable: ' . $imagePath);
    http_response_code(404);
    exit('Invitation image not found.');
}

$image = fopen($imagePath, 'rb');
if ($image === false) {
    error_log('WHS invitation image could not be opened: ' . $imagePath);
    http_response_code(503);
    exit('Invitation image is temporarily unavailable.');
}

$imageSize = filesize($imagePath);
if ($imageSize === false) {
    fclose($image);
    error_log('WHS invitation image size could not be read: ' . $imagePath);
    http_response_code(503);
    exit('Invitation image is temporarily unavailable.');
}

$download = ($_GET['download'] ?? '') === '1';
header('Content-Type: image/jpeg');
header('Content-Length: ' . $imageSize);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600');
header(
    'Content-Disposition: ' . ($download ? 'attachment' : 'inline')
    . '; filename="WHS-Egypt-2026-VIP-Invitation.jpeg"'
);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && fpassthru($image) === false) {
    error_log('WHS invitation image could not be streamed: ' . $imagePath);
}

fclose($image);

<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}

$attendeeId = $_SESSION['confirmed_attendee_id'] ?? null;
if (!is_int($attendeeId) || $attendeeId < 1) {
    redirect('/');
}

$statement = $database->prepare('SELECT full_name FROM attendees WHERE id = :id');
$statement->execute([':id' => $attendeeId]);
$attendee = $statement->fetch();
if (!is_array($attendee) || !is_string($attendee['full_name'] ?? null)) {
    unset($_SESSION['confirmed_attendee_id']);
    redirect('/');
}

$fullName = $attendee['full_name'];
if (($_GET['source'] ?? '') === '1') {
    $imagePath = dirname(__DIR__) . '/porf/WhatsApp Image 2026-10-21 at 10.29.31 PM (1).jpeg';
    if (!is_file($imagePath) || !is_readable($imagePath)) {
        error_log('WHS personalized invitation source image is missing or unreadable: ' . $imagePath);
        http_response_code(404);
        exit('Invitation image not found.');
    }

    $imageSize = filesize($imagePath);
    $image = fopen($imagePath, 'rb');
    if ($imageSize === false || $image === false) {
        http_response_code(503);
        exit('Invitation image is temporarily unavailable.');
    }

    header('Content-Type: image/jpeg');
    header('Content-Length: ' . $imageSize);
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    if (fpassthru($image) === false) {
        error_log('WHS personalized invitation source image could not be streamed.');
    }
    fclose($image);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f1e8">
    <title>Your Personalized Invitation | Women's Health Summit 2026</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/invitation.css">
</head>
<body class="personalized-page">
    <main class="invitation-shell">
        <header class="masthead" aria-label="Women's Health Summit">
            <div class="masthead-brand">
                <img class="masthead-logo" src="/assets/whs-logo-transparent.png" alt="" width="52" height="52">
                <div class="masthead-copy">
                    <span>Women's Health Summit <b>2026</b></span>
                    <span class="edition-label">The 3rd Edition</span>
                </div>
            </div>
            <a class="personalized-back-link" href="/">Invitation home</a>
        </header>

        <section class="personalized-content" aria-labelledby="personalized-title">
            <h1 id="personalized-title">Your Personalized Invitation</h1>
            <figure class="personalized-poster-wrap">
                <canvas
                    class="personalized-poster"
                    id="personalized-poster"
                    width="908"
                    height="1280"
                    role="img"
                    aria-label="<?= escape('Personalized invitation for ' . $fullName) ?>"
                ></canvas>
                <img id="invitation-source" src="/personalized-invitation.php?source=1" alt="" hidden>
                <span id="attendee-name" class="visually-hidden"><?= escape($fullName) ?></span>
            </figure>
            <button class="poster-download personalized-download" id="download-invitation" type="button" disabled>
                Download Invitation
            </button>
            <p class="personalized-status" id="personalized-status" role="status" aria-live="polite">Preparing your invitation...</p>
            <noscript><p class="personalized-status">Enable JavaScript to view and download your personalized invitation.</p></noscript>
        </section>
    </main>
    <script src="/assets/personalized-invitation.js" defer></script>
</body>
</html>
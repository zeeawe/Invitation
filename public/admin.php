<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('admin_error', 'Your session expired. Please try again.');
        redirect('/admin.php');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'login') {
        $password = $_POST['password'] ?? '';
        if (is_string($password) && password_verify($password, $config['admin_password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            redirect('/admin.php');
        }
        flash('admin_error', 'The password was not accepted.');
        redirect('/admin.php');
    }

    if ($action === 'logout') {
        unset($_SESSION['admin_authenticated']);
        session_regenerate_id(true);
        redirect('/admin.php');
    }
}

if (($_GET['export'] ?? '') === 'csv') {
    if (empty($_SESSION['admin_authenticated'])) {
        http_response_code(404);
        exit('Not found');
    }

    $attendees = $database->query(
        'SELECT full_name, professional_title, confirmed_at FROM attendees ORDER BY confirmed_at, id'
    )->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="whs-2026-attendees.csv"');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Full Name', 'Professional Title / Position', 'Confirmed At']);
    foreach ($attendees as $attendee) {
        $values = [$attendee['full_name'], $attendee['professional_title'], $attendee['confirmed_at']];
        foreach ($values as &$value) {
            if (preg_match('/^[\s]*[=+\-@]/u', $value)) {
                $value = "'" . $value;
            }
        }
        unset($value);
        fputcsv($output, $values);
    }
    fclose($output);
    exit;
}

$adminAuthenticated = !empty($_SESSION['admin_authenticated']);
$errorMessage = flash('admin_error');
$attendees = $adminAuthenticated
    ? $database->query('SELECT full_name, professional_title, confirmed_at FROM attendees ORDER BY confirmed_at DESC, id DESC')->fetchAll()
    : [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendee Administration | WHS 2026</title>
    <link rel="stylesheet" href="/assets/invitation.css">
</head>
<body class="admin-page">
    <main class="admin-shell">
        <header class="admin-header">
            <a href="/" class="admin-brand">WHS <span>2026</span></a>
            <span>ATTENDANCE ADMINISTRATION</span>
        </header>

        <?php if (!$adminAuthenticated): ?>
            <section class="admin-login" aria-labelledby="admin-title">
                <p class="eyebrow">RESTRICTED ACCESS</p>
                <h1 id="admin-title">Administrator sign in</h1>
                <?php if ($errorMessage !== null): ?>
                    <div class="notice error" role="alert"><?= escape($errorMessage) ?></div>
                <?php endif; ?>
                <form method="post" action="/admin.php" class="confirmation-form">
                    <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="login">
                    <label for="admin-password">Password</label>
                    <input id="admin-password" name="password" type="password" autocomplete="current-password" required>
                    <button type="submit">SIGN IN</button>
                </form>
            </section>
        <?php else: ?>
            <section class="attendee-list" aria-labelledby="attendee-title">
                <div class="list-heading">
                    <div>
                        <p class="eyebrow">PRIVATE ADMIN VIEW</p>
                        <h1 id="attendee-title">Confirmed attendees</h1>
                        <p><?= count($attendees) ?> confirmation<?= count($attendees) === 1 ? '' : 's' ?></p>
                    </div>
                    <div class="admin-actions">
                        <a class="export-button" href="/admin.php?export=csv">EXPORT CSV</a>
                        <form method="post" action="/admin.php">
                            <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                            <input type="hidden" name="action" value="logout">
                            <button class="logout-button" type="submit">SIGN OUT</button>
                        </form>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Full name</th><th>Professional title / position</th><th>Confirmed</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendees as $attendee): ?>
                                <tr>
                                    <td><?= escape($attendee['full_name']) ?></td>
                                    <td><?= escape($attendee['professional_title']) ?></td>
                                    <td><?= escape($attendee['confirmed_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($attendees === []): ?>
                                <tr><td colspan="3" class="empty-state">No confirmations have been received yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
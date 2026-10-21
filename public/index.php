<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$errorMessage = null;
$errorArabicMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errorMessage = 'Your session expired. Please refresh the page and try again.';
        $errorArabicMessage = 'انتهت الجلسة. يرجى تحديث الصفحة ثم المحاولة مرة أخرى.';
    } else {
        $fullName = clean_field($_POST['full_name'] ?? null, 160);
        $professionalTitle = clean_field($_POST['professional_title'] ?? null, 120);
        $phoneNumber = clean_field($_POST['phone_number'] ?? null, 30);
        $email = clean_field($_POST['email'] ?? null, 254);
        $notesInput = $_POST['notes'] ?? '';
        $notes = is_string($notesInput)
            ? (trim($notesInput) === '' ? '' : clean_field($notesInput, 500))
            : null;

        $phoneDigits = $phoneNumber === null ? '' : preg_replace('/\D/', '', $phoneNumber);
        if ($fullName === null
            || $professionalTitle === null
            || $phoneNumber === null
            || !preg_match('/^\+?[0-9][0-9\s().-]*$/', $phoneNumber)
            || !is_string($phoneDigits)
            || strlen($phoneDigits) < 7
            || strlen($phoneDigits) > 15
            || $email === null
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || $notes === null) {
            $errorMessage = 'Please enter a valid name, professional title, phone number, and email address.';
            $errorArabicMessage = 'يرجى إدخال الاسم والمسمى الوظيفي ورقم الهاتف والبريد الإلكتروني بشكل صحيح.';
        } else {
            $normalizedName = mb_strtolower($fullName, 'UTF-8');
            $normalizedTitle = mb_strtolower($professionalTitle, 'UTF-8');
            $duplicateKey = hash('sha256', $normalizedName . "\0" . $normalizedTitle);

            try {
                $statement = $database->prepare(
                    'INSERT INTO attendees (full_name, professional_title, phone_number, email, notes, duplicate_key)
                     VALUES (:full_name, :professional_title, :phone_number, :email, :notes, :duplicate_key)'
                );
                $statement->execute([
                    ':full_name' => $fullName,
                    ':professional_title' => $professionalTitle,
                    ':phone_number' => $phoneNumber,
                    ':email' => $email,
                    ':notes' => $notes,
                    ':duplicate_key' => $duplicateKey,
                ]);

                $notificationEmail = $config['notification_email'] ?? 'contact@whs-egy.com';
                $mailFrom = $config['mail_from'] ?? 'noreply@whs-egy.com';
                $mailSubject = 'New WHS Egypt 2026 VIP attendance confirmation';
                $mailBody = "A new VIP attendance confirmation was saved.\n\n"
                    . "Full Name: {$fullName}\n"
                    . "Professional Title / Position: {$professionalTitle}\n"
                    . "Phone Number: {$phoneNumber}\n"
                    . "Email Address: {$email}\n"
                    . ($notes !== '' ? "Additional Notes: {$notes}\n" : '');
                $mailHeaders = [
                    'From: WHS Invitation <' . $mailFrom . '>',
                    'MIME-Version: 1.0',
                    'Content-Type: text/plain; charset=UTF-8',
                ];

                if (filter_var($notificationEmail, FILTER_VALIDATE_EMAIL) === false
                    || filter_var($mailFrom, FILTER_VALIDATE_EMAIL) === false
                    || !@mail($notificationEmail, $mailSubject, $mailBody, implode("\r\n", $mailHeaders))) {
                    error_log('WHS invitation email notification could not be handed off to the configured mail transport.');
                }

                $_SESSION['confirmed'] = true;
                redirect('/');
            } catch (PDOException $error) {
                if ($error->getCode() === '23000') {
                    $errorMessage = 'A confirmation with these details has already been received.';
                    $errorArabicMessage = 'تم استلام تأكيد بهذه البيانات من قبل.';
                } else {
                    error_log('WHS invitation submission failed: ' . $error->getMessage());
                    $errorMessage = 'We could not save your confirmation. Please try again shortly.';
                    $errorArabicMessage = 'تعذر حفظ التأكيد. يرجى المحاولة مرة أخرى بعد قليل.';
                }
            }
        }
    }
}

$confirmed = !empty($_SESSION['confirmed']);
unset($_SESSION['confirmed']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f1e8">
    <meta name="description" content="VIP invitation and attendance confirmation for the Women's Health Summit 2026 in Cairo.">
    <title>VIP Invitation | Women's Health Summit 2026</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@500;600;700&family=Tajawal:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/invitation.css">
</head>
<body>
    <main class="invitation-shell">
        <header class="masthead" aria-label="Women's Health Summit">
            <div class="masthead-brand">
                <img class="masthead-logo" src="/assets/whs-logo-transparent.png" alt="WHS Egypt 2026 logo" width="52" height="52" fetchpriority="high">
                <div class="masthead-copy">
                    <span>Women's Health Summit <b>2026</b></span>
                    <span class="edition-label">The 3rd Edition | <span lang="ar" dir="rtl">النسخة الثالثة</span></span>
                </div>
            </div>
        </header>

        <section class="invitation-layout" aria-labelledby="event-title">
            <figure class="poster-wrap">
                <img class="poster" src="/assets/invitation.jpeg" alt="VIP invitation to the Women's Health Summit, 5–6 December 2026, at Cairo Marriott Hotel in Zamalek" width="904" height="1280">
            </figure>

            <div class="details-panel">
                <div class="eyebrow"><span></span> BY SPECIAL INVITATION <span></span></div>
                <p class="arabic-kicker" lang="ar" dir="rtl">دعوة خاصة</p>
                <h1 id="event-title">Women's Health<br>Summit <span>2026</span><span class="title-shine" aria-hidden="true">Women's Health<br>Summit 2026</span></h1>
                <p class="intro">We are honored to invite you to an exceptional gathering dedicated to advancing women's health.</p>

                <div class="event-details" aria-label="Event details">
                    <div class="detail-row">
                        <span class="detail-label">DATE</span>
                        <span>5–6 December 2026</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">VENUE</span>
                        <span>Cairo Marriott Hotel<br><span class="muted">Zamalek, Cairo</span></span>
                    </div>
                </div>

                <div class="form-heading">
                    <h2>Confirm your attendance</h2>
                    <p lang="ar" dir="rtl">يرجى تأكيد الحضور</p>
                </div>

                <?php if ($confirmed): ?>
                    <div class="notice success" role="status">
                        <strong>Thank you. Your attendance is confirmed.</strong>
                        <span lang="ar" dir="rtl">شكرًا لكم، تم تأكيد حضوركم.</span>
                    </div>
                <?php else: ?>
                    <?php if ($errorMessage !== null): ?>
                        <div class="notice error" role="alert">
                            <span><?= escape($errorMessage) ?></span>
                            <span lang="ar" dir="rtl"><?= escape($errorArabicMessage ?? '') ?></span>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="/" class="confirmation-form" data-confirmation-form>
                        <input type="hidden" name="csrf_token" value="<?= escape(csrf_token()) ?>">
                        <div class="form-field">
                            <label for="full-name">
                                <span>Full name <b aria-hidden="true">*</b></span>
                                <span lang="ar" dir="rtl">الاسم الكامل <b aria-hidden="true">*</b></span>
                            </label>
                            <input id="full-name" name="full_name" type="text" maxlength="160" autocomplete="name" placeholder="Enter your full name" dir="auto" required>
                        </div>

                        <div class="form-field">
                            <label for="professional-title">
                                <span>Professional title / position <b aria-hidden="true">*</b></span>
                                <span lang="ar" dir="rtl">المسمى الوظيفي <b aria-hidden="true">*</b></span>
                            </label>
                            <input id="professional-title" name="professional_title" type="text" maxlength="120" autocomplete="organization-title" placeholder="Enter your professional title or position" dir="auto" required>
                        </div>

                        <div class="form-field">
                            <label for="phone-number">
                                <span>Phone number <b aria-hidden="true">*</b></span>
                                <span lang="ar" dir="rtl">رقم الهاتف <b aria-hidden="true">*</b></span>
                            </label>
                            <input id="phone-number" name="phone_number" type="tel" maxlength="30" autocomplete="tel" inputmode="tel" placeholder="Enter your phone number" dir="ltr" required>
                        </div>

                        <div class="form-field">
                            <label for="email-address">
                                <span>Email address <b aria-hidden="true">*</b></span>
                                <span lang="ar" dir="rtl">البريد الإلكتروني <b aria-hidden="true">*</b></span>
                            </label>
                            <input id="email-address" name="email" type="email" maxlength="254" autocomplete="email" placeholder="Enter your email address" dir="ltr" required>
                        </div>

                        <div class="form-field">
                            <label for="attendee-notes">
                                <span>Additional notes <b>(optional)</b></span>
                                <span lang="ar" dir="rtl">ملاحظات إضافية (اختياري)</span>
                            </label>
                            <textarea id="attendee-notes" name="notes" maxlength="500" rows="3" placeholder="Anything else you would like us to know?" dir="auto"></textarea>
                        </div>

                        <button class="submit-button" type="submit">
                            <span class="submit-spinner" aria-hidden="true"></span>
                            <span data-submit-label>CONFIRM ATTENDANCE</span>
                            <span aria-hidden="true">|</span>
                            <b lang="ar" dir="rtl" data-submit-label-ar>تأكيد الحضور</b>
                        </button>
                        <a class="poster-download" href="/assets/invitation.jpeg" download="WHS-Egypt-2026-VIP-Invitation.jpeg">
                            <span>DOWNLOAD INVITATION</span>
                            <span lang="ar" dir="rtl">تحميل الدعوة</span>
                        </a>
                        <span class="visually-hidden" data-submit-status aria-live="polite"></span>
                    </form>
                <?php endif; ?>

            </div>
        </section>

        <section class="summit-brochure-section" aria-label="About the Women's Health Summit">
            <figure class="summit-brochure">
                <img src="/assets/whs-summit-brochure.jpeg" alt="Women's Health Summit brochure featuring summit information, objectives, and scientific tracks" width="1280" height="906" loading="lazy">
            </figure>
        </section>

        <section class="contact-section" aria-labelledby="contact-title">
            <div class="contact-heading">
                <p class="arabic-kicker" lang="ar" dir="rtl">تواصل معنا</p>
                <h2 id="contact-title">WHS Egypt 2026</h2>
                <p lang="ar" dir="rtl">للاستفسارات والتواصل، اختاروا الطريقة المناسبة لكم</p>
            </div>
            <div class="contact-grid">
                <a class="contact-card" href="https://whs-egy.com/" target="_blank" rel="noopener noreferrer">
                    <span class="contact-card-label">WEBSITE</span>
                    <span class="contact-card-value">whs-egy.com</span>
                    <span class="contact-card-hint" lang="ar" dir="rtl">زيارة الموقع</span>
                </a>
                <a class="contact-card" href="mailto:contact@whs-egy.com">
                    <span class="contact-card-label">EMAIL</span>
                    <span class="contact-card-value">contact@whs-egy.com</span>
                    <span class="contact-card-hint" lang="ar" dir="rtl">إرسال بريد إلكتروني</span>
                </a>
                <div class="contact-card">
                    <span class="contact-card-label">CALL US</span>
                    <span class="contact-phone-links">
                        <a class="contact-phone-link" href="tel:01020180180" dir="ltr">01020180180</a>
                        <a class="contact-phone-link" href="tel:01095828282" dir="ltr">01095828282</a>
                    </span>
                    <span class="contact-card-hint" lang="ar" dir="rtl">اتصل بنا</span>
                </div>
                <a class="contact-card" href="https://wa.me/201095828282" target="_blank" rel="noopener noreferrer">
                    <span class="contact-card-label">WHATSAPP</span>
                    <span class="contact-card-value" dir="ltr">+20 10 9582 8282</span>
                    <span class="contact-card-hint" lang="ar" dir="rtl">راسلنا على واتساب</span>
                </a>
                <a class="contact-card" href="https://www.instagram.com/whs.egypt" target="_blank" rel="noopener noreferrer">
                    <span class="contact-card-label">INSTAGRAM</span>
                    <span class="contact-card-value">@whs.egypt</span>
                    <span class="contact-card-hint" lang="ar" dir="rtl">تابعونا على إنستغرام</span>
                </a>
                <a class="contact-card" href="https://www.facebook.com/share/1BGPdkgBTt/?mibextid=wwXIfr" target="_blank" rel="noopener noreferrer">
                    <span class="contact-card-label">FACEBOOK</span>
                    <span class="contact-card-value">WHS Egypt</span>
                    <span class="contact-card-hint" lang="ar" dir="rtl">تابعونا على فيسبوك</span>
                </a>
            </div>
            <p class="contact-organizer" lang="ar" dir="rtl">تنظيم <strong dir="ltr">Science Event Management</strong></p>
        </section>

        <footer class="page-footer">
            <span>WOMEN'S HEALTH SUMMIT 2026</span>
            <span>CAIRO · 5—6 DECEMBER</span>
        </footer>
        <div class="page-edition-footer">
            <span class="edition-label">The 3rd Edition | <span lang="ar" dir="rtl">النسخة الثالثة</span></span>
        </div>
    </main>
    <script src="/assets/invitation.js" defer></script>
</body>
</html>
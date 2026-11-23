<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$error = '';

$selectedRole = (
    $_GET['role'] ?? $_POST['role'] ?? 'customer'
) === 'delivery_agent' ? 'delivery_agent' : 'customer';

$storedLicensePath = null;
$absoluteLicensePath = null;
$licenseMime = null;
$licenseExtension = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));

    $phone = preg_replace(
        '/\D+/',
        '',
        (string)($_POST['phone'] ?? '')
    );

    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    $selectedRole = ($_POST['role'] ?? '') === 'delivery_agent'
        ? 'delivery_agent'
        : 'customer';

    $vehiclePlate = strtoupper(
        trim((string)($_POST['vehicle_plate'] ?? ''))
    );

    $licenseNumber = strtoupper(
        trim((string)($_POST['license_number'] ?? ''))
    );

    if (mb_strlen($fullName) < 3) {

        $error = 'Full name must contain at least three characters.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Enter a valid email address.';

    } elseif (strlen($phone) < 10 || strlen($phone) > 15) {

        $error = 'Enter a valid mobile number.';

    } elseif (strlen($password) < 8 || strlen($password) > 72) {

        $error = 'Password must contain between 8 and 72 characters.';

    } elseif ($password !== $confirmPassword) {

        $error = 'Password confirmation does not match.';

    } elseif (
        $selectedRole === 'delivery_agent' &&
        ($vehiclePlate === '' || $licenseNumber === '')
    ) {

        $error = 'Provide vehicle and license information.';

    } elseif (
        $selectedRole === 'delivery_agent' &&
        (
            !isset($_FILES['license_document']) ||
            $_FILES['license_document']['error'] !== UPLOAD_ERR_OK
        )
    ) {

        $error = 'Please upload your driving licence document.';

    } else {

        // Validate uploaded licence document.

        if ($selectedRole === 'delivery_agent') {

            $file = $_FILES['license_document'];

            $maxSize = 5 * 1024 * 1024;

            if (
                !is_uploaded_file($file['tmp_name']) ||
                $file['size'] <= 0 ||
                $file['size'] > $maxSize
            ) {

                $error = 'Licence file must be smaller than 5 MB.';

            } else {

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $licenseMime = finfo_file(
                    $finfo,
                    $file['tmp_name']
                );
                finfo_close($finfo);

                $allowedTypes = [
                    'application/pdf' => 'pdf',
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png'
                ];

                if (!isset($allowedTypes[$licenseMime])) {

                    $error = 'Only PDF, JPG, JPEG and PNG files are allowed.';

                } else {

                    $licenseExtension = $allowedTypes[$licenseMime];

                }
            }
        }

        if ($error === '') {

            $connection = null;

            try {

                $connection = db();

                $exists = $connection->prepare(
                    'SELECT id FROM users
                     WHERE email = ? OR phone = ?
                     LIMIT 1'
                );

                $exists->execute([$email, $phone]);

                if ($exists->fetch()) {

                    $error = 'An account already exists with this email or mobile number.';

                } else {

                    // Store licence outside the public website directory.

                    if ($selectedRole === 'delivery_agent') {

                        $uploadDirectory =
                            dirname(__DIR__, 3) .
                            DIRECTORY_SEPARATOR .
                            'smartfuel_private_uploads' .
                            DIRECTORY_SEPARATOR .
                            'licenses';

                        if (
                            !is_dir($uploadDirectory) &&
                            !mkdir($uploadDirectory, 0700, true)
                        ) {
                            throw new RuntimeException(
                                'Could not create licence storage folder.'
                            );
                        }

                        $fileName = bin2hex(random_bytes(16))
                            . '.' . $licenseExtension;

                        $absoluteLicensePath =
                            $uploadDirectory .
                            DIRECTORY_SEPARATOR .
                            $fileName;

                        $storedLicensePath = 'licenses/' . $fileName;

                        if (!move_uploaded_file(
                            $_FILES['license_document']['tmp_name'],
                            $absoluteLicensePath
                        )) {

                            throw new RuntimeException(
                                'Licence upload failed.'
                            );
                        }
                    }

                    $connection->beginTransaction();

                    $insert = $connection->prepare(
                        "INSERT INTO users
                        (full_name, email, password_hash, phone, role, status)
                        VALUES (?, ?, ?, ?, ?, 'active')"
                    );

                    $insert->execute([
                        $fullName,
                        $email,
                        password_hash($password, PASSWORD_BCRYPT),
                        $phone,
                        $selectedRole
                    ]);

                    $userId = (int)$connection->lastInsertId();

                    if ($selectedRole === 'delivery_agent') {

                        $profile = $connection->prepare(
                            'INSERT INTO delivery_agent_profiles
                            (user_id, vehicle_plate, license_number,
                             license_document, is_online, rating)
                            VALUES (?, ?, ?, ?, 0, 0.00)'
                        );

                        $profile->execute([
                            $userId,
                            $vehiclePlate,
                            $licenseNumber,
                            $storedLicensePath
                        ]);

                        sf_query(
                            "INSERT INTO sf_agent_approvals
                            (user_id, approval_status)
                            VALUES (?, 'Pending')",
                            [$userId]
                        );

                        sf_notify_admins(
                            'New delivery partner awaiting approval: ' . $fullName,
                            'admin/manage_users.php'
                        );
                    }

                    $connection->commit();

                    flash(
                        'success',
                        $selectedRole === 'delivery_agent'
                            ? 'Account created. Admin approval is required before accepting deliveries.'
                            : 'Account created successfully. You can sign in now.'
                    );

                    redirect('auth/login.php');
                }

            } catch (Throwable $exception) {

                safe_rollback($connection);

                // Remove uploaded file if registration fails.

                if (
                    $absoluteLicensePath !== null &&
                    is_file($absoluteLicensePath)
                ) {
                    unlink($absoluteLicensePath);
                }

                $error = friendly_database_error(
                    $exception,
                    'Account creation failed. Please check your details and try again.'
                );
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account | <?= APP_NAME ?></title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="<?= url('assets/css/custom.css') ?>">

    <link rel="stylesheet" href="<?= url('assets/css/upgrade.css') ?>">

    <style>
        .license-upload-box {
            border: 2px dashed #16a34a;
            border-radius: 14px;
            padding: 22px;
            text-align: center;
            background: rgba(22, 163, 74, 0.05);
            cursor: pointer;
            transition: 0.3s;
        }

        .license-upload-box:hover {
            background: rgba(22, 163, 74, 0.12);
        }

        .license-upload-box i {
            font-size: 30px;
            color: #16a34a;
            margin-bottom: 10px;
        }

        .license-upload-box input {
            margin-top: 12px;
            width: 100%;
            font-size: 13px;
        }

        .license-help {
            font-size: 12px;
            color: #888;
            margin-top: 8px;
        }

        #licenseFileName {
            font-size: 13px;
            color: #16a34a;
            margin-top: 8px;
            overflow-wrap: anywhere;
        }
    </style>

</head>

<body class="auth-page">

<main class="auth-shell">

    <section class="auth-visual">

        <a class="brand" href="<?= url('index.php') ?>">

            <span class="brand-mark">
                <i class="fa-solid fa-bolt"></i>
            </span>

            <span>SmartFuel <em>One</em></span>

        </a>

        <div class="auth-message">

            <span class="status-pill">
                <i class="pulse-dot"></i>
                Join the delivery ecosystem
            </span>

            <h1>
                Create your<br>
                <span class="gradient-text">mobility account.</span>
            </h1>

            <p>
                Order fuel as a customer or register your
                vehicle as a delivery partner.
            </p>

        </div>

    </section>

    <section class="auth-panel">

        <form
            class="auth-card"
            method="post"
            enctype="multipart/form-data"
            novalidate
        >

            <a class="auth-home-link" href="<?= url('index.php') ?>">
                ← Back to Home
            </a>

            <?= csrf_field() ?>

            <span class="eyebrow">New account</span>

            <h2>Get started</h2>

            <p>Choose your role and complete the secure registration.</p>

            <?php if ($error): ?>

                <div class="alert error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= e($error) ?>
                </div>

            <?php endif; ?>

            <div class="form-grid">

                <div class="form-group full">

                    <label for="role">Account type</label>

                    <select id="role" name="role" data-role-select>

                        <option value="customer"
                            <?= $selectedRole === 'customer' ? 'selected' : '' ?>>
                            Customer
                        </option>

                        <option value="delivery_agent"
                            <?= $selectedRole === 'delivery_agent' ? 'selected' : '' ?>>
                            Delivery Partner
                        </option>

                    </select>

                </div>

                <div class="form-group full">

                    <label for="full_name">Full name</label>

                    <input
                        id="full_name"
                        name="full_name"
                        value="<?= e($_POST['full_name'] ?? '') ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="email">Email address</label>

                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="<?= e($_POST['email'] ?? '') ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="phone">Mobile number</label>

                    <input
                        id="phone"
                        name="phone"
                        inputmode="numeric"
                        value="<?= e($_POST['phone'] ?? '') ?>"
                        required
                    >

                </div>

                <div class="form-group" data-agent-field>

                    <label for="vehicle_plate">Delivery vehicle plate</label>

                    <input
                        id="vehicle_plate"
                        name="vehicle_plate"
                        value="<?= e($_POST['vehicle_plate'] ?? '') ?>"
                    >

                </div>

                <div class="form-group" data-agent-field>

                    <label for="license_number">Driving licence number</label>

                    <input
                        id="license_number"
                        name="license_number"
                        value="<?= e($_POST['license_number'] ?? '') ?>"
                    >

                </div>

                <div class="form-group full" data-agent-field>

                    <label for="license_document">
                        Upload Driving Licence
                    </label>

                    <div class="license-upload-box">

                        <i class="fa-solid fa-cloud-arrow-up"></i>

                        <h3>Upload your driving licence</h3>

                        <p>Choose your licence document from your device.</p>

                        <input
                            type="file"
                            id="license_document"
                            name="license_document"
                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                        >

                        <div id="licenseFileName">
                            No file selected
                        </div>

                        <p class="license-help">
                            PDF, JPG or PNG · Maximum 5 MB
                        </p>

                    </div>

                </div>

                <div class="form-group">

                    <label for="password">Password</label>

                    <div class="password-wrap">

                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            required
                            placeholder="Minimum 8 characters"
                        >

                        <button
                            type="button"
                            data-password-toggle="password"
                            aria-label="Show password"
                        >
                            <i class="fa-solid fa-eye"></i>
                        </button>

                    </div>

                </div>

                <div class="form-group">

                    <label for="confirm_password">Confirm password</label>

                    <div class="password-wrap">

                        <input
                            id="confirm_password"
                            name="confirm_password"
                            type="password"
                            autocomplete="new-password"
                            required
                            placeholder="Type password again"
                        >

                        <button
                            type="button"
                            data-password-toggle="confirm_password"
                            aria-label="Show password"
                        >
                            <i class="fa-solid fa-eye"></i>
                        </button>

                    </div>

                </div>

            </div>

            <button class="btn" type="submit" style="width:100%">

                <i class="fa-solid fa-user-plus"></i>
                Create Account

            </button>

            <div class="auth-link">

                Already registered?

                <a href="<?= url('auth/login.php') ?>">
                    Sign in
                </a>

            </div>

        </form>

    </section>

</main>

<script>

const roleSelect = document.querySelector('[data-role-select]');

const licenseInput = document.getElementById('license_document');

const licenseFileName = document.getElementById('licenseFileName');

const syncRole = () => {

    const isAgent = roleSelect.value === 'delivery_agent';

    document.querySelectorAll('[data-agent-field]').forEach(field => {

        field.style.display = isAgent ? 'block' : 'none';

        field.querySelectorAll('input').forEach(input => {

            input.required = isAgent;

            input.disabled = !isAgent;

        });

    });

};

roleSelect.addEventListener('change', syncRole);

syncRole();

licenseInput.addEventListener('change', function () {

    if (this.files.length > 0) {

        licenseFileName.textContent =
            'Selected: ' + this.files[0].name;

    } else {

        licenseFileName.textContent = 'No file selected';

    }

});

</script>

<script src="<?= url('assets/js/main.js') ?>"></script>

</body>
</html>
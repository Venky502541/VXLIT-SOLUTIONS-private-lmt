<?php
// === CONFIGURATION ===
$csvFile = __DIR__ . '/internship_applications.csv';

// === Sanitize & collect POST data ===
$internship = trim($_POST['internship'] ?? '');
$domain     = trim($_POST['domain'] ?? '');
$fullName   = trim($_POST['fullName'] ?? '');
$email      = trim($_POST['email'] ?? '');
$phone      = trim($_POST['phone'] ?? '');
$college    = trim($_POST['college'] ?? '');
$year       = trim($_POST['year'] ?? '');
$remarks    = trim($_POST['remarks'] ?? '');

$errors = [];

// === Validation ===
if (!$internship || !$domain || !$fullName || !$email || !$phone || !$college || !$year) {
    $errors[] = "All required fields must be filled.";
}
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid email address.";
}
if ($phone && !preg_match('/^[0-9]{10}$/', $phone)) {
    $errors[] = "Phone number must be 10 digits.";
}

// === Page Header HTML Function ===
function page_header($title) {
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{$title}</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: linear-gradient(135deg, #0a2239, #16db93); min-height: 100vh; display:flex; align-items:center; justify-content:center; color:white; font-family: 'Poppins', sans-serif; }
.message-box { background: rgba(255,255,255,0.1); backdrop-filter: blur(8px); padding: 30px; border-radius: 15px; max-width: 650px; text-align:center; box-shadow: 0 0 20px rgba(0,0,0,0.4); }
.message-box h1 { font-weight: 700; }
.btn-back { background:#fff; color:#0a2239; font-weight:600; border-radius:20px; padding:10px 20px; }
.btn-back:hover { background:#eee; }
</style>
</head>
<body>
HTML;
}

// === Page Footer HTML Function ===
function page_footer() {
    echo <<<HTML
</body>
</html>
HTML;
}

// === If errors, show error page ===
if (!empty($errors)) {
    page_header("Application Error");
    echo "<div class='message-box'>";
    echo "<h1>⚠ Application Failed</h1>";
    echo "<ul class='text-start'>";
    foreach ($errors as $err) {
        echo "<li>" . htmlspecialchars($err) . "</li>";
    }
    echo "</ul>";
    echo "<a href='internships.html' class='btn btn-back mt-3'>← Go Back & Fix</a>";
    echo "</div>";
    page_footer();
    exit;
}

// === Save to CSV ===
$date = date('Y-m-d H:i:s');

if (!file_exists($csvFile)) {
    $headers = ['Date', 'Internship Type', 'Domain', 'Full Name', 'Email', 'Phone', 'College', 'Year', 'Remarks'];
    $file = fopen($csvFile, 'w');
    fputcsv($file, $headers);
    fclose($file);
}

$file = fopen($csvFile, 'a');
fputcsv($file, [$date, $internship, $domain, $fullName, $email, $phone, $college, $year, $remarks]);
fclose($file);

// === Show Success Page ===
page_header("Application Successful");
echo "<div class='message-box'>";
echo "<h1>✅ Application Submitted!</h1>";
echo "<p>Thank you, <strong>" . htmlspecialchars($fullName) . "</strong> for applying to our <strong>" . 
     htmlspecialchars($internship) . "</strong> program in the <strong>" . htmlspecialchars($domain) . "</strong> domain.</p>";
echo "<p>We will contact you at <strong>" . htmlspecialchars($email) . "</strong> soon with next steps.</p>";
echo "<a href='internships.html' class='btn btn-back mt-3'>← Back to Internships</a>";
echo "</div>";
page_footer();
exit;
?>

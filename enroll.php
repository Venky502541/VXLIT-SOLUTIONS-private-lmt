<?php
// ==== CONFIG ====
$csvFile = __DIR__ . '/enrollments.csv';

// ==== GET & SANITIZE INPUT ====
$course   = isset($_POST['course'])   ? trim($_POST['course'])   : '';
$fullName = isset($_POST['fullName']) ? trim($_POST['fullName']) : '';
$email    = isset($_POST['email'])    ? trim($_POST['email'])    : '';
$phone    = isset($_POST['phone'])    ? trim($_POST['phone'])    : '';
$college  = isset($_POST['college'])  ? trim($_POST['college'])  : '';
$year     = isset($_POST['year'])     ? trim($_POST['year'])     : '';
$remarks  = isset($_POST['remarks'])  ? trim($_POST['remarks'])  : '';

$errors = [];

// ==== VALIDATION ====
if(!$course || !$fullName || !$email || !$phone || !$college || !$year){
    $errors[] = "All required fields must be filled out.";
}
if($email && !filter_var($email, FILTER_VALIDATE_EMAIL)){
    $errors[] = "Please enter a valid email address.";
}
if($phone && !preg_match('/^[0-9]{10}$/', $phone)){
    $errors[] = "Please enter a valid 10-digit phone number.";
}

?>
<!DOCTYPE html>
<html>
<head>
<title>Course Enrollment</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color:#f8f9fa; padding-top:50px; }
.container { max-width: 650px; }
</style>
</head>
<body>
<div class="container">

<?php
if(!empty($errors)){
    // ==== Show error message ====
    echo '<div class="alert alert-danger shadow">';
    echo '<h4 class="alert-heading">⚠ Enrollment Failed</h4>';
    echo '<ul>';
    foreach($errors as $err){
        echo '<li>'.htmlspecialchars($err).'</li>';
    }
    echo '</ul>';
    echo '<hr><a href="courses.html" class="btn btn-secondary">← Go Back</a>';
    echo '</div>';
} else {
    // ==== SAVE TO CSV ====
    $date = date('Y-m-d H:i:s');
    $row = [$date, $course, $fullName, $email, $phone, $college, $year, $remarks];

    if(!file_exists($csvFile)){
        $file = fopen($csvFile, 'w');
        fputcsv($file, ['Timestamp','Course','Full Name','Email','Phone','College','Year','Remarks']);
    } else {
        $file = fopen($csvFile, 'a');
    }
    fputcsv($file, $row);
    fclose($file);

    // ==== Show success message ====
    echo '<div class="alert alert-success shadow">';
    echo '<h4 class="alert-heading">✅ Enrollment Successful!</h4>';
    echo '<p>Thank you, <strong>'.htmlspecialchars($fullName).'</strong>, for enrolling in <strong>'.htmlspecialchars($course).'</strong>.</p>';
    echo '<p>We will contact you via <strong>'.htmlspecialchars($email).'</strong> or phone soon with further details.</p>';
    echo '<hr><a href="courses.html" class="btn btn-primary">← Back to Courses</a>';
    echo '</div>';
}
?>

</div>
</body>
</html>

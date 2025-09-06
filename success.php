<?php
session_start();
if (!isset($_SESSION['application_success'])) {
    header("Location: career.php");
    exit();
}
$data = $_SESSION['application_success'];
unset($_SESSION['application_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Application Submitted - RVNS Solutions</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f9fafb;
            color: #222;
            padding: 2rem;
            text-align: center;
        }
        .container {
            max-width: 600px;
            margin: 3rem auto;
            background: #fff;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(22, 219, 147, 0.15);
        }
        h1 {
            color: #16db93;
            font-weight: 900;
        }
        h3 {
            margin-top: 1rem;
            color: #0a263f;
        }
        ul {
            text-align: left;
            margin-top: 1.5rem;
            list-style: none;
            padding-left: 0;
        }
        ul li {
            margin-bottom: 0.8rem;
        }
        a.btn-back {
            margin-top: 2rem;
            background: #16db93;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 15px;
            text-decoration: none;
            font-weight: 600;
        }
        a.btn-back:hover {
            background: #11895a;
        }
    </style>
</head>
<body>
<div class="container" role="main" tabindex="0">
    <h1>Application Submitted Successfully!</h1>
    <p>Thank you, <strong><?=htmlspecialchars($data['name']); ?></strong>, for applying to the <strong><?=htmlspecialchars($data['job_title']); ?></strong> position at RVNS Solutions.</p>
    <p>A confirmation email has been sent to <strong><?=htmlspecialchars($data['email']); ?></strong>.</p>
    <h3>Your Submitted Details</h3>
    <ul>
        <li><strong>Name:</strong> <?=htmlspecialchars($data['name']); ?></li>
        <li><strong>Email:</strong> <?=htmlspecialchars($data['email']); ?></li>
        <li><strong>Phone:</strong> <?=htmlspecialchars($data['phone']); ?></li>
        <li><strong>Job Title:</strong> <?=htmlspecialchars($data['job_title']); ?></li>
        <li><strong>Job Location:</strong> <?=htmlspecialchars($data['job_location']); ?></li>
        <li><strong>Cover Letter:</strong><br><pre><?=htmlspecialchars($data['cover_letter']); ?></pre></li>
    </ul>
    <a href="career.php" class="btn-back" role="button">Back to Careers Page</a>
</div>
</body>
</html>

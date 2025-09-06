<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer autoload (using Composer)
require 'vendor/autoload.php';

// Secret key for admin access
define('ADMIN_SECRET', 'SuperSecret123');

// File to store jobs
define('JOBS_FILE', 'jobs.json');

// Load jobs from file or initialize
$jobs = file_exists(JOBS_FILE) ? json_decode(file_get_contents(JOBS_FILE), true) : [];

// Admin login handling
if (isset($_POST['admin_login'])) {
    if (trim($_POST['secret_key']) === ADMIN_SECRET) {
        $_SESSION['is_admin'] = true;
    } else {
        $login_error = 'Invalid secret key.';
    }
}

// Admin logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: career.php");
    exit;
}

// Admin action: add job
if ($_SESSION['is_admin']??false && isset($_POST['add_job'])) {
    $newJob = [
        'id' => count($jobs) ? max(array_column($jobs, 'id')) + 1 : 1,
        'title' => trim($_POST['title']),
        'location' => trim($_POST['location']),
        'description' => trim($_POST['description']),
    ];
    $jobs[] = $newJob;
    file_put_contents(JOBS_FILE, json_encode($jobs, JSON_PRETTY_PRINT));
    header("Location: career.php");
    exit;
}

// Admin action: delete job
if ($_SESSION['is_admin']??false && isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $jobs = array_filter($jobs, fn($j) => $j['id'] !== $id);
    $jobs = array_values($jobs);
    file_put_contents(JOBS_FILE, json_encode($jobs, JSON_PRETTY_PRINT));
    header("Location: career.php");
    exit;
}

// Handle application submission
if (isset($_POST['apply'])) {
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
    $jobId = intval($_POST['job_id']);
    $cover = filter_var($_POST['cover_letter'], FILTER_SANITIZE_STRING);

    $appliedJob = null;
    foreach ($jobs as $job) {
        if ($job['id'] === $jobId) {
            $appliedJob = $job;
            break;
        }
    }
    if (!$appliedJob || !$name || !$email) {
        $application_error = "Please fill in all required fields properly.";
    } else {
        // Send email using PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.example.com';  // Replace with your SMTP
            $mail->SMTPAuth = true;
            $mail->Username = 'your_email@example.com'; // SMTP username
            $mail->Password = 'your_password';          // SMTP password
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('your_email@example.com', 'RVNS Careers');
            $mail->addAddress('hr@rvnssolutions.com', 'HR Department');

            if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                $mail->addAttachment($_FILES['resume']['tmp_name'], $_FILES['resume']['name']);
            }

            $mail->isHTML(true);
            $mail->Subject = "Job Application for {$appliedJob['title']} - $name";
            $mail->Body = "
                <h2>New Job Application</h2>
                <p><b>Name:</b> $name</p>
                <p><b>Email:</b> $email</p>
                <p><b>Phone:</b> $phone</p>
                <p><b>Position:</b> {$appliedJob['title']} ({$appliedJob['location']})</p>
                <p><b>Cover Letter:</b><br>" . nl2br(htmlspecialchars($cover)) . "</p>
            ";

            $mail->send();
            $application_success = "Thank you for applying! We will contact you soon.";
        } catch (Exception $e) {
            $application_error = "Mailer error: {$mail->ErrorInfo}";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>RVNS Careers</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet" />
  <style>
    body { font-family: 'Poppins', sans-serif; background:#f9fafb; margin:0; color:#222; }
    nav.navbar { background:#0a2239; }
    nav .navbar-brand, nav .nav-link { color:#16db93 !important; font-weight:600; }
    nav .nav-link.active, nav .nav-link:hover { color:#a7ffdb !important; }
    .hero {
      height: 340px;
      background: linear-gradient(135deg, rgba(22,219,147,0.9), rgba(6,48,121,0.85)),
      url('career-hero.jpg') center center / cover no-repeat;
      color: #fff; display: flex; justify-content: center; align-items: center; text-align: center; padding: 0 1rem;
    }
    .hero h1 { font-size: 3rem; font-weight: 900; }
    main { max-width: 960px; margin: 3rem auto 5rem; padding: 0 1rem; }
    h2 { font-weight: 900; color: #0a263f; margin-bottom: 1.5rem; }
    /* Admin login */
    .admin-login { margin-bottom: 4rem; max-width: 400px; margin-left:auto; margin-right:auto; }
    .admin-login input { width: 100%; padding: 0.75rem; font-size: 1rem; }
    .admin-login button { margin-top: 0.5rem; width: 100%; background:#16db93; border:none; color:white; font-weight:700; padding:0.8rem; cursor:pointer; }
    .admin-login button:hover { background:#12a673; }
    /* Jobs list */
    .job-listing { list-style:none; padding:0; margin-bottom:3rem; }
    .job-listing li { background:#e6f7f4; border: 2px solid #16db93; border-radius:16px; padding:1rem 1.5rem; margin-bottom:1rem; }
    .job-listing strong { display:block; font-size:1.2rem; color:#0a4433; }
    /* Application form */
    form label { font-weight:600; margin-top:1rem; display:block; }
    form input[type="text"],
    form input[type="email"],
    form input[type="tel"],
    form textarea,
    form select {
      width: 100%; padding: 0.75rem; border: 2px solid #16db93; border-radius: 10px; font-size: 1rem; margin-top: 0.5rem; resize: vertical;
    }
    form button {
      margin-top: 1.5rem; background:#16db93; border:none; color:#fff; font-weight:700; font-size:1.2rem; padding:1rem; border-radius:15px; cursor:pointer;
      width: 100%;
    }
    form button:hover { background:#12a673; }
    .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 12px; }
    .alert-success { background-color: #d1f0db; color: #155724; }
    .alert-error { background-color: #fccfcf; color: #721c24; }
    @media (max-width: 768px) {
      .job-listing li { padding: 1rem; }
      form button { font-size: 1rem; }
    }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a href="index.html" class="navbar-brand">RVNS Solutions</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto text-uppercase">
        <li><a href="index.html" class="nav-link">Home</a></li>
        <li><a href="courses.html" class="nav-link">Courses</a></li>
        <li><a href="internships.html" class="nav-link">Internships</a></li>
        <li><a href="projects.html" class="nav-link">Projects</a></li>
        <li><a href="development.html" class="nav-link">Development</a></li>
        <li><a href="career.php" class="nav-link active">Careers</a></li>
        <li><a href="contact.html" class="nav-link">Contact</a></li>
      </ul>
    </div>
  </div>
</nav>

<section class="hero" role="banner" aria-label="Carreers page hero">
  <h1>Join Our Team</h1>
</section>

<main role="main" class="container">
  <?php if (!isset($_SESSION['is_admin'])): ?>
  <!-- Admin login -->
  <section class="admin-login" aria-label="Admin login">
    <h2>Admin Access</h2>
    <form method="POST">
      <input type="password" name="secret_key" placeholder="Enter secret key to manage jobs" required/>
      <button type="submit" name="admin_login">Login</button>
    </form>
    <?php if(isset($login_error)): ?>
      <p style="color:red;"><?php echo htmlspecialchars($login_error); ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if(isset($_SESSION['is_admin'])): ?>
  <!-- Admin job management panel -->
  <section aria-label="Admin job management">
    <h2>Manage Job Openings</h2>
    <form method="POST">
      <input type="hidden" name="add_job" value="1"/>
      <label for="title">Job Title</label>
      <input id="title" name="title" type="text" required/>
      <label for="location">Location</label>
      <input id="location" name="location" type="text" required/>
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="4" required></textarea>
      <button type="submit">Add Job</button>
    </form>
    <h3>Current Jobs</h3>
    <ul>
      <?php foreach($jobs as $job): ?>
        <li>
          <strong><?php echo htmlspecialchars($job['title']); ?></strong> - <?php echo htmlspecialchars($job['location']); ?>
          <a href="?delete=<?php echo $job['id']; ?>" onclick="return confirm('Delete this job?')">Delete</a>
        </li>
      <?php endforeach; ?>
    </ul>
    <a href="?logout=1" style="margin-top:1rem; display:inline-block;">Logout</a>
  </section>
  <?php endif; ?>

  <!-- Job listings (visible to all) -->
  <section aria-label="Job Openings">
    <h2>Current Job Openings</h2>
    <?php if(count($jobs) === 0): ?>
      <p>No current openings available. Please check back soon.</p>
    <?php else: ?>
      <ul class="job-listing" role="list">
      <?php foreach($jobs as $job): ?>
        <li tabindex="0">
          <strong><?php echo htmlspecialchars($job['title']); ?></strong> - <?php echo htmlspecialchars($job['location']); ?>
          <p><?php echo htmlspecialchars($job['description']); ?></p>
        </li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <!-- Application form -->
  <section aria-label="Job Application Form">
    <h2>Apply for a Job</h2>

    <?php if(isset($application_success)): ?>
      <p class="alert alert-success"><?php echo $application_success; ?></p>
    <?php elseif(isset($application_error)): ?>
      <p class="alert alert-error"><?php echo $application_error; ?></p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="apply" value="1"/>
      <label for="name">Full Name*</label>
      <input id="name" name="name" type="text" required/>
      <label for="email">Email*</label>
      <input id="email" name="email" type="email" required/>
      <label for="phone">Phone</label>
      <input id="phone" name="phone" type="tel"/>
      <label for="job_id">Select Job*</label>
      <select id="job_id" name="job_id" required>
        <option value="" disabled selected>Choose a job opening</option>
        <?php foreach($jobs as $job): ?>
          <option value="<?php echo $job['id']; ?>"><?php echo htmlspecialchars($job['title']) . " - " . htmlspecialchars($job['location']); ?></option>
        <?php endforeach; ?>
      </select>
      <label for="cover_letter">Cover Letter</label>
      <textarea id="cover_letter" name="cover_letter" rows="5"></textarea>
      <label for="resume">Upload Resume (PDF, DOC, DOCX)*</label>
      <input id="resume" name="resume" type="file" accept=".pdf,.doc,.docx" required/>
      <button type="submit">Submit Application</button>
    </form>
  </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js" defer></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    AOS.init({ duration: 900, once: true });
  });
</script>
</body>
</html>

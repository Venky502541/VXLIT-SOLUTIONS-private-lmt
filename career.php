<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Configuration constants
define('ADMIN_SECRET', 'SuperSecret123');
define('JOBS_FILE', __DIR__ . '/jobs.json');
define('APPLICANTS_FILE', __DIR__ . '/applicants.csv');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'rvnssolutions@gmail.com');
define('SMTP_PASS', 'ernfdzpcwzwrrypf'); // Gmail app password
define('SMTP_PORT', 587);

// Load jobs data from JSON
$jobs = file_exists(JOBS_FILE) ? json_decode(file_get_contents(JOBS_FILE), true) : [];

$showAdminLogin = false;
$adminLoginError = null;

// Handle Admin Login
if (isset($_POST['admin_login'])) {
    if (trim($_POST['secret_key']) === ADMIN_SECRET) {
        $_SESSION['is_admin'] = true;
    } else {
        $adminLoginError = "Invalid secret key.";
        $showAdminLogin = true;
    }
}

// Admin logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: career.php");
    exit;
}

// Admin add job
if (isset($_SESSION['is_admin']) && isset($_POST['add_job'])) {
    $title = trim($_POST['title']);
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);
    if ($title && $location && $description) {
        $newJob = [
            'id' => count($jobs) ? max(array_column($jobs, 'id')) + 1 : 1,
            'title' => $title,
            'location' => $location,
            'description' => $description,
        ];
        $jobs[] = $newJob;
        file_put_contents(JOBS_FILE, json_encode($jobs, JSON_PRETTY_PRINT));
        header("Location: career.php");
        exit;
    }
}

// Admin delete job
if (isset($_SESSION['is_admin']) && isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $jobs = array_values(array_filter($jobs, fn($job) => $job['id'] !== $deleteId));
    file_put_contents(JOBS_FILE, json_encode($jobs, JSON_PRETTY_PRINT));
    header("Location: career.php");
    exit;
}

$application_data = [];
$application_success = null;
$application_error = null;

// Application submission handling
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
        $application_error = "Please fill in all required fields correctly.";
        $application_data = $_POST;
    } else {
        $application_data = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'job_title' => $appliedJob['title'],
            'job_location' => $appliedJob['location'],
            'cover_letter' => $cover,
        ];

        $csvRow = [
            $name, $email, $phone, $appliedJob['title'], $appliedJob['location'], str_replace(["\r", "\n"], [' ', ' '], $cover), date('Y-m-d H:i:s')
        ];
        $csvHeader = ['Name', 'Email', 'Phone', 'Position', 'Location', 'Cover Letter', 'AppliedAt'];

        $fileExists = file_exists(APPLICANTS_FILE);
        $fp = fopen(APPLICANTS_FILE, 'a');
        if (!$fileExists) {
            fputcsv($fp, $csvHeader);
        }
        fputcsv($fp, $csvRow);
        fclose($fp);

        $csvContent = file_get_contents(APPLICANTS_FILE);

        try {
            // HR email
            $mailHR = new PHPMailer(true);
            $mailHR->isSMTP();
            $mailHR->Host = SMTP_HOST;
            $mailHR->SMTPAuth = true;
            $mailHR->Username = SMTP_USER;
            $mailHR->Password = SMTP_PASS;
            $mailHR->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mailHR->Port = SMTP_PORT;

            $mailHR->setFrom(SMTP_USER, 'RVNS Careers');
            $mailHR->addAddress('hr@rvnssolutions.com', 'HR Department');

            if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                $mailHR->addAttachment($_FILES['resume']['tmp_name'], $_FILES['resume']['name']);
            }
            $mailHR->addStringAttachment($csvContent, 'all-applicants.csv');
            $mailHR->isHTML(true);
            $mailHR->Subject = "New Job Application: {$appliedJob['title']} - $name";
            $mailHR->Body = "<h2>New Job Application</h2>
                <p><b>Name:</b> $name</p>
                <p><b>Email:</b> $email</p>
                <p><b>Phone:</b> $phone</p>
                <p><b>Position:</b> {$appliedJob['title']} ({$appliedJob['location']})</p>
                <p><b>Cover Letter:</b><br>" . nl2br(htmlspecialchars($cover)) . "</p>";
            $mailHR->send();

            // Applicant confirmation email
            $mailApplicant = new PHPMailer(true);
            $mailApplicant->isSMTP();
            $mailApplicant->Host = SMTP_HOST;
            $mailApplicant->SMTPAuth = true;
            $mailApplicant->Username = SMTP_USER;
            $mailApplicant->Password = SMTP_PASS;
            $mailApplicant->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mailApplicant->Port = SMTP_PORT;

            $mailApplicant->setFrom('no-reply@rvnssolutions.com', 'RVNS Solutions');
            $mailApplicant->addAddress($email, $name);
            $mailApplicant->isHTML(true);
            $mailApplicant->Subject = "Your application received - RVNS Solutions";
            $mailApplicant->Body = "<div style='font-family:Arial,sans-serif;color:#333'>
                <h2 style='color:#16db93'>Thank You for Applying, $name!</h2>
                <p>Your application for the position <b>{$appliedJob['title']}</b> has been received.</p>
                <p>Our HR team will contact you soon.</p>
                <p>If you have questions, reply to this email or contact hr@rvnssolutions.com.</p>
                <br><p>Best regards,<br>RVNS Solutions Team</p>
                <hr style='border:none; border-top:1px solid #16db93;'/>
                <small>This is an automated message; please do not reply.</small>
                </div>";
            $mailApplicant->send();

            $_SESSION['application_success'] = $application_data;
            header("Location: success.php");
            exit;
        } catch (Exception $e) {
            $application_error = "Mailer Error: " . $mailHR->ErrorInfo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>RVNS Careers</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet"/>
<style>
  body { font-family: 'Poppins', sans-serif; background:#f9fafb; color:#222; margin:0; }
  nav.navbar { background:#0a2239; }
  nav .navbar-brand, nav .nav-link { color:#16db93 !important; font-weight:600; }
  nav .nav-link.active, nav .nav-link:hover { color:#a7ffdb !important; }
  .hero { min-height:340px; background: linear-gradient(135deg, rgba(22,219,147,0.9), rgba(6,48,121,0.85)), url('career-hero.jpg') center center/cover no-repeat; color:#fff; display:flex; flex-direction: column; justify-content:center; text-align:center; padding:1rem;}
  .hero h1 { font-size: 3rem; font-weight: 900; margin-bottom: 1rem; }
  main { max-width:960px; margin: 2rem auto 5rem; padding: 0 1rem; }
  h2 { font-weight: 900; color: #0a263f; margin-bottom: 1.5rem; }
  .job-listing { list-style:none; padding:0; margin-bottom: 2rem;}
  .job-listing li { background:#e6f7f4; border:2px solid #16db93; border-radius:16px; padding:1rem 1.5rem; margin-bottom: 1rem; position: relative;}
  .job-listing strong { display:block; font-size:1.2rem; color:#0a4433;}
  .apply-btn { position:absolute; top: 20px; right: 20px; background:#16db93; border:none; color:#fff; padding: 0.375rem 0.7rem; border-radius: 0.5rem; cursor:pointer;}
  .application-section { display:none; background:#fff; padding: 2rem; border-radius: 20px; box-shadow: 0 8px 25px rgba(22,219,147,0.15); margin-top: 2rem; }
  .application-section.active { display:block; }
  form label { font-weight:600; margin-top: 1rem; display: block; }
  form input[type=text], form input[type=email], form input[type=tel], form textarea, form select { width: 100%; padding: 0.75rem; border: 2px solid #16db93; border-radius:10px; margin-top: 0.5rem; resize: vertical;}
  form button { margin-top: 1.5rem; background: #16db93; border:none; color:#fff; font-weight:700; font-size: 1.2rem; padding:1rem; border-radius: 20px; cursor:pointer; width: 100%; }
  form button:hover { background: #11895a; }
  .alert { padding: 1rem; border-radius: 12px; margin-bottom: 1rem; }
  .alert-success { background: #d1f0db; color: #155724; }
  .alert-error { background: #fccfcf; color: #721c24; }
  /* Admin panel */
  #adminLoginPanel { display:none; max-width:400px; margin:2rem auto; background:#fff; padding:1.5rem; border-radius:20px; box-shadow: 0 10px 25px rgba(22,219,147,0.15);}
  #adminPanel { max-width:700px; margin: 2rem auto; background:#fff; padding:1.5rem; border-radius: 20px; box-shadow: 0 10px 25px rgba(22,219,147,0.15);}
  #adminPanel h2, #adminLoginPanel h2 { margin-bottom:1rem;}
  #adminToggle { position: fixed; bottom:16px; right:16px; background:#16db93; color:#fff; border:none; border-radius:50%; width:48px; height:48px; font-size:24px; cursor:pointer; box-shadow: 0 2px 10px rgba(0,0,0,0.3);}
  @media (max-width: 768px) {
    .hero h1 { font-size: 2.3rem;}
  }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top navbar-dark" style="background-color: #0a2239;">
  <div class="container">
    <a class="navbar-brand" href="index.php" style="font-weight:700; color:#16db93; letter-spacing: 1.5px; font-size:1.5rem; font-family: 'Poppins', sans-serif;">
      RVNS Solutions
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto text-uppercase">
        <li class="nav-item"><a href="index.html" class="nav-link" aria-current="page">Home</a></li>
        <li class="nav-item"><a href="about.html" class="nav-link">About</a></li>
        <li class="nav-item"><a href="courses.html" class="nav-link">Courses</a></li>
        <li class="nav-item"><a href="internships.html" class="nav-link">Internships</a></li>
        <li class="nav-item"><a href="projects.html" class="nav-link">Projects</a></li>
        <li class="nav-item"><a href="development.html" class="nav-link">Development</a></li>
        <li class="nav-item"><a href="career.php" class="nav-link active">Career</a></li>
        <li class="nav-item"><a href="feedback.php" class="nav-link">FAQ</a></li>
        <li class="nav-item"><a href="contact.html" class="nav-link">Contact</a></li>
      </ul>
    </div>
  </div>
</nav>

<section class="hero" aria-label="Careers hero" role="banner" style="
  min-height: 280px;
  background:
    linear-gradient(135deg, rgba(22,219,147,0.85), rgba(6,48,121,0.85)),
    url('career-hero.jpg') center center/cover no-repeat;
  color: #fff;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  padding: 2rem 2.5rem;
  max-width: 900px;
  margin: 2rem auto;
  border-radius: 20px;
  box-shadow: 0 8px 30px rgba(22,219,147,0.25);
  font-weight: 400;
  letter-spacing: 0.02em;
  line-height: 1.5;
  box-sizing: border-box;
">

  <h1 style="font-weight: 900; font-size: 2.8rem; margin-bottom: 0.5rem; line-height: 1.1em; max-width: 90%;">Join Our Team</h1>

  <p style="max-width: 620px; font-size: 1.2rem; margin-bottom: 1.5rem; font-weight: 400; line-height: 1.5;">
    Shape your future with RVNS Solutions. Explore exciting career opportunities and grow professionally with a team that values innovation.
  </p>

  <ul style="list-style: disc inside; max-width: 620px; font-size: 1rem; color: #a1e6c0; padding-left: 0; margin: 0; text-align: left;">
    <li>Work on impactful projects with cutting-edge technology</li>
    <li>Collaborative and supportive team culture</li>
    <li>Competitive salary and comprehensive benefits</li>
    <li>Flexible work schedules supporting work-life balance</li>
    <li>Continuous learning and career advancement opportunities</li>
  </ul>
</section>

<main class="container" role="main">
  <section aria-label="Current Job Openings">
    <h2>Current Job Openings</h2>
    <?php if (!empty($application_success)) : ?>
      <div class="alert alert-success"><?=htmlspecialchars($application_success)?></div>
    <?php elseif (!empty($application_error)) : ?>
      <div class="alert alert-error"><?=htmlspecialchars($application_error)?></div>
    <?php endif; ?>

    <?php if (empty($jobs)) : ?>
      <p>No current job openings. Please check back later.</p>
    <?php else : ?>
      <ul class="job-listing" role="list">
        <?php foreach ($jobs as $job) : ?>
          <li tabindex="0">
            <strong><?=htmlspecialchars($job['title'])?></strong> - <?=htmlspecialchars($job['location'])?>
            <p><?=htmlspecialchars($job['description'])?></p>
            <button class="apply-btn" data-jobid="<?= $job['id'] ?>">Apply Now</button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section id="applicationSection" class="application-section" aria-label="Job application form">
    <h2>Apply for a Job</h2>
    <form method="POST" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="apply" value="1" />
      <label for="name">Full Name*</label>
      <input id="name" name="name" type="text" required value="<?=htmlspecialchars($application_data['name'] ?? '')?>" />
      <label for="email">Email*</label>
      <input id="email" name="email" type="email" required value="<?=htmlspecialchars($application_data['email'] ?? '')?>" />
      <label for="phone">Phone</label>
      <input id="phone" name="phone" type="tel" value="<?=htmlspecialchars($application_data['phone'] ?? '')?>" />
      <label for="job_id">Select Job*</label>
      <select id="job_id" name="job_id" required>
        <option value="" disabled <?=!isset($application_data['job_id']) ? 'selected' : ''?>>Select a job</option>
        <?php foreach ($jobs as $job) : ?>
          <option value="<?= $job['id'] ?>" <?= (isset($application_data['job_id']) && $application_data['job_id'] == $job['id']) ? 'selected' : '' ?>>
            <?=htmlspecialchars($job['title'] . ' - ' . $job['location'])?>
          </option>
        <?php endforeach; ?>
      </select>
      <label for="cover_letter">Cover Letter</label>
      <textarea id="cover_letter" name="cover_letter" rows="5"><?=htmlspecialchars($application_data['cover_letter'] ?? '')?></textarea>
      <label for="resume">Upload Resume (PDF/DOC/DOCX)*</label>
      <input id="resume" name="resume" type="file" accept=".pdf,.doc,.docx" required />
      <button type="submit">Submit Application</button>
    </form>
  </section>

  <?php if (!isset($_SESSION['is_admin'])): ?>
  <section id="adminLoginPanel">
    <h2>Admin Login <small style="font-weight: normal;">(Press Ctrl+Shift+K to Toggle)</small></h2>
    <form method="POST">
      <input name="secret_key" type="password" placeholder="Enter secret key" required />
      <button name="admin_login" type="submit">Login</button>
    </form>
    <?php if ($adminLoginError): ?>
      <p style="color:red;"><?=htmlspecialchars($adminLoginError)?></p>
    <?php endif; ?>
  </section>
  <?php else: ?>
  <section id="adminPanel" aria-label="Admin Panel" style="margin-top: 2rem;">
    <h2>Manage Job Openings</h2>
    <form method="POST" style="margin-bottom: 2rem;">
      <input type="hidden" name="add_job" value="1" />
      <label>Job Title:</label>
      <input name="title" type="text" required />
      <label>Location:</label>
      <input name="location" type="text" required />
      <label>Description:</label>
      <textarea name="description" rows="4" required></textarea>
      <button type="submit" class="btn btn-success mt-2">Add Job</button>
    </form>
    <h3>Current Jobs</h3>
    <ul>
      <?php foreach ($jobs as $job): ?>
        <li><strong><?=htmlspecialchars($job['title'])?></strong> - <?=htmlspecialchars($job['location'])?>
          <a href="?delete=<?= $job['id'] ?>" onclick="return confirm('Delete this job?');" style="color:#d9534f; margin-left:1rem;">Delete</a>
        </li>
      <?php endforeach; ?>
    </ul>
    <a href="?logout=1" class="btn btn-danger mt-3">Logout</a>
  </section>
  <?php endif; ?>

</main>


<footer class="footer-bg bg-dark text-light py-5" role="contentinfo" aria-label="Site Footer">
  <div class="container">
    <div class="row gy-4 justify-content-between">
      
<div class="row align-items-center">
  <div class="col-12 col-md-3 text-center text-md-start py-3">
    <img src="logo1.jpg" alt="RVNS Solutions Logo"
         class="img-fluid"
         style="max-width:160px; width:100%; height:auto; margin-bottom:14px;" />
    <p class="fst-italic text-light" style="font-size:1rem; max-width:260px; font-weight:500; opacity:0.85; margin:0;">
      Empowering your IT journey with skills, projects, and innovation.
    </p>
  </div>
  <!-- Other columns here -->

      
      <div class="col-6 col-md-2">
        <h5>Quick Links</h5>
        <ul class="list-unstyled lh-lg mb-0">
          <li><a href="about.html" class="footer-link text-light text-decoration-none">About Us</a></li>
          <li><a href="courses.html" class="footer-link text-light text-decoration-none">Courses</a></li>
          <li><a href="internships.html" class="footer-link text-light text-decoration-none">Internships</a></li>
          <li><a href="projects.html" class="footer-link text-light text-decoration-none">Projects</a></li>
          <li><a href="contact.html" class="footer-link text-light text-decoration-none">Contact</a></li>
        </ul>
      </div>
      
      <div class="col-6 col-md-3 text-center text-md-start">
        <h5>Contact Us</h5>
        <p class="mb-1"><i class="bi bi-geo-alt-fill me-2"></i>Adoni, Andhra Pradesh, India</p>
        <p class="mb-1"><i class="bi bi-telephone-fill me-2"></i><a href="tel:+918328051076" class="footer-link text-light text-decoration-none">+91 8328051076</a></p>
        <p><i class="bi bi-envelope-fill me-2"></i><a href="mailto:rvnssolutions@gmail.com" class="footer-link text-light text-decoration-none">rvnssolutions@gmail.com</a></p>
      </div>
      
      <div class="col-12 col-md-3 text-center text-md-start">
        <h5>Subscribe to Our Newsletter</h5>
        <form onsubmit="event.preventDefault(); alert('Subscribed!');" class="d-flex flex-column flex-sm-row align-items-center" style="max-width: 320px; margin: auto;">
          <input type="email" placeholder="Enter your email" required aria-label="Email" class="form-control mb-2 mb-sm-0 me-sm-2" />
          <button type="submit" class="btn btn-primary w-100 w-sm-auto px-4">Subscribe</button>
        </form>
        <div class="social-icons mt-3 d-flex justify-content-center justify-content-md-start gap-3" style="font-size: 1.4rem;">
          <a href="#" aria-label="Facebook" class="text-light"><i class="bi bi-facebook"></i></a>
          <a href="#" aria-label="Twitter" class="text-light"><i class="bi bi-twitter"></i></a>
          <a href="#" aria-label="Instagram" class="text-light"><i class="bi bi-instagram"></i></a>
          <a href="#" aria-label="LinkedIn" class="text-light"><i class="bi bi-linkedin"></i></a>
          <a href="#" aria-label="YouTube" class="text-light"><i class="bi bi-youtube"></i></a>
          <a href="#" aria-label="WhatsApp" class="text-light"><i class="bi bi-whatsapp"></i></a>
        </div>
      </div>
    </div>
    <div class="text-center text-light small mt-4" style="opacity: 0.65;">
      <a href="privacy.html" class="footer-link text-light text-decoration-none me-3">Privacy Policy</a>
      <a href="terms.html" class="footer-link text-light text-decoration-none">Terms of Service</a>
      <p class="mt-2 mb-0">© 2025 RVNS Solutions. All rights reserved.</p>
    </div>
  </div>
</footer>

<!-- Bootstrap 5 JS bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const adminLoginPanel = document.getElementById('adminLoginPanel');

  function toggleAdminPanel() {
    if (!adminLoginPanel) return;
    adminLoginPanel.style.display = adminLoginPanel.style.display === 'block' ? 'none' : 'block';
    if (adminLoginPanel.style.display === 'block') {
      adminLoginPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  // Listen for Ctrl+Shift+K and toggle admin login panel
  document.addEventListener('keydown', e => {
    if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      toggleAdminPanel();
    }
  });
});

</script>
</body>
</html>

<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Configuration
define('ADMIN_SECRET', 'SuperSecret123');
define('JOBS_FILE', __DIR__ . '/jobs.json');
define('APPLICANTS_FILE', __DIR__ . '/applicants.csv');

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'rvnssolutions@gmail.com');
define('SMTP_PASS', 'ernfdzpcwzwrrypf'); // your Gmail app password
define('SMTP_PORT', 587);

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
if (isset($_SESSION['is_admin'], $_POST['add_job'])) {
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
if (isset($_SESSION['is_admin'], $_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $jobs = array_values(array_filter($jobs, fn($job) => $job['id'] !== $deleteId));
    file_put_contents(JOBS_FILE, json_encode($jobs, JSON_PRETTY_PRINT));
    header("Location: career.php");
    exit;
}

$application_data = [];
$application_success = null;
$application_error = null;

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
            // HR Mail
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

            // Applicant Confirmation
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
    <title>RVNS Careers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
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
<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a href="index.html" class="navbar-brand">RVNS Solutions</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu"
      aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
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

  <h1 style="
    font-weight: 900;
    font-size: 2.8rem;
    margin-bottom: 0.5rem;
    line-height: 1.1em;
    max-width: 90%;
    ">
    Join Our Team
  </h1>

  <p style="
    max-width: 620px;
    font-size: 1.2rem;
    margin-bottom: 1.5rem;
    font-weight: 400;
    line-height: 1.5;
    ">
    Shape your future with RVNS Solutions. Explore exciting career opportunities and grow professionally with a team that values innovation.
  </p>

  <ul style="
    list-style: disc inside;
    max-width: 620px;
    font-size: 1rem;
    color: #a1e6c0;
    padding-left: 0;
    margin: 0;
    text-align: left;
    ">
    <li>Work on impactful projects with cutting-edge technology</li>
    <li>Collaborative and supportive team culture</li>
    <li>Competitive salary and comprehensive benefits</li>
    <li>Flexible work schedules supporting work-life balance</li>
    <li>Continuous learning and career advancement opportunities</li>
  </ul>

  <style>
    @media (max-width: 768px) {
      .hero {
        padding: 1.5rem 1.5rem;
        min-height: 260px;
      }
      .hero h1 {
        font-size: 2rem !important;
        max-width: 100% !important;
      }
      .hero p {
        font-size: 1rem !important;
        max-width: 100% !important;
      }
      .hero ul {
        font-size: 0.9rem !important;
        max-width: 100% !important;
      }
    }
    @media (max-width: 400px) {
      .hero {
        padding: 1rem 1rem;
        min-height: 220px;
      }
      .hero h1 {
        font-size: 1.6rem !important;
      }
      .hero p {
        font-size: 0.9rem !important;
      }
      .hero ul {
        font-size: 0.85rem !important;
      }
    }
  </style>
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
            <strong><?=htmlspecialchars($job['title']);?></strong> - <?=htmlspecialchars($job['location']);?>
            <p><?=htmlspecialchars($job['description']);?></p>
            <button class="apply-btn" data-jobid="<?= $job['id']; ?>">Apply Now</button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section id="applicationSection" class="application-section" aria-label="Job application form">
    <h2>Apply for a Job</h2>
    <form method="POST" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="apply" value="1"/>
      <label for="name">Full Name*</label>
      <input id="name" name="name" type="text" required value="<?=htmlspecialchars($application_data['name'] ?? '')?>"/>
      <label for="email">Email*</label>
      <input id="email" name="email" type="email" required value="<?=htmlspecialchars($application_data['email'] ?? '')?>"/>
      <label for="phone">Phone</label>
      <input id="phone" name="phone" type="tel" value="<?=htmlspecialchars($application_data['phone'] ?? '')?>"/>
      <label for="job_id">Select Job*</label>
      <select id="job_id" name="job_id" required>
        <option value="" disabled <?= !isset($application_data['job_id']) ? 'selected' : '' ?>>Select a job</option>
        <?php foreach ($jobs as $job): ?>
         <option value="<?= $job['id'] ?>" <?= (isset($application_data['job_id']) && $application_data['job_id'] == $job['id']) ? 'selected' : '' ?>>
           <?= htmlspecialchars($job['title'] . ' - ' . $job['location']) ?>
         </option>
        <?php endforeach;?>
      </select>
      <label for="cover_letter">Cover Letter</label>
      <textarea id="cover_letter" name="cover_letter" rows="5"><?=htmlspecialchars($application_data['cover_letter'] ?? '')?></textarea>
      <label for="resume">Upload Resume* (PDF, DOC, DOCX)</label>
      <input id="resume" name="resume" type="file" accept=".pdf,.doc,.docx" required/>
      <button type="submit">Submit Application</button>
    </form>
  </section>
<section class="culture-section" data-aos="fade-up" data-aos-delay="400" aria-label="Company Culture" style="
 max-width: 900px;
 margin: 3rem auto;
 padding: 2rem 1.5rem;
 background: #daf9ec;
 border-radius: 20px;
 box-shadow: 0 8px 30px rgba(22, 219, 147, 0.15);
 color: #115f51;
 font-family: 'Poppins', sans-serif;
">
  <h2 class="section-title" style="font-weight: 900; font-size: 2.5rem; margin-bottom: 1rem; color: #0a263f;">Our Culture & Values</h2>
  <p style="font-size: 1.15rem; line-height: 1.6; margin-bottom: 1.5rem;">
    At RVNS Solutions, we foster a culture of innovation, collaboration, and continuous learning. We believe in empowering our team members to achieve their best, supporting growth both professionally and personally.
  </p>
  <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; font-size: 1.05rem;">
    <div style="flex: 1 1 260px; background: #16db93; color: white; padding: 1.2rem 1rem; border-radius: 16px; box-shadow: 0 10px 25px rgba(1,65,30,0.15);">
      <h3 style="font-weight: 700; margin-bottom: 0.5rem;">Innovation</h3>
      <p>We champion creativity and encourage our team to pioneer solutions that set new industry standards.</p>
    </div>
    <div style="flex: 1 1 260px; background: #11895a; color: white; padding: 1.2rem 1rem; border-radius: 16px; box-shadow: 0 10px 25px rgba(1,40,20,0.15);">
      <h3 style="font-weight: 700; margin-bottom: 0.5rem;">Collaboration</h3>
      <p>Teamwork is at the heart of what we do. We support each other and work together to achieve common goals.</p>
    </div>
    <div style="flex: 1 1 260px; background: #0a563d; color: white; padding: 1.2rem 1rem; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,30,10,0.15);">
      <h3 style="font-weight: 700; margin-bottom: 0.5rem;">Growth</h3>
      <p>We invest in continuous learning and provide opportunities that foster both personal and professional development.</p>
    </div>
  </div>
</section>

  <?php if (!isset($_SESSION['is_admin'])): ?>
  <section id="adminLoginPanel">
    <h2>Admin Login <small style="font-weight:normal">(Press Ctrl+Shift+K to Toggle)</small></h2>
    <form method="POST">
      <input name="secret_key" type="password" placeholder="Enter secret key" required/>
      <button name="admin_login" type="submit">Login</button>
    </form>
    <?php if ($adminLoginError): ?>
      <p style="color:red;"><?=htmlspecialchars($adminLoginError)?></p>
    <?php endif; ?>
  </section>
  <?php else: ?>
  <section id="adminPanel" aria-label="Admin Panel" style="margin-top:2rem;">
    <h2>Manage Job Openings</h2>
    <form method="POST" style="margin-bottom:2rem;">
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
        <li>
          <strong><?=htmlspecialchars($job['title']); ?></strong> - <?=htmlspecialchars($job['location']); ?>
          <a href="?delete=<?= $job['id']; ?>" onclick="return confirm('Delete this job?');" style="color:#d9534f; margin-left:1rem;">Delete</a>
        </li>
      <?php endforeach; ?>
    </ul>
    <a href="?logout=1" class="btn btn-danger mt-3">Logout</a>
  </section>
  <?php endif; ?>
</main>

<button id="adminToggle" title="Toggle Admin Login" aria-label="Toggle Admin Login" style="
  position: fixed;
  bottom: 16px;
  right: 16px;
  background: #16db93;
  color: white;
  border: none;
  border-radius: 50%;
  width: 48px;
  height: 48px;
  font-size: 24px;
  cursor: pointer;
  display:none;
  z-index: 1000;"></button>
  <footer class="footer-bg" role="contentinfo" aria-label="Site Footer" style="background-color:#0a2239; color:#d2dfe4; padding:40px 20px 20px;">
  <div class="container">
    <div class="footer-columns d-flex flex-column flex-md-row justify-content-between flex-wrap">
      
      <div class="footer-column text-center text-md-start mb-4 mb-md-0" style="flex:1;">
        <img src="logo.jpg" alt="RVNS Solutions" class="footer-logo" style="max-width:160px; margin-bottom:15px;" />
        <p class="fst-italic" style="font-size:0.9rem; max-width:300px;">Empowering your IT journey with skills, projects, and innovation.</p>
      </div>
      
      <div class="footer-column mb-4 mb-md-0" style="flex:1;">
        <h5>Quick Links</h5>
        <ul class="list-unstyled" style="line-height:2;">
          <li><a href="about.html" class="footer-link text-light text-decoration-none">About Us</a></li>
          <li><a href="courses.html" class="footer-link text-light text-decoration-none">Courses</a></li>
          <li><a href="internships.html" class="footer-link text-light text-decoration-none">Internships</a></li>
          <li><a href="projects.html" class="footer-link text-light text-decoration-none">Projects</a></li>
          <li><a href="contact.html" class="footer-link text-light text-decoration-none">Contact</a></li>
        </ul>
      </div>
      
      <div class="footer-column mb-4 mb-md-0" style="flex:1;">
        <h5>Contact Us</h5>
        <p><i class="bi bi-geo-alt-fill me-2"></i>Adoni, Andhra Pradesh, India</p>
        <p><i class="bi bi-telephone-fill me-2"></i><a href="tel:+918328051076" class="footer-link text-light text-decoration-none">+91 8328051076</a></p>
        <p><i class="bi bi-envelope-fill me-2"></i><a href="mailto:rvnssolutions@gmail.com" class="footer-link text-light text-decoration-none">rvnssolutions@gmail.com</a></p>
      </div>
      
      <div class="footer-column text-center text-md-start" style="flex:1;">
        <h5>Subscribe to Our Newsletter</h5>
        <form class="newsletter-form d-flex flex-column flex-sm-row" onsubmit="event.preventDefault(); alert('Thank you for subscribing!');" style="max-width:320px;">
          <input type="email" placeholder="Enter your email" required aria-label="Email" class="form-control mb-2 mb-sm-0 me-sm-2" />
          <button type="submit" class="btn btn-success">Subscribe</button>
        </form>
        <div class="social-icons mt-3" role="navigation" aria-label="Social Media Links" style="font-size: 1.5rem;">
          <a href="#" aria-label="Facebook" target="_blank" class="text-light me-3"><i class="bi bi-facebook"></i></a>
          <a href="#" aria-label="Twitter" target="_blank" class="text-light me-3"><i class="bi bi-twitter"></i></a>
          <a href="#" aria-label="Instagram" target="_blank" class="text-light me-3"><i class="bi bi-instagram"></i></a>
          <a href="#" aria-label="LinkedIn" target="_blank" class="text-light me-3"><i class="bi bi-linkedin"></i></a>
          <a href="#" aria-label="YouTube" target="_blank" class="text-light me-3"><i class="bi bi-youtube"></i></a>
          <a href="#" aria-label="Telegram" target="_blank" class="text-light me-3"><i class="bi bi-telegram"></i></a>
          <a href="#" aria-label="Threads" target="_blank" class="text-light me-3"><i class="bi bi-threads"></i></a>
          <a href="#" aria-label="GitHub" target="_blank" class="text-light me-3"><i class="bi bi-github"></i></a>
          <a href="https://wa.me/918328051076" aria-label="WhatsApp" target="_blank" class="text-light"><i class="bi bi-whatsapp"></i></a>
        </div>
      </div>
    </div>
    <div class="text-center text-light small mt-4" style="opacity: 0.7;">
      <a href="privacy.html" class="footer-link text-light me-3 text-decoration-none">Privacy Policy</a>
      <a href="terms.html" class="footer-link text-light me-3 text-decoration-none">Terms of Service</a>
      &copy; 2025 RVNS Solutions. All Rights Reserved.
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const applicationSection = document.getElementById('applicationSection');
  document.querySelectorAll('.apply-btn').forEach(button => {
    button.addEventListener('click', () => {
      const jobId = button.getAttribute('data-jobid');
      applicationSection.classList.add('active');
      document.getElementById('job_id').value = jobId;
      window.scrollTo({top: applicationSection.offsetTop - 50, behavior: 'smooth'});
      document.getElementById('name').focus();
    });
  });

  const adminLoginPanel = document.getElementById('adminLoginPanel');
  const adminToggleBtn = document.getElementById('adminToggle');

  const toggleAdminPanel = () => {
    if (adminLoginPanel) {
      adminLoginPanel.style.display = (adminLoginPanel.style.display === 'block') ? 'none' : 'block';
      if (adminLoginPanel.style.display === 'block') {
        adminLoginPanel.scrollIntoView({behavior: 'smooth', block: 'center'});
      }
    }
  };

  adminToggleBtn.addEventListener('click', toggleAdminPanel);

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

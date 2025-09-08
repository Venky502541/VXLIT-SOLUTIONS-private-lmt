<?php
// DB connection (update host, port, dbname, user, pass accordingly)
$host = "localhost";
$port = 3308;
$db = "rvns_db";
$user = "root";
$pass = "";
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Messages for feedback and question submission
$message = $q_message = "";

// Feedback submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_form'])) {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $rating = $_POST['rating'] ?? '';
    if ($name && $desc && in_array($rating, ['1','2','3','4','5'])) {
        $stmt = $pdo->prepare("INSERT INTO feedback (name, description, rating) VALUES (?, ?, ?)");
        $stmt->execute([$name, $desc, $rating]);
        $message = "Thank you for your feedback!";
    } else {
        $message = "Please fill in all fields correctly.";
    }
}

// Question submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question_form'])) {
    $q_name = trim($_POST['q_name'] ?? '');
    $question = trim($_POST['question'] ?? '');
    if ($q_name && $question) {
        $stmt = $pdo->prepare("INSERT INTO questions (name, question) VALUES (?, ?)");
        $stmt->execute([$q_name, $question]);
        $q_message = "Thank you! Your question will be answered by admin soon.";
    } else {
        $q_message = "Please enter your name and question.";
    }
}

// Admin reply handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_reply_qid'], $_POST['admin_reply_answer'], $_POST['admin_pass_code'])) {
    if ($_POST['admin_pass_code'] === 'rvns2025') { // Change passcode here!
        $qid = (int)$_POST['admin_reply_qid'];
        $answer = trim($_POST['admin_reply_answer']);
        $pdo->prepare("UPDATE questions SET answer=? WHERE id=?")->execute([$answer, $qid]);
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } else {
        $q_message = "Invalid admin passcode.";
    }
}

// Fetch data
$feedbacks = $pdo->query("SELECT id, name, description, rating, submitted_at FROM feedback ORDER BY submitted_at DESC")->fetchAll();
$questions = $pdo->query("SELECT id, name, question, answer, submitted_at FROM questions ORDER BY submitted_at DESC")->fetchAll();
$feedback_count = count($feedbacks);
$avg_rating = $feedback_count ? round(array_sum(array_column($feedbacks,'rating')) / $feedback_count, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Feedback & Q&A - RVNS Solutions</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet" />
<style>
  body {background:#f7faf9; font-family:'Poppins',sans-serif; color:#0a2940; padding:1rem;}
  .section-box {background:#fff; border-radius:15px; max-width:700px; margin:2rem auto; padding:2.5rem 1.5rem; box-shadow:0 8px 36px rgba(22,219,147,0.1);}
  h2 {color:#16b98b; font-weight:700; margin-bottom:1rem; text-align:center;}
  .message, .q-message {text-align:center; margin-bottom:16px; font-weight:600; font-size:1.1rem; color:#157c4a;}
  .feedback-list, .qa-list {display:grid; gap:1.5rem;}
  .feedback-card, .qa-card {background:#fafdfe; border-radius:15px; padding:1rem 1rem 1rem 1rem; box-shadow: 0 4px 15px rgba(22,219,147,0.2); min-height:130px; transition: box-shadow 0.24s, transform 0.21s;}
  .feedback-card:hover, .feedback-card:focus, .qa-card:hover, .qa-card:focus {box-shadow: 0 12px 36px rgba(22,219,147,0.4); transform: translateY(-5px); outline:none;}
  .feedback-rating {color:#16b98b; font-size:1.25rem; letter-spacing:0.02em; margin-bottom:6px; font-weight:bold;}
  .feedback-desc {font-style:italic; color:#444; font-size:1rem; margin-bottom:0.8rem; min-height:45px;}
  .feedback-author {font-weight:600; font-size:1rem; color:#168d7d; text-align:right;}
  .stat-main {font-size:1.1rem; font-weight:600; text-align:center; margin-bottom:1.5rem;}
  .rating-overall {font-size:1.6rem; color:#ffba44; font-weight:bold;}
  .qa-q {font-weight:700; font-size:1rem; margin-bottom:4px;}
  .qa-meta {color:#333B55; font-size:0.9rem; margin-bottom:3px;}
  .qa-a {margin-top:6px; color:#2C8564; padding-left:8px; border-left:3px solid #14c58d;}
  .qa-await {font-style:italic; color:#bb6e1e; margin-top:4px;}
  .admin-reply-form {display:none; margin-top:8px;}
  @media (min-width: 600px){.feedback-list {grid-template-columns: repeat(auto-fit,minmax(280px, 1fr));} .qa-list {grid-template-columns: 1fr;}}
  @media (max-width: 600px){.section-box {margin:1rem 0; padding:1.2rem;} .feedback-list, .qa-list {gap:1rem;}}
</style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background-color: #0a2239;">
  <div class="container">
    <a class="navbar-brand" href="index.html" style="font-weight:700; color:#16db93; letter-spacing: 1.5px; font-family: 'Poppins', sans-serif; font-size: 1.5rem;">
      RVNS Solutions
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" 
      aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml;charset=utf8,%3csvg viewBox=%270 0 30 30%27 xmlns=%27http://www.w3.org/2000/svg%27%3e%3cpath stroke=%27rgba(255,255,255,1)%27 stroke-width=%273%27 stroke-linecap=%27round%27 stroke-miterlimit=%2710%27 d=%27M4 7h22M4 15h22M4 23h22%27/%3e%3c/svg%3e');"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto text-uppercase">
        <li class="nav-item"><a href="index.html" class="nav-link" aria-current="page">Home</a></li>
        <li class="nav-item"><a href="about.html" class="nav-link">About</a></li>
        <li class="nav-item"><a href="courses.html" class="nav-link">Courses</a></li>
        <li class="nav-item"><a href="internships.html" class="nav-link">Internships</a></li>
        <li class="nav-item"><a href="projects.html" class="nav-link">Projects</a></li>
        <li class="nav-item"><a href="development.html" class="nav-link">Development</a></li>
        <li class="nav-item"><a href="career.php" class="nav-link">Career</a></li>
        <li class="nav-item"><a href="feedback.php" class="nav-link active">FAQ</a></li>
        <li class="nav-item"><a href="contact.html" class="nav-link">Contact</a></li>
      </ul>
    </div>
  </div>
</nav>

<style>
.navbar-brand {
  font-weight: 700;
  color: #16db93 !important;
  letter-spacing: 1.5px;
}
.navbar-dark .navbar-nav .nav-link {
  color: #16db93;
  font-weight: 600;
  padding: 0.45rem 0.75rem;
  transition: color 0.3s ease;
  letter-spacing: 0.04em;
}
.navbar-dark .navbar-nav .nav-link:hover,
.navbar-dark .navbar-nav .nav-link:focus {
  color: #a7ffdb;
  outline: none;
}
.navbar-dark .navbar-nav .nav-link.active {
  color: #a7ffdb;
  font-weight: 700;
  border-bottom: 3px solid #16db93;
  padding-bottom: 0.35rem;
}
.navbar-dark .navbar-toggler-icon {
  background-image: url("data:image/svg+xml;charset=utf8,%3csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3e%3cpath stroke='rgba(255,255,255,1)' stroke-width='3' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
}
</style>

<!-- Bootstrap 5 JS (required for toggler) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<div class="section-box">
  <h2>We Value Your Feedback</h2>
  <div class="stat-main" aria-live="polite">
    <span class="rating-overall" aria-label="Average rating">
      <?= $feedback_count ? str_repeat('⭐', round($avg_rating)) : '' ?>
    </span>
    <?php if($feedback_count): ?>
      <span class="ms-2" aria-label="Average rating value">Avg: <b><?=number_format($avg_rating,1)?></b> / 5</span>
      <span class="ms-3" aria-label="Total number of feedbacks">Total: <b><?= $feedback_count ?></b></span>
    <?php else: ?>
      <span aria-label="No ratings yet">No ratings yet</span>
    <?php endif; ?>
  </div>

  <?php if($message): ?>
    <div class="message" role="alert"><?=htmlspecialchars($message)?></div>
  <?php endif; ?>

  <form method="post" class="mb-4" novalidate>
    <input type="hidden" name="feedback_form" value="1" />
    <div class="mb-3">
      <label for="name">Your Name <span aria-hidden="true" class="text-danger">*</span></label>
      <input type="text" id="name" name="name" class="form-control" required maxlength="50" />
    </div>
    <div class="mb-3">
      <label for="description">Your Feedback <span aria-hidden="true" class="text-danger">*</span></label>
      <textarea id="description" name="description" class="form-control" rows="3" required maxlength="500"></textarea>
    </div>
    <div class="mb-3">
      <label for="rating">Rating <span aria-hidden="true" class="text-danger">*</span></label>
      <select id="rating" name="rating" class="form-select" required>
        <option value="" disabled selected>Select rating</option>
        <option value="1">⭐ 1 Star</option>
        <option value="2">⭐⭐ 2 Stars</option>
        <option value="3">⭐⭐⭐ 3 Stars</option>
        <option value="4">⭐⭐⭐⭐ 4 Stars</option>
        <option value="5">⭐⭐⭐⭐⭐ 5 Stars</option>
      </select>
    </div>
    <button type="submit" class="btn btn-success w-100" aria-label="Submit Feedback">Submit Feedback</button>
  </form>

  <div class="feedback-list" role="list">
    <?php if(!$feedback_count): ?>
      <p class="text-center text-muted" role="alert">No feedback yet.</p>
    <?php else: foreach($feedbacks as $fb): ?>
    <article class="feedback-card" tabindex="0" role="listitem" aria-label="Feedback by <?=htmlspecialchars($fb['name'])?>">
      <div class="feedback-rating" aria-hidden="true"><?=str_repeat('⭐', (int)$fb['rating'])?></div>
      <div class="feedback-desc">"<?=nl2br(htmlspecialchars($fb['description']))?>"</div>
      <footer class="feedback-author">— <?=htmlspecialchars($fb['name'])?></footer>
    </article>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="section-box">
  <h2>Ask a Question</h2>
  <?php if($q_message): ?><div class="q-message" role="alert"><?=htmlspecialchars($q_message)?></div><?php endif; ?>
  <form method="post" novalidate>
    <input type="hidden" name="question_form" value="1" />
    <div class="mb-3">
      <label for="q_name">Your Name <span aria-hidden="true" class="text-danger">*</span></label>
      <input type="text" id="q_name" name="q_name" class="form-control" required maxlength="60" />
    </div>
    <div class="mb-3">
      <label for="question">Your Question <span aria-hidden="true" class="text-danger">*</span></label>
      <textarea id="question" name="question" class="form-control" rows="3" required maxlength="600"></textarea>
    </div>
    <button type="submit" class="btn btn-warning w-100 text-white" aria-label="Submit Question">Submit Question</button>
  </form>

  <div class="qa-list mt-4" role="list" aria-live="polite" aria-relevant="additions" id="qaList">
  <?php if(!$questions): ?>
    <p class="text-center text-muted" role="alert">No questions asked yet.</p>
  <?php else: foreach($questions as $q): ?>
    <article class="qa-card" tabindex="0" role="listitem" data-qid="<?=$q['id']?>">
      <div class="qa-meta">Asked by <?=htmlspecialchars($q['name'])?> on <?=date('M d, Y',strtotime($q['submitted_at']))?></div>
      <div class="qa-q"><?=nl2br(htmlspecialchars($q['question']))?></div>
      <?php if(trim((string)$q['answer'])): ?>
        <div class="qa-a"><b>Admin Reply:</b><br><?=nl2br(htmlspecialchars($q['answer']))?></div>
      <?php else: ?>
        <div class="qa-await">Awaiting admin reply…</div>
      <?php endif; ?>
      <!-- Admin inline reply form -->
      <form method="post" class="admin-reply-form mt-2" style="display:none;">
        <input type="hidden" name="admin_reply_qid" value="<?=$q['id']?>">
        <div class="input-group">
          <input type="text" name="admin_reply_answer" class="form-control" placeholder="Type your reply" value="<?=htmlspecialchars($q['answer']??'')?>" required />
          <input type="hidden" name="admin_pass_code" />
          <button type="submit" class="btn btn-success" aria-label="Submit admin reply">Reply</button>
        </div>
      </form>
    </article>
  <?php endforeach; endif; ?>
  </div>
</div>
<!-- Footer -->
<footer class="footer-bg bg-dark text-light py-5" role="contentinfo" aria-label="Site Footer">
  <div class="container">
    <div class="row gy-4 justify-content-between">
      
      <div class="col-12 col-md-3 text-center text-md-start">
        <img src="logo1.jpg" alt="RVNS Solutions" class="img-fluid mb-3" style="max-width: 160px;" />
        <p class="fst-italic" style="max-width: 280px; margin: 0 auto 0 auto; font-size: 0.9rem;">
          Empowering your IT journey with skills, projects, and innovation.
        </p>
      </div>
      
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
      
<div class="col-12 col-md-3 text-center text-md-start">
  <h5>Contact Us</h5>
  <p><i class="bi bi-geo-alt-fill me-2"></i>Adoni, Andhra Pradesh, India</p>
  <p><i class="bi bi-telephone-fill me-2"></i>
    <a href="tel:+918328051076" class="footer-link text-light text-decoration-none">+91 8328051076</a>
  </p>
  <p class="mb-0">
    <i class="bi bi-envelope-fill me-2"></i>
    <a href="mailto:rvnssolutions@gmail.com" class="footer-link text-light text-decoration-none"
       style="word-break: break-all; white-space: normal;">
      rvnssolutions@gmail.com
    </a>
  </p>
</div>

      
      <div class="col-12 col-md-3 text-center text-md-start">
        <h5>Subscribe to Our Newsletter</h5>
        <form onsubmit="event.preventDefault(); alert('Subscribed!');" class="d-flex flex-column flex-sm-row align-items-center" style="max-width: 320px; margin: auto;">
          <input type="email" placeholder="Enter your email" required aria-label="Email" class="form-control mb-2 mb-sm-0 me-sm-2" />
          <button type="submit" class="btn btn-primary w-100 w-sm-auto px-4">Subscribe</button>
        </form>
        <div class="social-icons mt-3 d-flex justify-content-center justify-content-md-start gap-3" style="font-size: 1.2rem;">
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

<script>
  // Show admin reply forms with Ctrl+Shift+R secret key
  document.addEventListener('keydown', e => {
    if(e.ctrlKey && e.shiftKey && (e.key === 'R' || e.key === 'r')) {
      let pass = prompt("Admin passcode:");
      if(!pass) return;
      document.querySelectorAll('.admin-reply-form').forEach(form => {
        form.style.display = 'block';
        form.querySelector('input[name="admin_pass_code"]').value = pass;
      });
    }
  });

  // Confirmation prompt for admin replies (optional)
  document.querySelectorAll('.admin-reply-form').forEach(form => {
    form.addEventListener('submit', e => {
      if(!confirm('Send this reply?')) e.preventDefault();
    });
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

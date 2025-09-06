<?php
// Database settings - update as per your environment
$host = "localhost";
$port = 3308;   // Your MySQL port
$db   = "rvns_db";
$user = "root";
$pass = "";    // usually empty in XAMPP

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    // connection successful
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}


// Handle submission
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $rating = $_POST['rating'] ?? '';

    $validRatings = ['1','2','3','4','5'];
    if ($name && $description && in_array($rating, $validRatings)) {
        $stmt = $pdo->prepare("INSERT INTO feedback (name, description, rating) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $rating]);
        $message = "Thank you for your feedback!";
    } else {
        $message = "Please fill in all required fields correctly.";
    }
}

// Fetch all feedback
$stmt = $pdo->query("SELECT name, description, rating, submitted_at FROM feedback ORDER BY submitted_at DESC");
$feedbacks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Feedback - RVNS Solutions</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body {
      background: #f8f9fa;
      font-family: 'Poppins', sans-serif;
      padding: 2rem 1rem;
      color: #0a2940;
    }
    h2 {
      color: #16b98b;
      font-weight: 700;
      margin-bottom: 1.5rem;
      text-align: center;
    }
    #feedbackForm {
      max-width: 600px;
      margin: auto 0 3rem;
      background: #fff;
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .message {
      text-align: center;
      margin-bottom: 20px;
      font-weight: 600;
      color: green;
      font-size: 1.1rem;
    }
    .feedback-card {
      background: #fff;
      border-radius: 15px;
      padding: 25px;
      box-shadow: 0 7px 30px rgba(0,0,0,0.07);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      height: 100%;
    }
    .feedback-card:hover, .feedback-card:focus {
      transform: translateY(-8px);
      box-shadow: 0 12px 45px rgba(0,0,0,0.15);
      outline: none;
    }
    .feedback-rating {
      font-size: 1.5rem;
      color: #16b98b;
      font-weight: 700;
      margin-bottom: 15px;
      letter-spacing: 0.05em;
    }
    .feedback-description {
      font-style: italic;
      color: #444;
      margin-bottom: 1.2rem;
      min-height: 66px; /* for card height consistency */
    }
    .feedback-author {
      font-weight: 700;
      font-size: 1.1rem;
      color: #0a2940;
      text-align: right;
    }
    .container {
      max-width: 900px;
      margin: 0 auto;
    }
    .feedback-list {
      display: grid;
      grid-template-columns: repeat(auto-fit,minmax(280px,1fr));
      gap: 1.8rem 2rem;
    }
  </style>
</head>
<body>

<div class="container">
  <h2>We Value Your Feedback</h2>

  <div id="feedbackForm" role="region" aria-label="Feedback submission form">
    <?php if($message): ?>
      <div class="message" role="alert"><?=htmlspecialchars($message)?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="mb-3">
        <label for="name" class="form-label">Your Name <span aria-hidden="true" style="color:#dc3545;">*</span></label>
        <input type="text" id="name" name="name" class="form-control" maxlength="50" required>
      </div>

      <div class="mb-3">
        <label for="description" class="form-label">Your Feedback <span aria-hidden="true" style="color:#dc3545;">*</span></label>
        <textarea id="description" name="description" class="form-control" rows="4" minlength="10" maxlength="600" required></textarea>
      </div>

      <div class="mb-3">
        <label for="rating" class="form-label">Rating <span aria-hidden="true" style="color:#dc3545;">*</span></label>
        <select id="rating" name="rating" class="form-select" required>
          <option value="" disabled selected>Select your rating</option>
          <option value="1">⭐ 1 Star</option>
          <option value="2">⭐⭐ 2 Stars</option>
          <option value="3">⭐⭐⭐ 3 Stars</option>
          <option value="4">⭐⭐⭐⭐ 4 Stars</option>
          <option value="5">⭐⭐⭐⭐⭐ 5 Stars</option>
        </select>
      </div>

      <button type="submit" class="btn btn-success w-100" aria-label="Submit Feedback">Submit</button>
    </form>
  </div>

  <section aria-label="User feedback display">
    <h2 class="text-center">Feedback From Our Users</h2>
    <div class="feedback-list" role="list">
      <?php if(!$feedbacks): ?>
        <p class="text-center text-muted">No feedbacks submitted yet. Be the first to share your thoughts!</p>
      <?php else: ?>
        <?php foreach($feedbacks as $fb): ?>
          <article class="feedback-card" tabindex="0" role="listitem" aria-label="Feedback by <?=htmlspecialchars($fb['name'])?>">
            <div class="feedback-rating" aria-hidden="true"><?=str_repeat('⭐', (int)$fb['rating'])?></div>
            <div class="feedback-description">"<?=nl2br(htmlspecialchars($fb['description']))?>"</div>
            <footer class="feedback-author">— <?=htmlspecialchars($fb['name'])?></footer>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

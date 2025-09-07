<?php
$currentPage = basename($_SERVER['PHP_SELF']);
function isActive($page, $current) {
    return $page === $current ? 'active' : '';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background-color: #0a2239;">
  <div class="container">
    <a class="navbar-brand" href="index.php" style="font-weight:700; color:#16db93; letter-spacing:1.5px; font-family:'Poppins', sans-serif; font-size:1.5rem;">
      RVNS Solutions
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" 
      aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml;charset=utf8,%3csvg viewBox=\'0 0 30 30\' xmlns=\'http://www.w3.org/2000/svg\'%3e%3cpath stroke=\'rgba(255,255,255,1)\' stroke-width=\'3\' stroke-linecap=\'round\' stroke-miterlimit=\'10\' d=\'M4 7h22M4 15h22M4 23h22\'/%3e%3c/svg%3e');"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto text-uppercase">
        <li class="nav-item">
          <a href="index.php" class="nav-link <?= isActive('index.php', $currentPage) ?>" aria-current="page">Home</a>
        </li>
        <li class="nav-item">
          <a href="about.html" class="nav-link <?= isActive('about.html', $currentPage) ?>">About</a>
        </li>
        <li class="nav-item">
          <a href="courses.html" class="nav-link <?= isActive('courses.html', $currentPage) ?>">Courses</a>
        </li>
        <li class="nav-item">
          <a href="internships.html" class="nav-link <?= isActive('internships.html', $currentPage) ?>">Internships</a>
        </li>
        <li class="nav-item">
          <a href="projects.html" class="nav-link <?= isActive('projects.html', $currentPage) ?>">Projects</a>
        </li>
        <li class="nav-item">
          <a href="development.html" class="nav-link <?= isActive('development.html', $currentPage) ?>">Development</a>
        </li>
        <li class="nav-item">
          <a href="career.php" class="nav-link <?= isActive('career.php', $currentPage) ?>">Career</a>
        </li>
        <li class="nav-item">
          <a href="feedback.php" class="nav-link <?= isActive('feedback.php', $currentPage) ?>">FAQ</a>
        </li>
        <li class="nav-item">
          <a href="contact.html" class="nav-link <?= isActive('contact.html', $currentPage) ?>">Contact</a>
        </li>
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
  }
  /* Hamburger icon white lines */
  .navbar-dark .navbar-toggler-icon {
    background-image: url("data:image/svg+xml;charset=utf8,%3csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3e%3cpath stroke='rgba(255,255,255,1)' stroke-width='3' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
  }
</style>

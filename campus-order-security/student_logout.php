<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

student_logout();
redirect('register.php');

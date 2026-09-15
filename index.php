<?php
require_once __DIR__ . '/lib/bootstrap.php';
if (!current_user()) redirect('login.php');
$role = current_user()['role'];
redirect($role === 'admin' ? 'admin/index.php' : ($role === 'teacher' ? 'teacher/index.php' : 'student/index.php'));

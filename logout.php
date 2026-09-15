<?php
require_once __DIR__ . '/lib/bootstrap.php';
if (current_user()) {
    audit('logout');
}
logout_user();
header('Location: login.php');
exit;

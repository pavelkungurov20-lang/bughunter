<?php
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();

header('Location: dashboard.php');
exit;

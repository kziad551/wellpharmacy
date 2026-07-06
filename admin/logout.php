<?php
require __DIR__ . '/inc/auth.php';
admin_logout();
redirect('login');

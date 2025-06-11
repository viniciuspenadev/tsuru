<?php
require_once 'config/config.php';
require_once 'classes/ClientAuth.php';

$clientAuth = new ClientAuth();
$clientAuth->logout();

header('Location: client-login.php');
exit;
?>

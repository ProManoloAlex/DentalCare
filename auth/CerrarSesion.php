<?php
session_start();
$_SESSION = [];
session_destroy();
header("Location: Login.php"); // con mayúscula, coincide con tu nombre de archivo real
exit();
?>
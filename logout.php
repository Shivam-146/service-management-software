<?php
session_start();
session_unset();
session_destroy();
header("Location: /cctv/index.php");
exit();
?>

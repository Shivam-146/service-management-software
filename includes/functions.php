<?php
// Shared helper functions

function redirect($path) {
    header("Location: $path");
    exit();
}

function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}
?>

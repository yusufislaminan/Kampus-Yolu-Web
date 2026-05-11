<?php
$hash = '$2y$12$iFIxcouTW32RCC9f3ET6ZuAmwahRaAd7rql7eu2ylSDl7Mf1jGG12';
$password = 'Admin123!';
var_dump(password_verify($password, $hash));

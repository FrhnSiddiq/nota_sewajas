<?php
$html = file_get_contents('rental_create_admin2b.html');
echo strpos($html, 'value="5"') !== false ? 'FOUND ID 5' : 'NOT FOUND';
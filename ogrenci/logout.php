<?php
/**
 * Öğrenci çıkışı — yalnızca öğrenci oturumunu düşürür, panel oturumuna dokunmaz.
 */
require_once __DIR__ . '/../api/helpers.php';

student_logout();
header('Location: giris.php?cikis=1');
exit;

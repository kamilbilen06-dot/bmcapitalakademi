<?php
/**
 * Arka plan mail gönderici — tarayıcı beklemez.
 * CLI: php api/mail_worker.php JOBID
 * HTTP: /api/mail_worker.php?job=JOBID&k=KEY
 */
define('MAILER_WORKER', true);
ignore_user_abort(true);
@set_time_limit(120);

require_once __DIR__ . '/mailer.php';

$jobId = '';
if (PHP_SAPI === 'cli') {
    $jobId = (string) ($argv[1] ?? '');
} else {
    $key = (string) ($_GET['k'] ?? '');
    if ($key === '' || !hash_equals(mailer_worker_key(), $key)) {
        http_response_code(403);
        echo 'forbidden';
        exit;
    }
    $jobId = (string) ($_GET['job'] ?? '');
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ok';
    mailer_finish_response();
}

if ($jobId !== '') {
    mailer_queue_flush($jobId);
}
mailer_queue_flush(null);

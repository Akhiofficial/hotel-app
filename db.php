<?php
// db.php
$config = require __DIR__ . '/config.php';
$DB = new mysqli($config['db']['host'],$config['db']['user'],$config['db']['pass'],$config['db']['name'],$config['db']['port']);
if($DB->connect_errno) {
    die("DB connect error: " . $DB->connect_error);
}
$DB->set_charset('utf8mb4');

function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

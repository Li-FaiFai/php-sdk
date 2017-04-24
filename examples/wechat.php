<?php
require_once __DIR__ . '/../autoload.php';

use Wechat\Auth;

$objAuth = new Auth();
$result = $objAuth->test();

echo 'hello world';
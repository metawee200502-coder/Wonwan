<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
\['user_id'] = 1; // fake user
\['return_request_csrf'] = 'fake_csrf';
\['csrf_token'] = 'fake_csrf';
\['order_id'] = 1;
\['reason'] = 'damaged';
\['return_tracking'] = 'TH123456';
require 'api/submit_return_request.php';

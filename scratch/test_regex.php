<?php
$str = "[P2P_TRACKING_SUBMITTED:J&T Express:12345678990]";
preg_match('/\[P2P_TRACKING_SUBMITTED:(.*?):(.*?)\]/', $str, $matches);
var_export($matches);

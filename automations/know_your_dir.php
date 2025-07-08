<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(600);
ini_set('max_execution_time', 600);
session_start();
date_default_timezone_set('America/Los_Angeles');

echo "Current directory: " . __DIR__ . "<br>";

echo "Working directory: " . getcwd() . "<br>";
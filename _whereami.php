<?php
header('Content-Type: text/plain; charset=utf-8');
echo "__FILE__ = " . __FILE__ . PHP_EOL;
echo "__DIR__  = " . __DIR__  . PHP_EOL;
echo "getcwd() = " . getcwd() . PHP_EOL;
echo "DOCUMENT_ROOT = " . ($_SERVER['DOCUMENT_ROOT'] ?? '') . PHP_EOL;
echo "SCRIPT_FILENAME = " . ($_SERVER['SCRIPT_FILENAME'] ?? '') . PHP_EOL;
echo "SCRIPT_NAME = " . ($_SERVER['SCRIPT_NAME'] ?? '') . PHP_EOL;

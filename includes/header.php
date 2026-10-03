<?php
/**
 * HTML Header Template
 * Hospital Management System (HMS)
 */
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'Hospital Management System';
$baseUrl = $baseUrl ?? '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= escape($pageTitle) ?> - ModernCare HMS</title>
    
    <!-- CSS Design System -->
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/dashboard.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/responsive.css">
</head>
<body>
<div class="app-wrapper">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>

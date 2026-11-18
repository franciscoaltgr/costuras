<?php
/**
 * Plantilla de Cabecera (Header)
 * Archivo: includes/header.php
 */
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
require_once __DIR__ . '/helpers.php';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?>Taller de Costuras</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.03);
        }
        .badge-status {
            font-size: 0.85rem;
            padding: 0.4em 0.75em;
            font-weight: 600;
        }
        .card {
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 0.6rem;
        }
        .card-header {
            font-weight: 600;
            background-color: #ffffff;
            border-bottom: 1px solid rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <i class="bi bi-scissors text-warning fs-4"></i>
                <span>Taller de Costuras</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage === 'index.php') ? 'active fw-bold' : ''; ?>" href="index.php">
                            <i class="bi bi-list-check"></i> Listado de Pedidos
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-warning btn-sm text-dark fw-bold d-flex align-items-center gap-1 shadow-sm" href="crear_pedido.php">
                            <i class="bi bi-plus-circle-fill"></i> Nuevo Pedido
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container mb-5">
        <?= renderFlash(); ?>

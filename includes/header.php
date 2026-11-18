<?php
/**
 * Plantilla de Cabecera (Header) - Grill Guard
 * Archivo: includes/header.php
 */
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
require_once __DIR__ . '/helpers.php';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#8B4A6B">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?>Grill Guard | Taller de Costuras</title>

    <!-- Tipografía accesible y elegante (optimizada para legibilidad a toda edad) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        /* ==========================================================================
           TEMA PROFESIONAL - TALLER DE COSTURAS
           Paleta inspirada en costura artesanal: rosa polvo, salvia, crema natural.
           Tipografía: Fraunces (títulos, elegante) + Inter (cuerpo, altísima legibilidad).
           Tamaño base 17 px y alto contraste pensados para usuarios de 60+ años.
           ========================================================================== */
        :root {
            /* Paleta de marca: taller de costuras */
            --tc-plum:         #8B4A6B;   /* Primario - rosa polvo profundo */
            --tc-plum-dark:    #6B3752;
            --tc-plum-light:   #B8778F;
            --tc-plum-50:      #F7EEF2;
            --tc-sage:         #7A8471;   /* Verde salvia - acento natural */
            --tc-sage-light:   #A9B29E;
            --tc-gold:         #C9A26B;   /* Dorado cálido - acentos */
            --tc-cream:        #FBF7F1;   /* Fondo crema natural */
            --tc-ivory:        #FFFDF9;
            --tc-charcoal:     #2E252A;
            --tc-text:         #3B2F33;
            --tc-text-muted:   #7C6B72;
            --tc-border:       #E6DED5;
            --tc-success:      #5F8A4F;
            --tc-warning:      #C9892B;
            --tc-danger:       #B14B4B;

            /* Sombras y radios */
            --tc-shadow-sm:    0 1px 2px rgba(60, 40, 48, 0.06);
            --tc-shadow:       0 4px 14px rgba(60, 40, 48, 0.08);
            --tc-shadow-lg:    0 12px 32px rgba(60, 40, 48, 0.12);
            --tc-radius:       14px;
            --tc-radius-sm:    10px;

            /* Variables Bootstrap sobrescritas para que badges/botones
               de las páginas existentes hereden el nuevo color de marca */
            --bs-primary:        var(--tc-plum);
            --bs-primary-rgb:    139, 74, 107;
            --bs-link-color:     var(--tc-plum);
            --bs-link-hover-color: var(--tc-plum-dark);
            --bs-border-radius:  var(--tc-radius-sm);
        }

        /* ---------- Base ---------- */
        html, body { height: 100%; }
        html { font-size: 17px; }               /* Cuerpo más grande para lectura cómoda */

        body {
            background-color: var(--tc-cream);
            color: var(--tc-text);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            font-feature-settings: 'cv11', 'ss01', 'ss03';
            line-height: 1.6;
            letter-spacing: 0.005em;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        main { flex: 1 0 auto; }
        footer { flex-shrink: 0; }

        /* ---------- Tipografía elegante para títulos ---------- */
        h1, h2, h3, h4, .h1, .h2, .h3, .h4 {
            font-family: 'Fraunces', 'Georgia', serif;
            font-weight: 600;
            letter-spacing: -0.015em;
            color: var(--tc-charcoal);
        }
        h1, .h1 { font-size: 2.1rem; }
        h2, .h2 { font-size: 1.75rem; }
        h3, .h3 { font-size: 1.45rem; }
        h4, .h4 { font-size: 1.2rem; }

        /* ---------- Navbar de marca ---------- */
        .navbar.navbar-dark.bg-dark {
            background: linear-gradient(135deg, var(--tc-plum-dark) 0%, var(--tc-plum) 100%) !important;
            box-shadow: 0 2px 16px rgba(60, 40, 48, 0.18);
            padding-top: .9rem;
            padding-bottom: .9rem;
        }
        .navbar-brand {
            font-family: 'Fraunces', serif;
            font-weight: 700;
            font-size: 1.35rem;
            letter-spacing: -0.3px;
            color: #FFFDF9 !important;
        }
        .navbar-brand .brand-sub {
            font-family: 'Inter', sans-serif;
            font-weight: 400;
            font-size: 0.95rem;
            opacity: 0.85;
        }
        .navbar .nav-link {
            color: rgba(255, 253, 249, 0.85) !important;
            font-weight: 500;
            padding: .55rem 1rem;
            border-radius: 10px;
            transition: background-color .18s ease, color .18s ease;
        }
        .navbar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.10);
            color: #FFFDF9 !important;
        }
        .navbar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.16);
            color: #FFFDF9 !important;
        }
        .brand-logo-img {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid rgba(255, 253, 249, 0.85);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
        }

        /* ---------- Botones ---------- */
        .btn {
            font-weight: 600;
            border-radius: var(--tc-radius-sm);
            padding: .55rem 1.1rem;
            transition: transform .12s ease, box-shadow .18s ease, background-color .18s ease;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary {
            background-color: var(--tc-plum);
            border-color: var(--tc-plum);
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--tc-plum-dark);
            border-color: var(--tc-plum-dark);
            box-shadow: 0 6px 16px rgba(139, 74, 107, 0.28);
        }
        .btn-outline-primary {
            color: var(--tc-plum);
            border-color: var(--tc-plum);
        }
        .btn-outline-primary:hover {
            background-color: var(--tc-plum);
            border-color: var(--tc-plum);
        }
        .btn-success { background-color: var(--tc-success); border-color: var(--tc-success); }
        .btn-outline-success { color: var(--tc-success); border-color: var(--tc-success); }
        .btn-outline-success:hover { background-color: var(--tc-success); border-color: var(--tc-success); }
        .btn-warning {
            background-color: var(--tc-gold);
            border-color: var(--tc-gold);
            color: #fff !important;
        }
        .btn-warning:hover {
            background-color: #B6914F;
            border-color: #B6914F;
            color: #fff !important;
            box-shadow: 0 6px 16px rgba(201, 162, 107, 0.32);
        }
        .btn-danger { background-color: var(--tc-danger); border-color: var(--tc-danger); }
        .btn-dark {
            background-color: var(--tc-charcoal);
            border-color: var(--tc-charcoal);
        }

        /* ---------- Tarjetas ---------- */
        .card {
            border: 1px solid var(--tc-border);
            border-radius: var(--tc-radius);
            box-shadow: var(--tc-shadow-sm);
            background-color: var(--tc-ivory);
        }
        .card-header {
            font-family: 'Fraunces', serif;
            font-weight: 600;
            background-color: var(--tc-ivory);
            border-bottom: 1px solid var(--tc-border);
            color: var(--tc-charcoal);
        }
        .card.shadow-sm { box-shadow: var(--tc-shadow) !important; }

        /* ---------- Tablas ---------- */
        .table {
            --bs-table-bg: transparent;
            color: var(--tc-text);
        }
        .table > thead {
            background-color: var(--tc-plum-50);
        }
        .table > thead th {
            font-family: 'Inter', sans-serif;
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.06em;
            color: var(--tc-plum-dark);
            font-weight: 700;
            border-bottom: 1px solid var(--tc-border);
            padding: .9rem .75rem;
        }
        .table > tbody > tr > td {
            padding: .85rem .75rem;
            vertical-align: middle;
            border-color: var(--tc-border);
        }
        .table-hover tbody tr:hover {
            background-color: var(--tc-plum-50);
        }
        .table-light, .table > :not(caption) > * > .table-light {
            --bs-table-bg: var(--tc-plum-50);
            color: var(--tc-plum-dark);
        }

        /* ---------- Formularios (legibles y con buen contraste) ---------- */
        .form-label {
            font-weight: 600;
            color: var(--tc-charcoal);
            margin-bottom: .4rem;
        }
        .form-control, .form-select {
            border-radius: var(--tc-radius-sm);
            border: 1px solid var(--tc-border);
            padding: .6rem .8rem;
            font-size: 1rem;
            color: var(--tc-text);
            background-color: #fff;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--tc-plum-light);
            box-shadow: 0 0 0 0.2rem rgba(139, 74, 107, 0.15);
        }
        .input-group-text {
            background-color: var(--tc-plum-50);
            border-color: var(--tc-border);
            color: var(--tc-plum-dark);
            font-weight: 600;
        }
        .form-check-input:checked {
            background-color: var(--tc-plum);
            border-color: var(--tc-plum);
        }

        /* ---------- Badges / insignias de estado ---------- */
        .badge {
            font-weight: 600;
            letter-spacing: 0.02em;
            padding: .5em .75em;
            border-radius: 999px;
        }
        .badge-status {
            font-size: 0.82rem;
            padding: .5em .9em;
            font-weight: 600;
        }

        /* ---------- Alertas ---------- */
        .alert {
            border-radius: var(--tc-radius);
            border: 1px solid var(--tc-border);
            box-shadow: var(--tc-shadow-sm);
        }
        .alert-success { background-color: #ECF4E7; color: #3F5F30; border-color: #C9DBB9; }
        .alert-danger  { background-color: #F8E8E8; color: #7D2E2E; border-color: #ECC3C3; }
        .alert-warning { background-color: #FBF2E0; color: #7A5820; border-color: #E8D3A5; }

        /* ---------- Links ---------- */
        a { text-decoration: none; }
        a:hover { text-decoration: underline; }
        .navbar a:hover, .btn, .card-header a { text-decoration: none !important; }

        /* ---------- Utilidades de marca ---------- */
        .text-primary { color: var(--tc-plum) !important; }
        .text-brand { color: var(--tc-plum) !important; }
        .bg-brand-soft { background-color: var(--tc-plum-50) !important; }
        .border-brand { border-color: var(--tc-plum) !important; }

        /* ---------- Footer refinado ---------- */
        footer.footer {
            background-color: var(--tc-ivory);
            border-top: 1px solid var(--tc-border);
            color: var(--tc-text-muted);
        }

        /* ---------- Accesibilidad extra para usuarios mayores ---------- */
        :focus-visible {
            outline: 3px solid var(--tc-plum-light);
            outline-offset: 2px;
        }
        .btn:focus-visible {
            outline-offset: 3px;
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 575.98px) {
            html { font-size: 16px; }
            .navbar-brand { font-size: 1.15rem; }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-3" href="index.php">
                <img src="assets/img/logo_grill_guard.jpg" alt="Grill Guard" class="brand-logo-img">
                <span>
                    Grill Guard
                    <span class="brand-sub d-none d-sm-inline">· Taller de Costuras</span>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-lg-1">
                    <li class="nav-item">
                        <a class="nav-link <?= in_array($currentPage, ['cotizaciones.php', 'nueva_cotizacion.php', 'ver_cotizacion.php', 'editar_cotizacion.php']) ? 'active' : ''; ?>" href="cotizaciones.php">
                            <i class="bi bi-file-earmark-text me-1"></i> Cotizaciones
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage === 'index.php') ? 'active' : ''; ?>" href="index.php">
                            <i class="bi bi-list-check me-1"></i> Listado de Pedidos
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-warning btn-sm fw-bold d-flex align-items-center gap-1 px-3" href="crear_pedido.php">
                            <i class="bi bi-plus-circle-fill"></i> Nuevo Pedido
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container mb-5 flex-grow-1">
        <?= renderFlash(); ?>

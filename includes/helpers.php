<?php
/**
 * Funciones Auxiliares del Sistema
 * Archivo: includes/helpers.php
 * 
 * Codificación: UTF-8
 * Formato Monetario: Pesos Chilenos (CLP)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escapa cadenas para prevenir ataques Cross-Site Scripting (XSS)
 * garantizando la codificación UTF-8 adecuada para tildes y caracteres en español.
 * 
 * @param mixed $value
 * @return string
 */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Formatea un valor monetario en Pesos Chilenos (CLP)
 * El peso chileno es una moneda entera sin centavos (ej: $ 450.000)
 * 
 * @param float|int|string $amount
 * @return string
 */
function formatMoney($amount): string {
    return '$ ' . number_format((float)$amount, 0, '', '.');
}

/**
 * Formatea una fecha estándar Y-m-d a formato legible chileno d/m/Y
 * 
 * @param string|null $dateString
 * @return string
 */
function formatDate(?string $dateString): string {
    if (empty($dateString)) {
        return '-';
    }
    $timestamp = strtotime($dateString);
    return $timestamp ? date('d/m/Y', $timestamp) : e($dateString);
}

/**
 * Retorna la clase de badge de Bootstrap correspondiente al estado del pedido
 * 
 * @param string $estado
 * @return string
 */
function getEstadoBadgeClass(string $estado): string {
    return match ($estado) {
        'Pendiente'    => 'bg-warning text-dark',
        'En Proceso'   => 'bg-primary',
        'Prueba Lista' => 'bg-info text-dark',
        'Entregado'    => 'bg-success',
        'Cancelado'    => 'bg-secondary',
        default        => 'bg-light text-dark border',
    };
}

/**
 * Guarda un mensaje flash en sesión para ser renderizado en la siguiente vista
 * 
 * @param string $type success|danger|warning|info
 * @param string $message
 * @return void
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Renderiza y limpia el mensaje flash almacenado en sesión
 * 
 * @return string
 */
function renderFlash(): string {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        $type = e($flash['type']);
        $msg  = e($flash['message']);
        
        return "
        <div class=\"alert alert-{$type} alert-dismissible fade show\" role=\"alert\">
            {$msg}
            <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Cerrar\"></button>
        </div>";
    }
    return '';
}

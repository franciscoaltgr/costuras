<?php
/**
 * Funciones Auxiliares del Sistema
 * Archivo: includes/helpers.php
 * 
 * Codificación: UTF-8 Profesional
 * Moneda: Pesos Chilenos (CLP)
 */

// Asegurar codificación interna
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
ini_set('default_charset', 'UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escapa cadenas para prevenir ataques Cross-Site Scripting (XSS)
 * garantizando la codificación UTF-8 adecuada para tildes y caracteres en español.
 * Utiliza ENT_SUBSTITUTE para evitar que secuencias no válidas devuelvan una cadena vacía.
 * 
 * @param mixed $value
 * @return string
 */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Corrige posibles problemas de doble codificación o caracteres mal formados
 * 
 * @param string|null $string
 * @return string
 */
function fixUtf8(?string $string): string {
    if ($string === null || $string === '') {
        return '';
    }
    // Si ya es UTF-8 válido, verificar si tiene doble codificación típica UTF-8 en ISO-8859-1
    if (mb_check_encoding($string, 'UTF-8')) {
        $converted = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $string);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
        return $string;
    }
    return mb_convert_encoding($string, 'UTF-8', 'auto');
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
 * Formatea una fecha estándar Y-m-d a formato legible chileno d/m/Y o d-m-Y
 * 
 * @param string|null $dateString
 * @param string $separator
 * @return string
 */
function formatDate(?string $dateString, string $separator = '/'): string {
    if (empty($dateString)) {
        return '-';
    }
    $timestamp = strtotime($dateString);
    return $timestamp ? date("d{$separator}m{$separator}Y", $timestamp) : e($dateString);
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
 * Retorna la clase de badge para los estados de las cotizaciones
 * 
 * @param string $estado
 * @return string
 */
function getEstadoCotizacionBadgeClass(string $estado): string {
    return match ($estado) {
        'Borrador'              => 'bg-secondary',
        'Enviada'               => 'bg-primary',
        'Aprobada'              => 'bg-success',
        'Rechazada'             => 'bg-danger',
        'Convertida en Pedido'  => 'bg-info text-dark',
        default                 => 'bg-light text-dark border',
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

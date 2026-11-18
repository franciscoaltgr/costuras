<?php
/**
 * Generador de PDF Profesional para Cotizaciones Grill Guard
 * Archivo: includes/CotizacionPDF.php
 * Librería: FPDF (Open Source - Permissive License)
 */

require_once __DIR__ . '/../lib/fpdf/fpdf.php';
require_once __DIR__ . '/helpers.php';

class CotizacionPDF extends FPDF {
    protected array $cotizacion;
    protected array $items;

    public function __construct(array $cotizacion, array $items) {
        parent::__construct('P', 'mm', 'A4');
        $this->cotizacion = $cotizacion;
        $this->items = $items;
        $this->SetMargins(15, 15, 15);
        $this->SetAutoPageBreak(true, 25);
    }

    /**
     * Devuelve la altura real (en mm) a la que se dibujará una imagen
     * respetando su aspect ratio nativo al fijar un ancho objetivo.
     */
    protected function imageHeightForWidth(string $path, float $widthMm): float {
        if (!file_exists($path)) {
            return 0.0;
        }
        $info = @getimagesize($path);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return $widthMm;
        }
        return $widthMm * ($info[1] / $info[0]);
    }

    /**
     * Convierte de UTF-8 a Windows-1252 para compatibilidad total con fuentes estándar de FPDF
     */
    public function u(string $str): string {
        return mb_convert_encoding($str, 'windows-1252', 'UTF-8');
    }

    /**
     * Formato de dinero en CLP
     */
    public function money($amount): string {
        return '$ ' . number_format((float)$amount, 0, '', '.');
    }

    /**
     * Pie de página oficial
     */
    public function Footer() {
        $this->SetY(-18);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        
        // Línea divisoria suave
        $this->SetDrawColor(220, 220, 220);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(3);

        // Texto de pie de página: "2026 grill guard"
        $this->Cell(90, 5, $this->u('2026 grill guard - Taller de Costura y Confección'), 0, 0, 'L');
        $this->Cell(90, 5, $this->u('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    /**
     * Construye el documento de cotización completo basado en el formato oficial
     */
    public function generarDocumento(): void {
        $this->AliasNbPages();
        $this->AddPage();

        // ----- Parámetros del encabezado ajustados para evitar solape con la tabla inferior -----
        $logoX     = 15;
        $logoY     = 15;
        $logoW     = 30;            // ancho reducido para compactar la cabecera
        $tallerX   = 15 + $logoW + 6; // 6 mm de respiración entre logo y textos
        $headerTop = 16;

        // 1. ENCABEZADO: LOGO
        $logoPath = __DIR__ . '/../assets/img/logo_grill_guard.jpg';
        $logoBottom = $logoY;
        if (file_exists($logoPath)) {
            $logoH = $this->imageHeightForWidth($logoPath, $logoW);
            $this->Image($logoPath, $logoX, $logoY, $logoW);
            $logoBottom = $logoY + $logoH;
        }

        // 2. DATOS DEL TALLER (al costado derecho del logo)
        $this->SetXY($tallerX, $headerTop);
        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor(33, 37, 41);
        $this->Cell(78, 5, $this->u('GRILL GUARD'), 0, 2, 'L');

        $this->SetX($tallerX);
        $this->SetFont('Arial', 'B', 9.5);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(78, 4.6, $this->u('Pecheras Parrilleras · Taller de Costuras'), 0, 2, 'L');

        $this->Ln(1);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(60, 60, 60);

        $this->SetX($tallerX);
        $this->Cell(78, 4.3, $this->u('+56 9 1013 1407'), 0, 2, 'L');
        $this->SetX($tallerX);
        $this->Cell(78, 4.3, $this->u('grillguardch@gmail.com'), 0, 2, 'L');
        $this->SetX($tallerX);
        $this->Cell(78, 4.3, $this->u('Rosas 1339, Santiago Centro'), 0, 2, 'L');
        $this->SetX($tallerX);
        $this->Cell(78, 4.3, $this->u('La Hermita 1556, Talca'), 0, 2, 'L');

        $textosBottom = $this->GetY();

        // 3. CAJA VERDE: NÚMERO DE COTIZACIÓN (Top Right)
        $this->SetXY(128, $headerTop + 6);
        $this->SetFillColor(169, 209, 142);
        $this->SetDrawColor(140, 180, 115);
        $this->SetTextColor(20, 50, 20);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(67, 10, $this->u('COTIZACIÓN ' . $this->cotizacion['numero']), 1, 1, 'C', true);

        // 4. FECHA EMISIÓN bajo la caja verde
        $this->SetXY(128, $headerTop + 17);
        $this->SetFont('Arial', '', 8.5);
        $this->SetTextColor(100, 100, 100);
        $fechaCot = !empty($this->cotizacion['fecha']) ? date('d-m-Y', strtotime($this->cotizacion['fecha'])) : date('d-m-Y');
        $this->Cell(67, 5, $this->u('Fecha de emisión: ' . $fechaCot), 0, 1, 'C');

        // 5. MARGEN DE SEGURIDAD: calculamos el punto más bajo del bloque superior
        //    (logo, textos del taller y caja de cotización) y añadimos un gap limpio
        //    + una línea divisoria para separar visualmente la cabecera del cuerpo.
        $cajaCotBottom = $headerTop + 22; // fin de la línea "Fecha de emisión"
        $bloqueBottom  = max($logoBottom, $textosBottom, $cajaCotBottom);
        $gap = 6; // margen inferior garantizado tras la cabecera

        $yDivisor = $bloqueBottom + $gap;
        $this->SetDrawColor(205, 205, 205);
        $this->SetLineWidth(0.25);
        $this->Line(15, $yDivisor, 195, $yDivisor);

        // Punto de inicio de la tabla del cliente (SIEMPRE por debajo del logo)
        $this->SetY($yDivisor + 4);

        // 6. TABLA DE DATOS DEL CLIENTE (Bordes idénticos al formato de la imagen)
        $this->SetDrawColor(80, 80, 80);
        $this->SetLineWidth(0.3);

        // Fila 1: NOMBRE
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(30, 30, 30);
        $this->Cell(32, 7, $this->u('NOMBRE'), 1, 0, 'L');
        $this->SetFont('Arial', 'B', 9.5);
        $this->Cell(148, 7, $this->u(' ' . strtoupper($this->cotizacion['cliente_nombre'])), 1, 1, 'L');

        // Fila 2: DIRECCIÓN
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(32, 6.5, $this->u('DIRECCIÓN'), 1, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(148, 6.5, $this->u(' ' . ($this->cotizacion['cliente_direccion'] ?? '')), 1, 1, 'L');

        // Fila 3: TELÉFONO
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(32, 6.5, $this->u('TELÉFONO'), 1, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(148, 6.5, $this->u(' ' . ($this->cotizacion['cliente_telefono'] ?? '')), 1, 1, 'L');

        // Fila 4: CORREO
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(32, 6.5, $this->u('CORREO'), 1, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(148, 6.5, $this->u(' ' . ($this->cotizacion['cliente_email'] ?? '')), 1, 1, 'L');

        // Fila 5: FECHA
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(32, 6.5, $this->u('FECHA'), 1, 0, 'L');
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(70, 6.5, $this->u(' ' . $fechaCot), 1, 0, 'C');
        $this->Cell(78, 6.5, '', 1, 1, 'L'); // Bloque en blanco a la derecha para respetar la grilla

        $this->Ln(6);

        // 3. TABLA DE PRODUCTOS / CONFECCIÓN
        // Anchos de columna que suman 180 mm exactos
        $wDesc   = 58;
        $wColor  = 32;
        $wCant   = 28;
        $wUnit   = 31;
        $wValor  = 31;

        // Fila de encabezado con colores pastel según la imagen
        // Color amarillo suave (#FFE699) para DESCRIPCIÓN y CANTIDAD, y durazno suave (#F8CBAD) para VALOR
        $this->SetFont('Arial', 'B', 9);
        $this->SetDrawColor(80, 80, 80);

        // Celda superior combinada o cabecera descriptiva
        $this->SetFillColor(255, 230, 153); // Amarillo suave (#FFE699)
        $this->Cell($wDesc, 6.5, $this->u('DESCRIPCIÓN'), 1, 0, 'C', true);
        $this->Cell($wColor, 6.5, $this->u('COLOR'), 1, 0, 'C', true);
        $this->Cell($wCant, 6.5, $this->u('CANTIDAD'), 1, 0, 'C', true);
        $this->Cell($wUnit, 6.5, $this->u('UNITARIO'), 1, 0, 'C', true);
        $this->SetFillColor(248, 203, 173); // Durazno (#F8CBAD)
        $this->Cell($wValor, 6.5, $this->u('VALOR'), 1, 1, 'C', true);

        // Filas de Ítems
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(20, 20, 20);

        $rowCount = 0;
        foreach ($this->items as $item) {
            $rowCount++;
            $this->Cell($wDesc, 7, $this->u(' ' . $item['producto']), 1, 0, 'L');
            $this->Cell($wColor, 7, $this->u($item['color'] ?? ''), 1, 0, 'C');
            $this->Cell($wCant, 7, number_format((int)$item['cantidad'], 0, '', '.'), 1, 0, 'C');
            $this->Cell($wUnit, 7, $this->money($item['precio_unitario']) . ' ', 1, 0, 'R');
            $this->Cell($wValor, 7, $this->money($item['total_linea']) . ' ', 1, 1, 'R');
        }

        // Rellenar filas vacías para mantener el formato cuadriculado elegante (mínimo 6 filas como en la imagen)
        $filasMinimas = 6;
        for ($i = $rowCount; $i < $filasMinimas; $i++) {
            $this->Cell($wDesc, 7, '', 1, 0, 'L');
            $this->Cell($wColor, 7, '', 1, 0, 'C');
            $this->Cell($wCant, 7, '', 1, 0, 'C');
            $this->Cell($wUnit, 7, '', 1, 0, 'R');
            $this->Cell($wValor, 7, '', 1, 1, 'R');
        }

        $this->Ln(3);

        // 4. TABLA DE TOTALES (Alineada a la derecha)
        $wTotLabel = 28;
        $wTotVal   = 40;
        $xTot = 15 + (180 - ($wTotLabel + $wTotVal)); // 15 + 112 = 127

        // TOTAL NETO
        $this->SetX($xTot);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($wTotLabel, 6.5, $this->u('TOTAL'), 1, 0, 'R');
        $this->SetFont('Arial', 'B', 9.5);
        $this->Cell($wTotVal, 6.5, $this->money($this->cotizacion['subtotal']) . ' ', 1, 1, 'R');

        // IVA 19%
        $this->SetX($xTot);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($wTotLabel, 6.5, $this->u('IVA (19%)'), 1, 0, 'R');
        $this->SetFont('Arial', '', 9.5);
        $this->Cell($wTotVal, 6.5, $this->money($this->cotizacion['iva']) . ' ', 1, 1, 'R');

        // SALDO TOTAL / TOTAL CON IVA
        $this->SetX($xTot);
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(245, 245, 245);
        $this->Cell($wTotLabel, 7, $this->u('TOTAL C/IVA'), 1, 0, 'R', true);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($wTotVal, 7, $this->money($this->cotizacion['total']) . ' ', 1, 1, 'R', true);

        // ABONO (50%)
        $pctAbono = (int)($this->cotizacion['porcentaje_abono'] ?? 50);
        $this->SetX($xTot);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(30, 110, 30);
        $this->Cell($wTotLabel, 6.5, $this->u("ABONO ({$pctAbono}%)"), 1, 0, 'R');
        $this->SetFont('Arial', 'B', 9.5);
        $this->Cell($wTotVal, 6.5, $this->money($this->cotizacion['abono']) . ' ', 1, 1, 'R');

        // SALDO PENDIENTE (50%)
        $this->SetX($xTot);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(180, 40, 40);
        $this->Cell($wTotLabel, 6.5, $this->u('SALDO'), 1, 0, 'R');
        $this->SetFont('Arial', 'B', 9.5);
        $this->Cell($wTotVal, 6.5, $this->money($this->cotizacion['saldo']) . ' ', 1, 1, 'R');

        $this->SetTextColor(20, 20, 20);
        $this->Ln(6);

        // 5. CONDICIÓN Y POLÍTICA DE TALLER (Inferior Izquierda / Todo el ancho)
        $condicion = !empty($this->cotizacion['condiciones']) 
            ? $this->cotizacion['condiciones'] 
            : 'TALLER SOLICITA 50% CON LA CONFIRMACION Y 50% CON LA ENTREGA';

        $this->SetFont('Arial', 'B', 8.5);
        $this->SetTextColor(50, 50, 50);
        $this->Cell(180, 6, $this->u($condicion), 0, 1, 'L');
    }
}

<?php

namespace Drupal\ingresar_evento\Services;

use DateTime;

/**
 * Servicio para generar imágenes de calendario con fechas de eventos.
 */
class EventImageGenerator {

  /**
   * Ruta raíz de la aplicación Drupal.
   *
   * @var string
   */
  protected string $appRoot;

  /**
   * Constructor del servicio.
   */
  public function __construct(string $app_root) {
    $this->appRoot = $app_root;
  }

  /**
   * Genera la imagen del calendario recibiendo una fecha en formato Y-m-d (ej. 2026-11-26).
   *
   * @param string $dateString
   *   Fecha en formato 'Y-m-d'.
   * @param string $outputPath
   *   Ruta donde se guardará la imagen generada.
   *
   * @return bool
   *   TRUE si la generación fue exitosa.
   */
  public function generateImage(string $dateString, string $outputPath): bool {
    $date = DateTime::createFromFormat('Y-m-d', $dateString);
    if (!$date) {
      return FALSE;
    }

    // Traducir mes a español si es necesario
    $meses = [
      1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
      5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
      9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'
    ];
    
    // Traducción de días de la semana en español
    $diasSemana = [
      1 => 'Lunes',
      2 => 'Martes',
      3 => 'Miércoles',
      4 => 'Jueves',
      5 => 'Viernes',
      6 => 'Sábado',
      7 => 'Domingo',
    ];

    $monthText = $meses[(int)$date->format('n')] . ' ' . $date->format('Y');
    $dayNumberText = $date->format('d');
    $dayNameText = $diasSemana[(int) $date->format('N')]; // 1 (Lunes) a 7 (Domingo)

    // Cargar la imagen base de fondo
    $baseImagePath = $this->appRoot . '/modules/custom/ingresar_evento/img/calendario-evento-instituto.jpg';
    if (!file_exists($baseImagePath)) {
      return FALSE;
    }

    $image = imagecreatefromjpeg($baseImagePath);
    if (!$image) {
      return FALSE;
    }

    // Definición de Colores
    $whiteColor = imagecolorallocate($image, 255, 255, 255);
    $blueColor = imagecolorallocate($image, 15, 32, 67);//azul marino

    // Ruta a la fuente TrueType (.ttf)
    $fontPath = $this->appRoot . '/modules/custom/ingresar_evento/fonts/Roboto-Regular.ttf';

    // Renderizar el Mes y Año en la cabecera azul
    $fontSizeHeader = 18;
    $bboxHeader = imagettfbbox($fontSizeHeader, 0, $fontPath, $monthText);
    $textWidthHeader = abs($bboxHeader[2] - $bboxHeader[0]);
    $xHeader = (imagesx($image) - $textWidthHeader) / 2;
    $yHeader = 70;

    imagettftext($image, $fontSizeHeader, 0, (int)$xHeader, $yHeader, $whiteColor, $fontPath, $monthText);

    // Renderizar el Número del Día (destacado grande)
    $fontSizeDayNum = 75;
    $bboxDayNum = imagettfbbox($fontSizeDayNum, 0, $fontPath, $dayNumberText);
    $textWidthDayNum = abs($bboxDayNum[2] - $bboxDayNum[0]);
    $xDayNum = (imagesx($image) - $textWidthDayNum) / 2;
    $yDayNum = 185;

    imagettftext($image, $fontSizeDayNum, 0, (int) $xDayNum, $yDayNum, $blueColor, $fontPath, $dayNumberText);

    // Renderizar el Nombre del Día de la semana
    $fontSizeDayName = 18;
    $bboxDayName = imagettfbbox($fontSizeDayName, 0, $fontPath, $dayNameText);
    $textWidthDayName = abs($bboxDayName[2] - $bboxDayName[0]);
    $xDayName = (imagesx($image) - $textWidthDayName) / 2;
    $yDayName = 215;

    imagettftext($image, $fontSizeDayName, 0, (int) $xDayName, $yDayName, $blueColor, $fontPath, $dayNameText);

    // Guardar la imagen procesada
    $result = imagejpeg($image, $outputPath, 90);
    imagedestroy($image);

    return $result;
  }

}

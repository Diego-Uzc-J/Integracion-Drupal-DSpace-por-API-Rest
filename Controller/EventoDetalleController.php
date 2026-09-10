<?php

namespace Drupal\ingresar_evento\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventoDetalleController extends ControllerBase {

  protected Connection $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database')
    );
  }

  public function detalle(int $evento_id): array {

    $evento = $this->database->select('ingresar_evento_registros', 'e')
      ->fields('e')
      ->condition('id', $evento_id)
      ->execute()
      ->fetchObject();

    if (!$evento) {
      throw new NotFoundHttpException();
    }

    $build = [];
    
    $realPath_thumbnails_evento = NULL;
    if(isset( $evento->thumbnails_evento )) {
        $destination_thumbnails_evento = 'public://thumbnails_eventos/' . $evento->thumbnails_evento;
        $imagen_url = \Drupal::service('file_url_generator')->generateString( $destination_thumbnails_evento );
    }

    $build['detalle'] = [
      '#theme' => 'ingresar_evento_detalle',
      '#id' => $evento->id,
      '#titulo' => $evento->titulo,
      '#autor' => $evento->autor,
      '#fecha' => $evento->fecha,
      '#descripcion' => $evento->descripcion,
      '#palabras_clave' => $evento->palabras_clave,
      '#adjunto_fid' => $evento->adjunto_fid,
      '#estado' => $evento->estado ?? 'Pendiente',
      '#dspace_uuid' => $evento->dspace_uuid ?? 'Por asignar',
      '#dspace_handle' => $evento->dspace_handle ?? 'Por asignar',
      '#thumbnails_evento' => $imagen_url,
    ];

    return $build;
  }

}

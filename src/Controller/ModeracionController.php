<?php

namespace Drupal\ingresar_evento\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ingresar_evento\Services\DSpaceConnectorService;

class ModeracionController extends ControllerBase {

  protected DSpaceConnectorService $dspaceConnector;

  public function __construct(DSpaceConnectorService $dspace_connector) {
    $this->dspaceConnector = $dspace_connector;
    
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ingresar_evento.dspace_connector')
    );
  }
    
  public function listar() {
    
    $connection = Database::getConnection();
    $query = $connection->select('ingresar_evento_registros', 'r')
      ->fields('r')
      ->orderBy('id', 'DESC')
      ->execute();

    $rows = [];
    foreach ($query as $registro) {
      $clase_estado = ($registro->estado == 'Pendiente') ? 'color-warning' : 'color-success';
      $estado_markup = [
        '#markup' => '<span class="badge-status ' . $clase_estado . '">' . Html::escape($registro->estado) . '</span>',
      ];

      $uuid_display = 'N/A';
      if (!empty($registro->dspace_uuid)) {
        $uuid_display = [
          '#markup' => '<code title="' . Html::escape($registro->dspace_uuid) . '">' . Html::escape(substr($registro->dspace_uuid, 0, 8)) . '...</code>',
        ];
      }

      $handle_display = 'N/A';
      if (!empty($registro->dspace_handle) && $registro->dspace_handle !== 'En revisión de Workflow') {
        $handle_display = [
          '#type' => 'link',
          '#title' => $registro->dspace_handle,
          '#url' => Url::fromUri('https://hdl.handle.net/' . $registro->dspace_handle),
          '#attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer'],
        ];
      } elseif ($registro->dspace_handle === 'En revisión de Workflow') {
        $handle_display = [
          '#markup' => '<em>' . $this->t('En revisión') . '</em>',
        ];
      }

      $links_acciones = [];
      $links_acciones['detalle'] = [
        'title' => $this->t('Ver detalle'),
        'url' => Url::fromRoute('ingresar_evento.detalle', ['evento_id' => $registro->id]),
      ];

      if ($registro->estado == 'Pendiente') {
        $token = \Drupal::getContainer()->get('csrf_token')->get('admin/dspace-moderacion/enviar/' . $registro->id);
        $links_acciones['enviar'] = [
          'title' => $this->t('Enviar a DSpace'),
          'url' => Url::fromRoute('ingresar_evento.enviar_dspace', ['id' => $registro->id], [
            'query' => ['token' => $token],
          ]),
        ];
      }

      $dropbutton = [
        'data' => [
          '#type' => 'dropbutton',
          '#links' => $links_acciones,
        ],
      ];

      $rows[] = [
        $registro->id,
        $registro->titulo,
        $registro->autor,
        ['data' => $estado_markup],
        ['data' => $uuid_display],
        ['data' => $handle_display],
        $dropbutton,
      ];
    }

    $header = ['ID', 'Título', 'Autor', 'Estado', 'DSpace UUID', 'DSpace Handle', 'Acciones'];

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No hay registros en la base de datos.'),
      '#attached' => [
        'library' => [
          'ingresar_evento/moderacion_list',
        ],
      ],
    ];
  }

  public function enviarADSpace($id) {
    // Delegamos toda la lógica pesada al servicio
    $this->dspaceConnector->enviarRegistro($id);

    return new RedirectResponse(Url::fromRoute('ingresar_evento.moderacion')->toString());
  }
  
}

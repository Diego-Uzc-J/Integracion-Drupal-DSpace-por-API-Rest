<?php

namespace Drupal\ingresar_evento\Services;

use Drupal\Core\Database\Database;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Log\LoggerInterface;
use Drupal\Core\Site\Settings;

class DSpaceConnectorService {

  use StringTranslationTrait;

  protected ClientInterface $httpClient;
  protected FileSystemInterface $fileSystem;
  protected LoggerInterface $logger;
  protected MessengerInterface $messenger;

  public function __construct(
    ClientInterface $http_client,
    FileSystemInterface $file_system,
    LoggerInterface $logger,
    MessengerInterface $messenger,
    TranslationInterface $string_translation
  ) {
    $this->httpClient = $http_client;
    $this->fileSystem = $file_system;
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Envía un registro pendiente a DSpace.
   *
   * @param int $id
   *   ID del registro en la base de datos local.
   *
   * @return bool
   *   TRUE si se envió correctamente, FALSE en caso contrario.
   */
  public function enviarRegistro(int $id): bool {
    $connection = Database::getConnection();
    $registro = $connection->select('ingresar_evento_registros', 'r')
      ->fields('r')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$registro || $registro->estado == 'Enviado') {
      $this->messenger->addError($this->t('Registro no válido o ya enviado.'));
      return FALSE;
    }

    // CONFIGURACIÓN DE PARÁMETROS DSPACE 9 (por omisión)
    //~ $dspace_base_url  = 'http://demodspace9x.localhost/server/api'; 
    //~ $dspace_user     = 'integra-dr-ds@demodspace9x.localhost';
    //~ $dspace_password = 'I&2F16X!.1cE';
    //~ $collection_uuid = 'ae4f941b-5317-1344-8039-12ba7abd46e0';
    
    // Obtenemos los valores definidos en settings.php
    $dspace_base_url = Settings::get('dspace_api_base_url', 'https://demodspace9x.localhost/server/api');
    $dspace_user = Settings::get('dspace_api_user');
    $dspace_password = Settings::get('dspace_api_password');
    $collection_uuid = Settings::get('dspace_api_collection', 'ae4f941b-5317-1344-8039-12ba7abd46e0');

    // Validación básica por si olvidaron configurarlo
    if (empty($user) || empty($password)) {
      throw new \Exception('Las credenciales de DSpace no están configuradas en settings.php.');
    }

    try {
      $cookieJar = new CookieJar();
      $client = $this->httpClient;

      // PASO 0: Handshake inicial para CSRF
      $init_response = $client->get($dspace_base_url . '/authn/status', [
        'cookies' => $cookieJar,
        'headers' => ['Accept' => 'application/json'],
        'http_errors' => false,
      ]);

      $csrf_token = '';
      foreach ($cookieJar->toArray() as $cookie) {
        if (strtoupper($cookie['Name']) === 'XSRF-TOKEN') {
          $csrf_token = $cookie['Value'];
          break;
        }
      }
      if (empty($csrf_token) && $init_response->hasHeader('DSPACE-XSRF-TOKEN')) {
        $csrf_token = $init_response->getHeaderLine('DSPACE-XSRF-TOKEN');
      }

      // PASO 1: Login Dinámico
      $login_response = $client->post($dspace_base_url . '/authn/login', [
        'cookies' => $cookieJar,
        'headers' => ['X-XSRF-TOKEN' => $csrf_token],
        'form_params' => [
          'user'     => $dspace_user,
          'password' => $dspace_password,
        ],
      ]);

      if (!$login_response->hasHeader('Authorization')) {
        throw new \Exception($this->t('No se recibió el token de autorización (JWT) tras el login.'));
      }
      $bearer_token = $login_response->getHeaderLine('Authorization');

      $nuevo_csrf_token = $login_response->getHeaderLine('DSPACE-XSRF-TOKEN');
      if (empty($nuevo_csrf_token)) {
        foreach ($cookieJar->toArray() as $cookie) {
          if (strtoupper($cookie['Name']) === 'XSRF-TOKEN') {
            $nuevo_csrf_token = $cookie['Value'];
            break;
          }
        }
      }
      if (empty($nuevo_csrf_token)) {
        $nuevo_csrf_token = $csrf_token;
      }
                  
      // PASO 2: Crear Workspace Item vacío
      $workspace_response = $client->post($dspace_base_url . '/submission/workspaceitems?owningCollection=' . $collection_uuid, [
        'cookies' => $cookieJar,
        'headers' => [
          'Authorization' => $bearer_token,
          'X-XSRF-TOKEN'  => $nuevo_csrf_token,
          'Content-Type'  => 'application/json',
          'Accept'        => 'application/hal+json',
        ],
        'json' => (object)[],
      ]);

      if ($workspace_response->getStatusCode() != 201) {
        throw new \Exception($this->t('No se pudo inicializar el Workspace Item en DSpace.'));
      }
      
      $workspace_data = json_decode($workspace_response->getBody()->getContents(), TRUE);
      $workspace_id   = $workspace_data['id'] ?? '';
      
      // PASO 3: Inyectar Metadatos
      $patch_body = [
        [
          'op'    => 'add',
          'path'  => '/sections/formularioMonografiasPageOne/dc.title',
          'value' => [['value' => $registro->titulo, 'language' => 'es', 'authority' => null, 'confidence' => -1, 'display' => $registro->titulo]]
        ],
        [
          'op'    => 'add',
          'path'  => '/sections/formularioMonografiasPageOne/dc.contributor.author',
          'value' => [['value' => $registro->autor, 'language' => null, 'authority' => null, 'confidence' => -1, 'display' => $registro->autor]]
        ],
        [
          'op'    => 'add',
          'path'  => '/sections/formularioMonografiasPageOne/dc.date.issued',
          'value' => [['value' => $registro->fecha, 'language' => null, 'authority' => null, 'confidence' => -1, 'display' => $registro->fecha]]
        ],
        [
          'op'    => 'add',
          'path'  => '/sections/formularioMonografiasPageOne/dc.description.abstract',
          'value' => [['value' => $registro->descripcion, 'language' => 'es', 'authority' => null, 'confidence' => -1, 'display' => $registro->descripcion]]
        ],
        [
          'op'    => 'add',
          'path'  => '/sections/formularioMonografiasPageOne/dc.type.media',
          'value' => [['value' => 'Texto', 'language' => 'es', 'authority' => null, 'confidence' => -1, 'display' => 'Texto']]
        ],
        [
          'op'    => 'add',
          'path'  => '/sections/formularioMonografiasPageOne/dc.subject.tipo',
          'value' => [['value' => 'Eventos', 'language' => 'es', 'authority' => null, 'confidence' => -1, 'display' => 'Eventos']]
        ],
        [
          'op'    => 'add',
          'path'  => '/sections/formularioMonografiasPageOne/dc.type',
          'value' => [['value' => 'event', 'language' => 'es', 'authority' => null, 'confidence' => -1, 'display' => 'Evento']]
        ],
        [
          'op'    => 'add',
          'path'  => '/sections/formularioMonografiasPageTwo/dc.subject',
          'value' => [['value' => $registro->palabras_clave, 'language' => 'es', 'authority' => null, 'confidence' => -1, 'display' => $registro->palabras_clave]]
        ],
        [
          'op'    => 'add',
          'path'  => '/sections/formularioMonografiasPageTwo/dc.rights',
          'value' => [['value' => 'info:eu-repo/semantics/openAccess', 'language' => null, 'authority' => null, 'confidence' => -1, 'display' => 'OpenAccess']]
        ],
        [
          'op'    => 'add',
          'path'  => '/sections/formularioMonografiasPageTwo/dc.rights.license',
          'value' => [['value' => 'http://creativecommons.org/licenses/by-nc-sa/3.0/ve/', 'language' => null, 'authority' => null, 'confidence' => -1, 'display' => 'CC BY']]
        ],
        [
          'op'    => 'add',
          'path'  => '/sections/license/granted',
          'value' => true
        ]
      ];

      $client->patch($dspace_base_url . '/submission/workspaceitems/' . $workspace_id, [
        'cookies' => $cookieJar,
        'headers' => [
          'Authorization' => $bearer_token,
          'X-XSRF-TOKEN'  => $nuevo_csrf_token,
          'Content-Type'  => 'application/json-patch+json',
          'Accept'        => 'application/json',
        ],
        'json' => $patch_body,
      ]);

      // Generar imagen mediante la API de servicios
      $generator = \Drupal::service('ingresar_evento.event_image_generator');
      $fechaActual = date("YmdHis");
      $thumbnails_evento_i = 'evento_' . $fechaActual . '.jpg';
      $destination = 'public://thumbnails_eventos/' . $thumbnails_evento_i;
      $realPath = $this->fileSystem->realpath($destination);

      $generator->generateImage('2026-11-26', $realPath);

      $uuid_file = null;
      if (file_exists($realPath)) {
        $multipart_data = [
          [
            'name'     => 'file',
            'contents' => fopen($realPath, 'r'),
            'filename' => 'fecha_evento.jpg',
            'headers'  => ['Content-Type' => 'image/jpeg']
          ]
        ];
     
        $bitstream_submission_url = rtrim($dspace_base_url, '/') . '/submission/workspaceitems/' . $workspace_id;
        
        $post_response = $client->post($bitstream_submission_url, [
          'cookies' => $cookieJar,
          'headers' => [
            'Authorization' => $bearer_token,
            'X-XSRF-TOKEN'  => $nuevo_csrf_token,
            'Accept'        => 'application/hal+json',
          ],
          'multipart' => $multipart_data,
        ]);
        
        $workspace_data = json_decode($post_response->getBody()->getContents(), TRUE);
        $uuid_file = $workspace_data['sections']['upload']['files'][0]['uuid'] ?? null;
      } else {
        $this->logger->warning($this->t('La imagen fija no se encontró en la ruta física: @path', ['@path' => $realPath]));
      }

      // PASO 5: Mover al Workflow Final de Publicación
      $workflow_url = $dspace_base_url . '/workflow/workflowitems';
      $uri_item = $dspace_base_url . '/submission/workspaceitems/' . $workspace_id;

      $workflow_response = $client->post($workflow_url, [
          'cookies' => $cookieJar,
          'headers' => [
              'Authorization' => $bearer_token,
              'X-XSRF-TOKEN'  => $nuevo_csrf_token,
              'Content-Type'  => 'text/uri-list',
              'Accept'        => 'application/hal+json',
          ],
          'body' => trim($uri_item) . "\r\n",
          'allow_redirects' => false,
      ]);

      $status_code = $workflow_response->getStatusCode();
      $item_uuid = null;
      $handle_asignado = 'Sin Handle';

      if (in_array($status_code, [200, 201]) && $uuid_file) {
          $item_endpoint_url = $dspace_base_url . '/core/bitstreams/' . $uuid_file . '/bundle?expand=parent&embed=item';

          $item_response = $client->get($item_endpoint_url, [
            'cookies' => $cookieJar,
            'headers' => [
              'Authorization' => $bearer_token,
              'Accept'        => 'application/json',
            ],
          ]);

          $item_data = \Drupal\Component\Serialization\Json::decode((string) $item_response->getBody()->getContents());
          $item_uuid = $item_data['_embedded']['item']['uuid'] ?? $item_data['id'] ?? null;
          $handle_asignado = $item_data['_embedded']['item']['handle'] ?? 'En revisión';
      }
  
      // Guardar éxito en base de datos local SQL
      $connection->update('ingresar_evento_registros')
        ->fields([
          'estado' => 'Enviado',
          'dspace_uuid' => $item_uuid,
          'dspace_handle' => $handle_asignado,
          'thumbnails_evento' => $thumbnails_evento_i,
        ])
        ->condition('id', $id)
        ->execute();
      
      $this->messenger->addMessage($this->t('El item ha sido enviado exitosamente a DSpace'));
      $this->messenger->addMessage($this->t('Ítem depositado. UUID: @uuid, Handle: @handle', [
        '@uuid' => $item_uuid,
        '@handle' => $handle_asignado
      ]));

      return TRUE;

    } catch (\Exception $e) {
      $this->messenger->addError($this->t('Error general en la integración: @message', ['@message' => $e->getMessage()]));
      return FALSE;
    }
  }

}

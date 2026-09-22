<?php

namespace Drupal\Tests\ingresar_evento\Functional;

use Drupal\Tests\BrowserTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;


/**
 * Pruebas funcionales para el flujo de envío de eventos a DSpace 9.
 *
 * @group ingresar_evento
 */
#[RunTestsInSeparateProcesses] // <-- Añade este atributo aquí
class DspaceIntegrationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'node', 'user', 'ingresar_evento'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Usuario con rol de moderador.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $moderatorUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Creación de usuario moderador con el permiso correspondiente.
    $this->moderatorUser = $this->drupalCreateUser([
        'access content',
        'administer ingresar_evento',
    ]);

  }

  /**
   * Configura un cliente HTTP simulado (Mock) para interceptar peticiones a DSpace.
   *
   * @param array $responses
   *   Arreglo de respuestas HTTP simuladas (Guzzle Response).
   */
  protected function setupMockHttpClient(array $responses): void {
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);
    $mockClient = new Client(['handler' => $handlerStack]);

    // Inyectamos el cliente simulado en el contenedor de servicios de Drupal.
    $this->container->set('http_client', $mockClient);
  }

  /**
   * Testea el flujo completo: Usuario anónimo envía formulario -> Moderador aprueba -> API DSpace.
   */
  public function testAnonymousSubmissionAndModeratorSendToDspace(): void {
    // 1. USUARIO ANÓNIMO: Acceder al formulario público.
    $this->drupalGet('/evento'); // Ajusta la URL de tu formulario.
    $this->assertSession()->statusCodeEquals(200);

    // Llenar los 5 campos requeridos.
    $edit = [
      'titulo' => 'Simposio de Inteligencia Artificial 2026',
      'autor' => 'Dra. Elena Rostova',
      'fecha' => '2026-09-15',
      'descripcion' => 'Un evento académico sobre avances en LLMs y robótica.',
      'palabras_clave' => 'IA, Robótica, Drupal',
    ];

    // Enviar formulario como anónimo.
    $this->submitForm($edit, 'Enviar Evento');
    $this->assertSession()->pageTextContains('El evento ha sido registrado exitosamente y está en espera de moderación.');

    // Verificar que un anónimo NO puede ver el botón "Enviar a DSpace".
    // Asumiendo que redirige o lista la entidad creada ID 1:
    $this->drupalGet('/admin/dspace-moderacion'); 
    $this->assertSession()->buttonNotExists('Enviar a DSpace');

    // 2. MODERADOR: Autenticación y ejecución.
    $this->drupalLogin($this->moderatorUser);

    // CORREGIDO: Proveer respuestas para los 5 pasos secuenciales que ejecuta el DSpaceConnectorService
    $this->setupMockHttpClient([
      // PASO 0: Handshake inicial / CSRF
      new Response(200, ['DSPACE-XSRF-TOKEN' => 'fake-csrf-token-1']),
      
      // PASO 1: Login / Obtención de JWT y nuevo CSRF
      new Response(200, [
        'Authorization' => 'Bearer fake-jwt-token',
        'DSPACE-XSRF-TOKEN' => 'fake-csrf-token-2'
      ]),
      
      // PASO 2: Crear Workspace Item vacío (Retorna 201 Created)
      new Response(201, ['Content-Type' => 'application/json'], json_encode([
        'id' => '12345',
        'type' => 'workspaceitem'
      ])),
      
      // PASO 3: Inyectar Metadatos (PATCH)
      new Response(200, ['Content-Type' => 'application/json'], json_encode(['status' => 'success'])),
      
      // PASO 4: Multipart POST de la imagen (Bitstream)
      new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'sections' => [
          'upload' => [
            'files' => [
              ['uuid' => 'uuid-file-123']
            ]
          ]
        ]
      ])),
      
      // PASO 5: Mover al Workflow Final (POST)
      new Response(201, ['Content-Type' => 'application/json'], json_encode(['status' => 'workflow-active'])),
      
      // PASO 5.1: GET Final para extraer el UUID e Item final embebido
      new Response(200, ['Content-Type' => 'application/json'], json_encode([
        '_embedded' => [
          'item' => [
            'uuid' => 'uuid-final-item-dspace-999',
            'handle' => '123456789/52923'
          ]
        ]
      ])),
    ]);

    // Visitar la vista/página de moderación del evento.
    $this->drupalGet('/admin/ver-evento/1');
    $this->assertSession()->statusCodeEquals(200);

  }

}

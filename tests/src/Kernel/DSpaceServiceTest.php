<?php

namespace Drupal\Tests\ingresar_evento\Kernel;

use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Pruebas Kernel para el servicio de integración con DSpace.
 *
 * @group ingresar_evento
 */
#[RunTestsInSeparateProcesses]
class DSpaceServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ingresar_evento'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Asegura la instalación de esquemas/configuraciones necesarias.
    $this->installConfig(['ingresar_evento']);
    // Instala el esquema de base de datos definido en ingresar_evento.install
    $this->installSchema('ingresar_evento', ['ingresar_evento_registros']);
  }

  /**
   * Evalúa la creación de un Item en DSpace mediante un Mock de Guzzle.
   */
  public function testCrearItemEnDSpace(): void {
    // 1. Crear el Mock de GuzzleHttp\ClientInterface
    $mockResponseBody = json_encode([
      'id' => '12345678-abcd-1234-abcd-123456789abc',
      'type' => 'item',
      'name' => 'Conferencia de Inteligencia Artificial',
    ]);

    $mockHttpClient = $this->createMock(ClientInterface::class);
    $mockHttpClient->method('request')
      ->willReturn(new Response(201, [], $mockResponseBody));

    // 2. Inyectar el mock en el contenedor del test
    $this->container->set('http_client', $mockHttpClient);

    // 3. Obtener el servicio registrado
    $dspaceService = $this->container->get('ingresar_evento.test_dspace_service');

    // 4. Ejecutar la prueba
    $datosEvento = [
      'titulo' => 'Conferencia de Inteligencia Artificial',
      'autor' => 'Ana Martínez',
      'fecha' => '2026-10-15',
      'descripcion' => 'Un evento sobre IA.',
      'palabras_clave' => 'IA, Tecnología',
    ];

    $resultado = $dspaceService->crearItem($datosEvento);

    $this->assertIsArray($resultado);
    $this->assertEquals('12345678-abcd-1234-abcd-123456789abc', $resultado['id']);
  }

  /**
   * Test para verificar que la inserción de registros en base de datos funciona.
   */
  public function testRegistroYLogInsercion() {
    $database = \Drupal::database();

    // Insertar un registro de prueba simulando el envío del formulario
    $id = $database->insert('ingresar_evento_registros')
      ->fields([
        'titulo' => 'Congreso de Inteligencia Artificial 2026',
        'autor' => 'Dr. Alan Turing',
        'descripcion' => 'Un evento sobre el futuro de los modelos LLM.',
        'fecha' => '2026-09-15',
        'palabras_clave' => 'LLM, Ciencia de Datos',
        'estado' => 'Pendiente',
        'dspace_uuid' => 'c34c971e-4c65-4c92-a591-e9cd460af0ee',
        'dspace_handle' => '123456789/54321',
      ])
      ->execute();

    $this->assertNotEmpty($id, 'El registro debería generar un ID autoincremental.');

    // Consultar el registro guardado
    $registro = $database->select('ingresar_evento_registros', 'e')
      ->fields('e')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();

    $this->assertEquals('Pendiente', $registro['estado']);
    $this->assertEquals('Dr. Alan Turing', $registro['autor']);
  }

}

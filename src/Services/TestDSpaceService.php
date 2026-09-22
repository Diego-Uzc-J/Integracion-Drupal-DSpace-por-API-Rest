<?php

namespace Drupal\ingresar_evento\Services;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Servicio de integración con la API REST de DSpace.
 */
class TestDSpaceService {

  /**
   * Cliente HTTP.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Fábrica de configuraciones.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor del servicio.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
  }

  /**
   * Crea un Item en DSpace.
   */
  public function crearItem(array $datosEvento): array {
    // Ejemplo de implementación simplificada
    $response = $this->httpClient->request('POST', 'https://dspace-api-url/api/core/items', [
      'json' => $datosEvento,
    ]);

    return json_decode($response->getBody()->getContents(), TRUE);
  }

}

<?php

namespace Drupal\ingresar_evento\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

class IngresarEventoForm extends FormBase {

  public function getFormId() {
    return 'ingresar_evento_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    // --- CAMPO HONEYPOT (Invisible para usuarios, para filtrar bots) ---
    $form['homepage_verification'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Dejar en blanco'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'style' => 'display:none !important;',
        'tabindex' => '-1',
        'autocomplete' => 'off',
      ],
    ];

    // --- CAMPOS DE DATOS DUBLIN CORE ---
    $form['titulo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título (dc.title)'),
      '#required' => TRUE,
    ];

    $form['autor'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Autor (dc.contributor.author)'),
      '#required' => TRUE,
    ];

    $form['fecha'] = [
      '#type' => 'date',
      '#title' => $this->t('Fecha de Publicación (dc.date.issued)'),
      '#required' => TRUE,
    ];

    $form['descripcion'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Descripción / Resumen (dc.description)'),
      '#required' => TRUE,
    ];

    $form['palabras_clave'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Palabras Clave separadas por coma (dc.subject)'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enviar Evento'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validar Honeypot: Si tiene contenido, es un bot automátizado
    if (!empty($form_state->getValue('homepage_verification'))) {
      $form_state->setErrorByName('homepage_verification', $this->t('Envío no permitido.'));
      return;
    }
    
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $connection = Database::getConnection();
    $connection->insert('ingresar_evento_registros')
      ->fields([
        'titulo' => $form_state->getValue('titulo'),
        'autor' => $form_state->getValue('autor'),
        'fecha' => $form_state->getValue('fecha'),
        'descripcion' => $form_state->getValue('descripcion'),
        'palabras_clave' => $form_state->getValue('palabras_clave'),
        'estado' => 'Pendiente',
      ])->execute();

    $this->messenger()->addMessage($this->t('El evento ha sido registrado exitosamente y está en espera de moderación.'));
    $form_state->setRedirect('<front>');
  }
}

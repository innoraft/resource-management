<?php

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;

class FormBlock extends BlockBase implements BlockPluginInterface {

/**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $form['form_block_project_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Project Name'),
    );

    $form['form_block_user_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('User Name'),
    );

    $form['form_block_time_duration'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Time Duration'),
    );

    $form['form_block_percentage_time'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Percentage of member\'s time'),
    );

    $form['form_block_non-billable'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Non-billable'),
    );

	$form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    );
    
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    drupal_set_message($this->t('@emp_name ,Your application is being submitted!', array('@emp_name' => $form_state->getValue('employee_name'))));
  }

}
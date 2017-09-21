<?php
/**
 * @file
 * Contains \Drupal\resource_management\Form\MemberDetailsForm.
 */
namespace Drupal\resource_management\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UserNameInputForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_name_input_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['user_name'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#title' => $this->t('User Name'),
      '#required' => 'true'
    );

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('user.info',['uId' => $form_state->getValue('user_name')]);
  }
}

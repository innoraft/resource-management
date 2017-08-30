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

class MemberDetailsForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'member_details_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['project_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Project Name'),
    );

    $form['lead'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#title' => $this->t('Lead'),
    );

    $form['member'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#title' => $this->t('Member'),
    );

    $form['user_name'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#cardinality' => '3',
      '#title' => $this->t('User Name'),
    );

    // $form['specification'] = array(
    //   // '#type' => 'checkbox_tree',
    //   '#type' => 'entity_autocomplete',
    //   '#target_type' => 'taxonomy_term',
    //   // '#vocabulary' => taxonomy_vocabulary_load(1),
    //   '#title' => $this->t('Specialization'),
    // );

    $form['time_duration'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Time Duration'),
    );

    $form['percentage_time'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Percentage of member\'s time'),
    );

    $form['non_billable'] = array(
      '#type' => 'checkbox',
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

  public function get_name($id){
    $user = \Drupal\user\Entity\User::load($id);
    $name = $user->getUsername();
    return $name;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $paragraph_lead_and_member = Paragraph::create([
      'type' => 'lead_and_member',
      'field_lead' => array('target_id' => $form_state->getValue('lead')),
      'field_member' => array('target_id' => $form_state->getValue('member'))
    ]);

    $paragraph_lead_and_member->save();

    $paragraph_member_details = Paragraph::create([
      'type' => 'member_details',
      'field_lead_and_member' => array(
        'target_id' => $paragraph_lead_and_member->id(),
        'target_revision_id' => $paragraph_lead_and_member->getRevisionId(),
      ),
      'field_total_billable' => 20,
      'field_total_non_billable' => 0,
      'field_user_name' => array('target_id' => $form_state->getValue('user_name')),
    ]);

    $paragraph_member_details->save();

    $nodeData = array(
      'type' => 'project',
      'status' => 1,
      'title' => $form_state->getValue('project_name'),
      'field_member_details' => array(
        'target_id' => $paragraph_member_details->id(),
        'target_revision_id' => $paragraph_member_details->getRevisionId(),
      ),
    );
    $entity = Node::create($nodeData);
    $entity->save();
    drupal_set_message("Success",'status');
  }


}
<?php
/**
 * @file
 * Contains \Drupal\form_block\Form\MemberDetailsForm.
 */
namespace Drupal\form_block\Form;

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

    $form['form_block_project_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Project Name'),
    );

    $form['form_block_user_name'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#cardinality' => '3',
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

    $form['form_block_non_billable'] = array(
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

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // $node = \Drupal\node\Entity\Node::create(array(
    //   'langcode' => 'en',
    //   'created' => REQUEST_TIME,
    //   'changed' => REQUEST_TIME,
    //   'title' => 'First Block',
      
    //   'field_tags' =>[2],
    //   'body' => [
    //     'summary' => '',
    //     'value' => '<p>'.$form_state->getValue('form_block_project_name').'</p>',
    //     'format' => 'full_html',
    //   ],
    // ));

    // $node->save();
    $paragraph_lead_and_member = Paragraph::create([
      'type' => 'lead_and_member',
      'field_lead' => array('value' => 'vivek', 'format' => 'full_html'),
      'field_member' => array('value' => 'vivek', 'format' => 'full_html'),
      'field_project_name' => array('value' => 'Project 1', 'format' => 'full_html'),
    ]);

    $paragraph_lead_and_member->save();

    $paragraph_member_details = Paragraph::create([
      'type' => 'member_details',
      'field_lead_and_member' => array(
        'target_id' => $paragraph_lead_and_member->id(),
        'target_revision_id' => $paragraph_lead_and_member->getRevisionId(),
      ),
      'field_spe' => array('target_id' => 3),
      'field_total_billable' => 20,
      'field_total_non_billable' => 0,
      'field_user_name' => $form_state->getValue('form_block_user_name'),
    ]);

    $paragraph_member_details->save();

    $nodeData = array(
      'type' => 'project',
      'status' => 1,
      'title' => 'First Node',
      'field_member_details' => array(
        'target_id' => $paragraph_member_details->id(),
        'target_revision_id' => $paragraph_member_details->getRevisionId(),
      ),
    );
    $entity = Node::create($nodeData);
    $entity->save();
    // dpm($form_state->getValues());
    drupal_set_message("Success",'status');
  }


}
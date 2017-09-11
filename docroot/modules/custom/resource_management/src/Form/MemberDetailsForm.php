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
  public function buildForm(array $form, FormStateInterface $form_state, $uId = NULL,$nid = NULL) {
    if(!is_null($nid)){
      $node = \Drupal\node\Entity\Node::load($nid);
      $paragraph = $node->get('field_member_details')->getValue();      
      $target_id = $paragraph[0]['target_id'];
      $paragraph_single = Paragraph::load($target_id);

      $total_billable = floatval($paragraph_single->get('field_total_billable')->getValue()[0]['value']);
      $total_non_billable = floatval($paragraph_single->get('field_total_non_billable')->getValue());
      $user_name = $paragraph_single->get('field_user_name')->getValue()[0]['target_id'];

      $leadAndMemberId = $paragraph_single->get('field_lead_and_member')->getValue()[0]['target_id'];
      $leadAndMemberPara = Paragraph::load($leadAndMemberId);
      $lead = '';
      $member = '';

      if(!empty($leadAndMemberPara->get('field_lead')->getValue())) {
        $lead = $leadAndMemberPara->get('field_lead')->getValue()[0]['target_id'];
      } else {
        $lead = 'No lead';
      }
      
      if(!empty($leadAndMemberPara->get('field_member')->getValue())) {
        $member = $leadAndMemberPara->get('field_member')->getValue()[0]['target_id'];  
      } else {
        $member = 'No member';
      }


      $entity =  \Drupal\user\Entity\User::loadMultiple(); // Load entity
      foreach ($entity as $key => $user) {
          if($user->id() === $lead){
            $lead = $user;
            break;
          }
      }

      foreach ($entity as $key => $user) {
        if($user->id() === $member){
          $member = $user;
          break;
        }
      }

      foreach ($entity as $key => $user) {
        if($user->id() === $user_name){
          $user_name = $user;
          break;
        }
      }
    }

    $form['project_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Project Name'),
      '#required' => 'true',
    );

    $form['lead'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#title' => $this->t('Lead'),
      '#required' => 'true',
    );

    $form['member'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#title' => $this->t('Member'),
      '#required' => 'true',
    );

    $form['user_name'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#cardinality' => '3',
      '#title' => $this->t('User Name'),
      '#required' => 'true',
    );

    $form['time_duration'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Time Duration'),
    );

    $form['percentage_time'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Percentage of member\'s time'),
    );

    $form['field_billable'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Billable'),
      '#required' => 'true',
    );

    $form['non_billable'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Non-billable'),
    );


    $form['nid'] = array(
      '#type' => 'value',
      '#value' => $nid,
    );

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    );


    if(!is_null($nid)){
      $form['project_name']['#default_value'] = $node->getTitle();
      $form['lead']['#default_value'] = $lead;
      $form['member']['#default_value'] = $member;
      $form['user_name']['#default_value'] = $user_name;
      // $form['time_duration']
      // $form['percentage_time']
      // $form['percentage_time']
      $form['field_billable']['#default_value'] = $total_billable;
      $form['non_billable']['#default_value'] = $total_non_billable;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, $uId = NULL,$nid = NULL) {
    if(!is_null($nid)){

      $node = \Drupal\node\Entity\Node::load($form_state->getValue('nid'));
      $node->setTitle($form_state->getValue('project_name'));


      $paragraph = $node->get('field_member_details')->getValue();      
      $target_id = $paragraph[0]['target_id'];

      $paragraph_member_details = Paragraph::load($target_id);
      $paragraph_member_details->set('field_total_billable',$form_state->getValue('field_billable'));
      $paragraph_member_details->set('field_total_non_billable',$form_state->getValue('non_billable'));
      $paragraph_member_details->set('field_user_name',array('target_id' => $form_state->getValue('user_name')));


      $leadAndMemberId = $paragraph_member_details->get('field_lead_and_member')->getValue()[0]['target_id'];

      $leadAndMemberPara = Paragraph::load($leadAndMemberId);
      $leadAndMemberPara->set('field_lead',array('target_id' => $form_state->getValue('lead')));
      $leadAndMemberPara->set('field_member',array('target_id'=>$form_state->getValue('member')));
      $leadAndMemberPara->save();

      $paragraph_member_details->save();

      $node->save();
      $form_state->setRedirect('user.info',['uId' => $form_state->getValue('user_name')]);
    }

    else{
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
        'field_total_billable' => $form_state->getValue('field_billable'),
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

      $form_state->setRedirect('user.info',['uId' => $form_state->getValue('user_name')]);
    }
  }
}
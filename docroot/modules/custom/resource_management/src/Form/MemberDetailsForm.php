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
  
    $form['#tree'] = TRUE;
    $form_state->setCached(FALSE);
    if(!is_null($nid)){
     
      $saved_node = \Drupal\node\Entity\Node::load($nid);
      $field_member_details = $saved_node->get('field_member_details')->getValue();
      $field_member_details = $field_member_details[0]['target_id'];
      $member_details_para = Paragraph::load($field_member_details);
      $paragraph_lead_and_member_array = $member_details_para->get('field_lead_and_member')->getValue();
      $billing_information_para_id_array = $member_details_para->get('field_billing_information')->getValue();
      $i = 0;
      $node = NULL;
      $node['title'] = $saved_node->getTitle();


      while(!empty($paragraph_lead_and_member_array[$i]) ){
          $j = 0;
          $lead_member_para = Paragraph::load($paragraph_lead_and_member_array[$i]['target_id']);
          $node['lead'][$i] = \Drupal\user\Entity\User::load($lead_member_para->get('field_lead')->getValue()[0]['target_id']);
          $member_array = $lead_member_para->get('field_member')->getValue();
          foreach ($member_array as $key => $member) {
              $node['member'][$i][$j] = \Drupal\user\Entity\User::load($member['target_id']);
              $j++;
          }
          $i++;
      }

      $i = 0;
      while(!empty($billing_information_para_id_array[$i])){
          $billing_information_para = Paragraph::load($billing_information_para_id_array[$i]['target_id']);
          $node['member_name'][$i] = \Drupal\user\Entity\User::load($billing_information_para->get('field_user_name')->getValue()[0]['target_id']);
          $node['total_billable'][$i] = $billing_information_para->get('field_billable')->getValue()[0]['value'];
          $node['total_non_billable'][$i] = $billing_information_para->get('field_non_billable')->getValue()[0]['value'];
          $i++;
      }

      $form['project_name'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Project Name'),
        '#required' => 'true',
      );
      $form['lead_member_fieldset'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Lead And Member Groups'),
        '#prefix' => '<div id="lead_member_fieldset_wrapper">',
        '#suffix' => '</div>'
      );

      $lead_member_count = $form_state->get('num_lead_member');
      $lead_member_fieldset_count = 0;
      if(empty($lead_member_count)){
        $lead_member_fieldset_count = count($paragraph_lead_and_member_array);
        $form_state->set('num_lead_member',$lead_member_fieldset_count);
      }
      if($form_state->get('num_lead_member') > 0){
        $lead_member_fieldset_count = $form_state->get('num_lead_member');
      }
      else{
        $lead_member_fieldset_count = 1;
      }

      for($j=0; $j<$lead_member_fieldset_count; $j++){
        $form['lead_member_fieldset']['group'][$j] = array(
          '#type' => 'fieldset',
          '#title' => $this->t('Lead And Member'),
          '#prefix' => '<div id="lead_member_fieldset_wrapper_'.$j.'">',
          '#suffix' => '</div>'
        );
        $form['lead_member_fieldset']['group'][$j]['lead'] = array(
          '#type' => 'entity_autocomplete',
          '#target_type' => 'user',
          '#title' => $this->t('Lead'),
          '#required' => 'true',
          '#default_value' => empty($node['lead'][$j]) ? '' : $node['lead'][$j],
        );
        $form['lead_member_fieldset']['group'][$j]['members_fieldset'] = array(
          '#type' => 'fieldset',
          '#title' => $this->t('Member names'),
          '#prefix' =>  '<div id="members_fieldset_wrapper_'.$j.'">',
          '#suffix' => '</div>',      
        );
        $member_count_prev = $form_state->get(['num_members',$j]);
        $member_count = 0;
        if(empty($member_count_prev) && !empty($node['member'][$j])){
          $member_count =  count($node['member'][$j]);
          $form_state->set(['num_members',$j],$member_count);
        }
        if($form_state->get(['num_members',$j]) > 0){
          $member_count = $form_state->get(['num_members',$j]);
        }
        else{
          $member_count = 1;
          $form_state->set(['num_members',$j],1);
        }
        for ($i=0; $i < $member_count; $i++) { 
          $form['lead_member_fieldset']['group'][$j]['members_fieldset']['name'][$i] = array(
              '#type' => 'entity_autocomplete',
              '#target_type' => 'user',
              '#title' => $this->t('Member'),
              '#required' => 'true',
              '#default_value' => empty($node['member'][$j][$i]) ? '' : $node['member'][$j][$i],
          );
        }
        $form['lead_member_fieldset']['group'][$j]['members_fieldset']['actions']['add_name'] = array(
          '#type' => 'submit',
          '#value' => t('Add more member'),
          '#name' => 'add-member-button-'.$j,
          '#submit' => array('::addOne'),
          '#ajax' => array(
            'callback' => '::addMoreCallback',
            'wrapper' => 'members_fieldset_wrapper_'.$j,
          ),
        );

        if($member_count > 1){
          $form['lead_member_fieldset']['group'][$j]['members_fieldset']['actions']['remove_name'] = array(
            '#type' => 'submit',
            '#value' => t('Remove One'),
            '#name' => 'remove-member-button-'.$j,
            '#submit' => array('::removeCallback'),
            '#ajax' => array(
              'callback' => '::addMoreCallback',
              'wrapper' => 'members_fieldset_wrapper_'.$j,
            ),
          );
        }
      }
      $form['lead_member_fieldset']['actions']['add_name'] = array(
        '#type' => 'submit',
        '#value' => t('Add more'),
        '#name' => 'add-lead-member-group-button-'.$j,
        '#submit' => array('::addOneLeadMemberFieldset'),
        '#ajax' => array(
          'callback' => '::addMoreCallbackLeadMemberFieldset',
          'wrapper' => 'lead_member_fieldset_wrapper'
        ),
      );

      if($lead_member_fieldset_count > 1){
        $form['lead_member_fieldset']['actions']['remove_name'] = array(
          '#type' => 'submit',
          '#value' => t('Remove One'),
          '#name' => 'remove-lead-member-group-button-'.$j,
          '#submit' => array('::removeCallbackLeadMemberFieldset'),
          '#ajax' => array(
            'callback' => '::addMoreCallbackLeadMemberFieldset',
            'wrapper' => 'lead_member_fieldset_wrapper',
          ),
        );
      }

      $billing_information_fieldset_count = count($billing_information_para_id_array);
      
      $billing_information_count = $form_state->get('num_users_bill_info');
      $billing_information_fieldset_count = 0;
      if(empty($billing_information_count)){
        $billing_information_fieldset_count = count($billing_information_para_id_array);
        $form_state->set('num_users_bill_info',$billing_information_fieldset_count);
      }
      if($form_state->get('num_users_bill_info') > 0){
        $billing_information_fieldset_count = $form_state->get('num_users_bill_info');
      }
      else{
        $billing_information_fieldset_count = 1;
      }

      $form['member_billing_information_fieldset'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Member Billing Information'),
        '#prefix' => '<div id="member_billing_information_fieldset_wrapper">',
        '#suffix' => '</div>'
      );

      for($j=0; $j<$billing_information_fieldset_count; $j++){
        
        $form['member_billing_information_fieldset']['group'][$j] = array(
          '#type' => 'fieldset',
          '#title' => $this->t('Member '.($j+1).''),
          '#prefix' => '<div id="member_billing_information_wrapper_'.$j.'">',
          '#suffix' => '</div>',
        );
        $form['member_billing_information_fieldset']['group'][$j]['member_name'] = array(
          '#type' => 'entity_autocomplete',
          '#target_type' => 'user',
          '#required' => 'true',
          '#title' => $this->t('Member Name'),
          '#default_value' => $node['member_name'][$j],
        );

        $form['member_billing_information_fieldset']['group'][$j]['billable'] = array(
          '#type' => 'textfield',
          '#title' => $this->t('Billable'),
          '#required' => 'true',
          '#field_suffix' => '%',
          '#default_value' => $node['total_billable'][$j],
        );

        $form['member_billing_information_fieldset']['group'][$j]['non_billable'] = array(
          '#type' => 'textfield',
          '#title' => $this->t('Non-Billable'),
          '#required' => 'true',
          '#field_suffix' => '%',
          '#default_value' => $node['total_non_billable'][$j],
        );
        
        $form['member_billing_information_fieldset']['group'][$j]['time_duration'] = array(
          '#type' => 'date',
          '#title' => $this->t('Time Duration'),
          // '#default_value' => $node
        );
      }

      $form['member_billing_information_fieldset']['actions']['add_info'] = array(
        '#type' => 'submit',
        '#value' => t('Add More Member Information'),
        '#submit' => array('::addOneBillingInformationFieldset'),
        '#ajax' => array(
          'callback' => '::addMoreCallbackBillingInformationFieldset',
          'wrapper' => 'member_billing_information_fieldset_wrapper',
        ),
      );

      if($billing_information_fieldset_count > 1){
        $form['member_billing_information_fieldset']['actions']['remove_info'] = array(
          '#type' => 'submit',
          '#value' => t('Remove Above Member Information'),
          '#submit' => array('::removeOneBillingInformationFieldset'),
          '#ajax' => array(
            'callback' => '::addMoreCallbackBillingInformationFieldset',
            'wrapper' => 'member_billing_information_fieldset_wrapper',
          ),
        );
      }

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
      );

      $form_state->set('nid',$nid);


      $form['project_name']['#default_value'] = $node['title'];
   
    }
    else{
      $form['project_name'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Project Name'),
        '#required' => 'true',
      );

      $form['lead_member_fieldset'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Lead And Member'),
        '#prefix' => '<div id="lead_member_fieldset_wrapper">',
        '#suffix' => '</div>'
      );

      $lead_member_count = $form_state->get('num_lead_member');
      if(empty($lead_member_count)){
        $lead_member_count = $form_state->set('num_lead_member',1);
      }
      if($form_state->get('num_lead_member') > 0){
        $lead_member_fieldset_count = $form_state->get('num_lead_member');
      }
      else{
        $lead_member_fieldset_count = 1;
      }


      for($j=0; $j<$lead_member_fieldset_count; $j++){
        $form['lead_member_fieldset']['group'][$j] = array(
          '#type' => 'fieldset',
          '#title' => $this->t('Lead And Member'),
          '#prefix' => '<div id="lead_member_fieldset_wrapper_'.$j.'">',
          '#suffix' => '</div>'
        );
        $form['lead_member_fieldset']['group'][$j]['lead'] = array(
          '#type' => 'entity_autocomplete',
          '#target_type' => 'user',
          '#title' => $this->t('Lead'),
          '#required' => 'true',
        );
        $form['lead_member_fieldset']['group'][$j]['members_fieldset'] = array(
          '#type' => 'fieldset',
          '#title' => $this->t('Member names'),
          '#prefix' =>  '<div id="members_fieldset_wrapper_'.$j.'">',
          '#suffix' => '</div>',      
        );

        $member_count = $form_state->get(['num_members',$j]);
        if($member_count == 0){
          $form_state->set(['num_members',$j],1);
          $member_count = 1;
        }

        for ($i=0; $i < $member_count; $i++) { 
          $form['lead_member_fieldset']['group'][$j]['members_fieldset']['name'][$i] = array(
              '#type' => 'entity_autocomplete',
              '#target_type' => 'user',
              '#title' => $this->t('Member'),
              '#required' => 'true',
          );
        }
        $form['lead_member_fieldset']['group'][$j]['members_fieldset']['actions']['add_name'] = array(
          '#type' => 'submit',
          '#name' => 'add-member-button-'.$j,
          '#value' => t('Add more member'),
          '#submit' => array('::addOne'),
          '#ajax' => array(
            'callback' => '::addMoreCallback',
            'wrapper' => 'members_fieldset_wrapper_'.$j,
          ),
        );

        if($member_count > 1){
          $form['lead_member_fieldset']['group'][$j]['members_fieldset']['actions']['remove_name'] = array(
            '#type' => 'submit',
            '#name' => 'remove-member-button-'.$j,
            '#value' => t('Remove One'),
            '#submit' => array('::removeCallback'),
            '#ajax' => array(
              'callback' => '::addMoreCallback',
              'wrapper' => 'members_fieldset_wrapper_'.$j,
            ),
          );
        }
      }
      $form['lead_member_fieldset']['actions']['add_name'] = array(
        '#type' => 'submit',
        '#name' => 'add-lead-member-group-button-'.$j,
        '#value' => t('Add more'),
        '#submit' => array('::addOneLeadMemberFieldset'),
        '#ajax' => array(
          'callback' => '::addMoreCallbackLeadMemberFieldset',
          'wrapper' => 'lead_member_fieldset_wrapper'
        ),
      );

      if($lead_member_fieldset_count > 1){
        $form['lead_member_fieldset']['actions']['remove_name'] = array(
          '#type' => 'submit',
          '#name' => 'remove-lead-member-group-button-'.$j,
          '#value' => t('Remove One'),
          '#submit' => array('::removeCallbackLeadMemberFieldset'),
          '#ajax' => array(
            'callback' => '::addMoreCallbackLeadMemberFieldset',
            'wrapper' => 'lead_member_fieldset_wrapper',
          ),
        );
      }


      $form['member_billing_information_fieldset'] = array(
        '#type' => 'fieldset',
        '#title' => $this->t('Billing Information'),
        '#prefix' => '<div id="member_billing_information_fieldset_wrapper">',
        '#suffix' => '</div>'
      );

      $billing_information_fieldset_count = 0;
      $billing_information_count = $form_state->get('num_users_bill_info');
      if(empty($billing_information_count)){
        $billing_information_count = $form_state->set('num_users_bill_info',1);
      }
      if($form_state->get('num_users_bill_info') > 0){
        $billing_information_fieldset_count = $form_state->get('num_users_bill_info');
      }
      else{
        $billing_information_fieldset_count = 1;
      }

      
      for($j=0; $j<$billing_information_fieldset_count; $j++){
        
        $form['member_billing_information_fieldset']['group'][$j] = array(
          '#type' => 'fieldset',
          '#title' => $this->t('Member Billing Information'),
          '#prefix' => '<div id="member_billing_information_wrapper_'.$j.'">',
          '#suffix' => '</div>'
        );
        $form['member_billing_information_fieldset']['group'][$j]['member_name'] = array(
          '#type' => 'entity_autocomplete',
          '#target_type' => 'user',
          '#required' => 'true',
          '#title' => $this->t('Member Name'),
        );

        $form['member_billing_information_fieldset']['group'][$j]['billable'] = array(
          '#type' => 'textfield',
          '#title' => $this->t('Billable'),
          '#required' => 'true',
          '#field_suffix' => '%',
        );

        $form['member_billing_information_fieldset']['group'][$j]['non_billable'] = array(
          '#type' => 'textfield',
          '#title' => $this->t('Non-Billable'),
          '#required' => 'true',
          '#field_suffix' => '%',
        );
        
        $form['member_billing_information_fieldset']['group'][$j]['time_duration'] = array(
          '#type' => 'date',
          '#title' => $this->t('Time Duration'),
        );
      }

      $form['member_billing_information_fieldset']['actions']['add_info'] = array(
        '#type' => 'submit',
        '#value' => t('Add More Member Information'),
        '#submit' => array('::addOneBillingInformationFieldset'),
        '#ajax' => array(
          'callback' => '::addMoreCallbackBillingInformationFieldset',
          'wrapper' => 'member_billing_information_fieldset_wrapper',
        ),
      );

      if($billing_information_fieldset_count > 1){
        $form['member_billing_information_fieldset']['actions']['remove_info'] = array(
          '#type' => 'submit',
          '#value' => t('Remove Above Member Information'),
          '#submit' => array('::removeOneBillingInformationFieldset'),
          '#ajax' => array(
            'callback' => '::addMoreCallbackBillingInformationFieldset',
            'wrapper' => 'member_billing_information_fieldset_wrapper',
          ),
        );
      }

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
      );
    }
    $form_state->set('nid',$nid);
    $form_state->set('uid',$uId);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nid = $form_state->get('nid');
    $lead_member_fieldset_group = $form_state->getValue('lead_member_fieldset')['group'];
    $lead_member_fieldset_group_count = count($lead_member_fieldset_group);
    $lead = array();
    $member = array();
    foreach ($lead_member_fieldset_group as $key => $group) {
      $lead[] = $group['lead'];
      $member[$key] = array();
      foreach ($group['members_fieldset']['name'] as $key1 => $member_name) {
          $member[$key][$key1]['target_id'] = $member_name;
      }
    }


    $member_billing_information_fieldset_group = $form_state->getValue('member_billing_information_fieldset')['group'];
    $member_billing_information_fieldset_group_count = count($member_billing_information_fieldset_group);
    $i = 0;
    foreach ($member_billing_information_fieldset_group as $key => $group) {
        $member_bill_info[$i]['name'] = $group['member_name'];
        $member_bill_info[$i]['billable'] = $group['billable'];
        $member_bill_info[$i]['non_billable'] = $group['non_billable'];
        $member_bill_info[$i]['time_duration'] = $group['time_duration'];
        $i++;
    }

    if(!is_null($nid)){

      $node = \Drupal\node\Entity\Node::load($nid);
      $node->setTitle($form_state->getValue('project_name'));

      $paragraph = $node->get('field_member_details')->getValue();      
      $target_id = $paragraph[0]['target_id'];

      $paragraph_member_details = Paragraph::load($target_id);
      $paragraph_lead_and_member_array = $paragraph_member_details->get('field_lead_and_member')->getValue();
      $paragraph_billing_information = $paragraph_member_details->get('field_billing_information')->getValue();
      $i = 0;

      foreach ($paragraph_member_details->get('field_lead_and_member') as $key => $field) {
          $leadAndMemberId = $field->getValue()['target_id'];
          $leadAndMemberPara = Paragraph::load($leadAndMemberId);
          $leadAndMemberPara->set('field_lead',array('target_id'=>$lead[$i]));  
          $leadAndMemberPara->set('field_member',$member[$i]);
          $leadAndMemberPara->save();
          $paragraph_lead_and_member_array[$i] = array();
          $paragraph_lead_and_member_array[$i]['target_id'] = $field->getValue()['target_id'];
          $paragraph_lead_and_member_array[$i]['target_revision_id'] = $field->getValue()['target_revision_id'];
          $i++;
      }

      
      while(!empty($lead[$i])){
        $paragraph = Paragraph::create([
          'type' => 'lead_and_member',
          'field_lead' => array('target_id' => $lead[$i]),
          'field_member' => $member[$i]
        ]);
        $paragraph->save();        
        $paragraph_lead_and_member_array[$i] = array();
        $paragraph_lead_and_member_array[$i]['target_id'] = $paragraph->id();
        $paragraph_lead_and_member_array[$i]['target_revision_id'] = $paragraph->getRevisionId();
        $i++;
      }
      $paragraph_member_details->set('field_lead_and_member',$paragraph_lead_and_member_array);

      $paragraph_billing_information_array = array();
      $i = 0;
      foreach ($paragraph_member_details->get('field_billing_information') as $key => $field) {
          $paragraph_id = $field->getValue()['target_id'];
          $billing_information_para = Paragraph::load($paragraph_id);
          $billing_information_para->set('field_user_name',$member_bill_info[$i]['name']);
          $billing_information_para->set('field_billable',$member_bill_info[$i]['billable']);
          $billing_information_para->set('field_non_billable',$member_bill_info[$i]['non_billable']);
          $billing_information_para->set('field_time_duration',$member_bill_info[$i]['time_duration']);
          $billing_information_para->save();
          $paragraph_billing_information_array[$i] = array();
          $paragraph_billing_information_array[$i]['target_id'] = $field->getValue()['target_id'];
          $paragraph_billing_information_array[$i]['target_revision_id'] = $field->getValue()['target_revision_id'];
          $i++;
      }

      while(!empty($member_bill_info[$i])){
        $paragraph = Paragraph::create([
          'type' => 'billing_information',
          'field_billable' => $member_bill_info[$i]['billable'],
          'field_non_billable' => $member_bill_info[$i]['non_billable'],
          'field_time_duration' => $member_bill_info[$i]['time_duration'],
          'field_user_name' => $member_bill_info[$i]['name'],
        ]);
        $paragraph->save();        
        $paragraph_billing_information_array[$i] = array();
        $paragraph_billing_information_array[$i]['target_id'] = $paragraph->id();
        $paragraph_billing_information_array[$i]['target_revision_id'] = $paragraph->getRevisionId();
        $i++;
      }
      $paragraph_member_details->set('field_lead_and_member',$paragraph_lead_and_member_array);
      $paragraph_member_details->set('field_billing_information',$paragraph_billing_information_array);


      $paragraph_member_details->save();

      $node->save();
      // kint($form_state->getValue('uid'));
      $form_state->setRedirect('user.info',['uId' => $form_state->get('uid')]);
    }

    else{
      $paragraph_lead_and_member = array();

      for ($i=0; $i < $lead_member_fieldset_group_count; $i++) { 
        $paragraph_lead_and_member[$i] = Paragraph::create([
          'type' => 'lead_and_member',
          'field_lead' => array('target_id' => $lead[$i]),
          'field_member' => $member[$i],
        ]);
        $paragraph_lead_and_member[$i]->save();
      }

      $paragraph_lead_and_member_array = array();
      foreach ($paragraph_lead_and_member as $key => $value) {
        $paragraph_lead_and_member_array[$key]['target_id'] = $paragraph_lead_and_member[$key]->id();
        $paragraph_lead_and_member_array[$key]['target_revision_id'] = $paragraph_lead_and_member[$key]->getRevisionId();
      }

      $paragraph_member_billing_information = array();

      for($i=0; $i<$member_billing_information_fieldset_group_count; $i++){
        $paragraph_member_billing_information[$i] = Paragraph::create([
                  'type' => 'billing_information',
                  'field_billable' => $member_bill_info[$i]['billable'],
                  'field_non_billable' => $member_bill_info[$i]['non_billable'],
                  'field_time_duration' => $member_bill_info[$i]['time_duration'],
                  'field_user_name' => $member_bill_info[$i]['name'],
                ]);
        $paragraph_member_billing_information[$i]->save(); 
      }

      $paragraph_member_billing_information_array = array();
      foreach ($paragraph_member_billing_information as $key => $value) {
        $paragraph_member_billing_information_array[$key]['target_id'] = $paragraph_member_billing_information[$key]->id();
        $paragraph_member_billing_information_array[$key]['target_revision_id'] = $paragraph_member_billing_information[$key]->getRevisionId();
      }

      $paragraph_member_details = Paragraph::create([
              'type' => 'member_details',
              'field_lead_and_member' => $paragraph_lead_and_member_array,
              'field_billing_information' => $paragraph_member_billing_information_array,
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

      $form_state->setRedirect('user.info',['uId' => $form_state->get('uid')]);
    }
  }

  public function addOne(array &$form, FormStateInterface $form_state) {
    $triggeringElement = $form_state->getTriggeringElement()['#name'];
    $triggeringElement = explode('-',$triggeringElement);
    $j = $triggeringElement[3]; //id of triggering element
    $members_count = $form_state->get(['num_members',$j]);
    // \Drupal::logger('resource_management')->notice('@members',array('@members'=>$members_count));
    $add_button = $members_count+1;
    $form_state->set(['num_members',$j],$add_button);
    $form_state->setRebuild();
  }

  public function addMoreCallback(array &$form, FormStateInterface $form_state) {
    $triggeringElement = $form_state->getTriggeringElement()['#name'];
    $triggeringElement = explode('-',$triggeringElement);
    $j = $triggeringElement[3]; //id of triggering element
    
    return $form['lead_member_fieldset']['group'][$j]['members_fieldset'];
  }

  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $triggeringElement = $form_state->getTriggeringElement()['#id'];
    $triggeringElement = explode('-',$triggeringElement);
    $j = $triggeringElement[5]; //id of triggering element
    $members_count = $form_state->get(['num_members',$j]);

    if($members_count > 1){
      $remove_button =  $members_count - 1;
      $form_state->set(['num_members',$j],$remove_button);
    }
    $form_state->setRebuild();
  }


////// For lead and member

  public function addOneLeadMemberFieldset(array &$form, FormStateInterface $form_state) {
    $lead_members_count = $form_state->get('num_lead_member');
    $add_button = $lead_members_count+1;
    $form_state->set('num_lead_member',$add_button);
    $triggeringElement = $form_state->getTriggeringElement()['#name'];
    $triggeringElement = explode('-',$triggeringElement);
    $j = $triggeringElement[5]; //id of triggering element
    $form_state->set(['num_members',($j+1)],1);
    $form_state->setRebuild();
  }

  public function addMoreCallbackLeadMemberFieldset(array &$form, FormStateInterface $form_state) {
    $lead_members_count = $form_state->get('num_lead_member');
    return $form['lead_member_fieldset'];
  }

  public function removeCallbackLeadMemberFieldset(array &$form, FormStateInterface $form_state) {
    $lead_members_count = $form_state->get('num_lead_member');
    if($members_count > 1){
      $remove_button =  $lead_members_count - 1;
      $form_state->set('num_lead_member',$remove_button);
    }
    $form_state->setRebuild();
  }
//////

// ##  For billing Information   ##

  public function addOneBillingInformationFieldset(array &$form, FormStateInterface $form_state) {
    $users_bill_info_count = $form_state->get('num_users_bill_info');
    $add_button = $users_bill_info_count + 1;
    $form_state->set('num_users_bill_info',$add_button);
    // \Drupal::logger('resource_management')->notice('@count',array('@count'=>$add_button));
    $form_state->setRebuild();
  }

  public function addMoreCallbackBillingInformationFieldset(array &$form, FormStateInterface $form_state) {

    return $form['member_billing_information_fieldset'];
  }

  public function removeOneBillingInformationFieldset(array &$form, FormStateInterface $form_state) {
    $users_bill_info_count = $form_state->get('num_users_bill_info');
    if($users_bill_info_count > 1){
      $remove_button =  $users_bill_info_count - 1;
      $form_state->set('num_users_bill_info',$remove_button);
    }
    $form_state->setRebuild();
  }

// ##     ##
}

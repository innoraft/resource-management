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
    \Drupal::logger('resource_management')->notice('In buildform');
    if(!is_null($nid)){

      $node = \Drupal\node\Entity\Node::load($nid);
      $paragraph = $node->get('field_member_details')->getValue();      
      $target_id = $paragraph[0]['target_id'];
      $paragraph_member_details = Paragraph::load($target_id);

      $total_billable = floatval($paragraph_member_details->get('field_total_billable')->getValue()[0]['value']);
      $total_non_billable = floatval($paragraph_member_details->get('field_total_non_billable')->getValue());
      $user_name = $paragraph_member_details->get('field_user_name')->getValue()[0]['target_id'];
      $user_name = \Drupal\user\Entity\User::load($user_name);
      $paragraph_lead_and_member_array = $paragraph_member_details->get('field_lead_and_member')->getValue();
      $lead = array();
      $member = array();
      $i = 0;

      foreach ($paragraph_lead_and_member_array as $key => $paragraph_lead_and_member) {
        $paragraph = Paragraph::load($paragraph_lead_and_member['target_id']);
        $lead[$i] = $paragraph->get('field_lead')->getValue()[0]['target_id'];
        $member[$i] = array();
        foreach ($paragraph->get('field_member') as $key => $member_entity) {
          $member[$i][] = $member_entity->getValue()['target_id'];
        }
        $i++;
      }
      $entity =  \Drupal\user\Entity\User::loadMultiple(); // Load entity

      for($j=0; $j< $i; $j++){
        $lead[$j] = $entity[$lead[$j]];
        foreach ($member[$j] as $key => $value) {
          $member[$j][$key] = $entity[$member[$j][$key]]; 
        }
      }

      $form['project_name'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Project Name'),
        '#required' => 'true',
        '#default_value' => $node->getTitle(),
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
        // $lead_member_count = $form_state->set('num_lead_member',1);
      }
      if($form_state->get('num_lead_member') > 0){
        $lead_member_fieldset_count = $form_state->get('num_lead_member');
      }
      else{
        $lead_member_fieldset_count = 1;
      }
      \Drupal::logger('resource_management')->notice('Count @lmfc ',array('@lmfc'=>$lead_member_fieldset_count));
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
          '#default_value' => $lead[$j],
        );
        $form['lead_member_fieldset']['group'][$j]['members_fieldset'] = array(
          '#type' => 'fieldset',
          '#title' => $this->t('Member names'),
          '#prefix' =>  '<div id="members_fieldset_wrapper_'.$j.'">',
          '#suffix' => '</div>',      
        );
        $member_count_prev = $form_state->get(['num_members',$j]);
        $member_count = 0;
        if(empty($member_count_prev)){
          $member_count =  count($member[$j]);
          $form_state->set(['num_members',$j],$member_count);
        }
        if($form_state->get(['num_members',$j]) > 0){
          $member_count = $form_state->get(['num_members',$j]);
        }
        else{
          $member_count = 1;
        }
        for ($i=0; $i < $member_count; $i++) { 
          $form['lead_member_fieldset']['group'][$j]['members_fieldset']['name'][$i] = array(
              '#type' => 'entity_autocomplete',
              '#target_type' => 'user',
              '#title' => $this->t('Member'),
              '#required' => 'true',
              '#default_value' => empty($member[$j][$i]) ? '' : $member[$j][$i],
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
    // kint($form['lead_member_fieldset']['actions']['add_name']);

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

      $form['field_billable'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Billable'),
        '#required' => 'true',
        '#field_suffix' => '%',
      );

      $form['non_billable'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Non-Billable'),
        '#field_suffix' => '%',
      );


      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
      );

      $form_state->set('nid',$nid);


      $form['project_name']['#default_value'] = $node->getTitle();
      $form['user_name']['#default_value'] = $user_name;
      // $form['time_duration']
      // $form['percentage_time']
      // $form['percentage_time']
      $form['field_billable']['#default_value'] = $total_billable;
      $form['non_billable']['#default_value'] = $total_non_billable;
      // kint($form);
      // kint($form['lead_member_fieldset']['group'][0]['members_fieldset']['actions']);
      // kint($form['lead_member_fieldset']['group'][1]['members_fieldset']['actions']);
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
          '#value' => t('Remove One'),
          '#submit' => array('::removeCallbackLeadMemberFieldset'),
          '#ajax' => array(
            'callback' => '::addMoreCallbackLeadMemberFieldset',
            'wrapper' => 'lead_member_fieldset_wrapper',
          ),
        );
      }

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

      $form['field_billable'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Billable'),
        '#required' => 'true',
        '#field_suffix' => '%',
      );

      $form['non_billable'] = array(
        '#type' => 'textfield',
        '#title' => $this->t('Non-Billable'),
        '#field_suffix' => '%',
      );


      // $form['nid'] = array(
      //   '#type' => 'value',
      //   '#value' => $nid,
      // );

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
      );
    }

    $form_state->set('nid',$nid);

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

    if(!is_null($nid)){

      $node = \Drupal\node\Entity\Node::load($nid);
      $node->setTitle($form_state->getValue('project_name'));

      $paragraph = $node->get('field_member_details')->getValue();      
      $target_id = $paragraph[0]['target_id'];

      $paragraph_member_details = Paragraph::load($target_id);
      $paragraph_member_details->set('field_total_billable',$form_state->getValue('field_billable'));
      $paragraph_member_details->set('field_total_non_billable',$form_state->getValue('non_billable'));
      $paragraph_member_details->set('field_user_name',array('target_id' => $form_state->getValue('user_name')));
      $i = 0;
      $paragraph_lead_and_member_array = array();
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
      $paragraph_member_details->save();

      $node->save();
      $form_state->setRedirect('user.info',['uId' => $form_state->getValue('user_name')]);
    }

    else{
      $paragraph_lead_and_member = array();

      for ($i=0; $i < $lead_member_fieldset_group_count; $i++) { 
        $paragraph_lead_and_member[$i] = Paragraph::create([
          'type' => 'lead_and_member',
          'field_lead' => array('target_id' => $lead[$i]),
          'field_member' => $member[$i]
        ]);
        $paragraph_lead_and_member[$i]->save();
      }

      $paragraph_lead_and_member_array = array();
      foreach ($paragraph_lead_and_member as $key => $value) {
        $paragraph_lead_and_member_array[$key]['target_id'] = $paragraph_lead_and_member[$key]->id();
        $paragraph_lead_and_member_array[$key]['target_revision_id'] = $paragraph_lead_and_member[$key]->getRevisionId();
      }

      $paragraph_member_details = Paragraph::create([
        'type' => 'member_details',
        'field_lead_and_member' => $paragraph_lead_and_member_array,
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

  public function addOne(array &$form, FormStateInterface $form_state) {
    $triggeringElement = $form_state->getTriggeringElement()['#name'];
    // \Drupal::logger('resource_management')->notice('trigger @trig',array('@trig'=>print_r($triggeringElement,true)));
    $triggeringElement = explode('-',$triggeringElement);
    $j = $triggeringElement[3]; //id of triggering element
    \Drupal::logger('resource_management')->notice('In addOne Trigger @trig',array('@trig'=>$j));
    $members_count = $form_state->get(['num_members',$j]);
    $add_button = $members_count+1;
    $form_state->set(['num_members',$j],$add_button);
    $form_state->setRebuild();
  }

  public function addMoreCallback(array &$form, FormStateInterface $form_state) {
    // \Drupal::logger('resource_management')->notice('In addMoreCallback');
    $triggeringElement = $form_state->getTriggeringElement()['#name'];
    \Drupal::logger('resource_management')->notice('In addMoreCallback Trigger @trig',array('@trig'=>$triggeringElement));
    $triggeringElement = explode('-',$triggeringElement);
    $j = $triggeringElement[3]; //id of triggering element
    
    return $form['lead_member_fieldset']['group'][$j]['members_fieldset'];
    // return $form['lead_member_fieldset']['group'][0]['members_fieldset'];
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
}

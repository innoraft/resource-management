<?php

namespace Drupal\resource_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\user\Entity\User;
use Drupal\Core\Datetime\DrupalDateTime;
/**
 * {@inheritdoc}
 */

class UserProjectInfo extends ControllerBase{
	
	public function content($uId){

		$user = User::load($uId);
		$name = (!empty($user)) ? $user->getUsername() : NULL;
		$form = \Drupal::formBuilder()->getForm('Drupal\resource_management\Form\UserNameInputForm');
		$form['user_name']['#value'] = $name;
		$markup_form = \Drupal::service('renderer')->render($form);	

		if ($name == NULL) {
			$build = array(
				'#type' => 'markup',
				'#markup' => $markup_form,
			);
			return $build;
		}


		$specialization = $user->get('field_specification')->getValue();
		if (!empty($specialization)) {
			$specialization_vals = array();
			$specialization = $user->get('field_specification')->getValue();
			$specialization_vals =  array_column($specialization, 'target_id');
			array_walk($specialization_vals, array($this,'load_term'));
			$specialization_vals = implode(',', $specialization_vals);
		}
		else{
		 	$specialization_vals = $this->t('No specialization given');
		}


		$para_ids = $this->get_user_billing_information_paragraphs($uId);

		$link_options = array(
			'type' => 'link',
			'title' => $this->t('Add More Information'),
			'attributes' => [
				'class' => ['use-ajax'],
				'data-dialog-type' => 'modal',
				'data-dialog-options' => \Drupal\Component\Serialization\Json::encode(['width' => '700']),
			],
		);

		$url = Url::fromRoute('user.data_entry',['uId'=>$uId]);


		if (empty($para_ids)) {
			$link_options['title'] = $this->t('Add Information');
			$url->setOptions($link_options);
			$link = Link::fromTextAndUrl($link_options['title'], $url )->toString();
			$markup_data = $this->t("No information avalaible for user yet.");
			$markup_form = $markup_form->jsonSerialize();
			$markup_obj = Markup::create($markup_form.$markup_data.'<br>'.$link);
			$build = array(
				'#type' => 'markup',
				'#markup' => $markup_obj,
			);	
			return $build;
		}

		$url->setOptions($link_options);
		$link = Link::fromTextAndUrl($link_options['title'], $url )->toString();
		$query = \Drupal::entityQuery('node')
			->condition('type','project')
			->condition('status',1)
			->condition('field_member_details.target_id',$para_ids,'IN')
			->sort('nid','DESC');
			$rs = $query->execute();

		$nodes = NULL;
		if(!empty($rs)){
			$nids = array_values($rs); 
			$nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
		}
		else{
			$link_options['title'] = $this->t('Add Information');
			$url->setOptions($link_options);
			$link = Link::fromTextAndUrl($link_options['title'], $url )->toString();
			$markup_data = $this->t("No information avalaible for user yet.");
			$markup_form = $markup_form->jsonSerialize();
			$markup_obj = Markup::create($markup_form.$markup_data.'<br>'.$link);
			$build = array(
				'#type' => 'markup',
				'#markup' => $markup_obj,
			);	
			return $build;			
		}


		$i = 0;
		$user_node_count = 0;
		$total_billable = 0.0;
		$total_non_billable = 0.0;

		$delete_link_options = array(
			'type' => 'link',
			'title' => $this->t('Delete Information'),
			'attributes' => [
				'class' => ['delete-link'],
				'data-toggle' => 'confirmation',
			],
		);

		foreach ($nodes as $node_detail) {
			$node[$i]['title']  = $node_detail->getTitle();
			if(!empty($node_detail->get('field_member_details')->getValue())){
				$paragraph_member_details = Paragraph::load($node_detail->get('field_member_details')->getValue()[0]['target_id']);
				$paragraph_lead_and_member_array = $paragraph_member_details->get('field_lead_and_member')->getValue();
				if(!empty($paragraph_lead_and_member_array)){
					foreach ($paragraph_lead_and_member_array as $key => $value) {
						$paragraph_lead_and_member = Paragraph::load($value['target_id']);
						$node[$i]['lead_member_group_id'][$key]['id'] = $value['target_id'];
						$node[$i]['lead'][$key] = User::load($paragraph_lead_and_member->get('field_lead')->getValue()[0]['target_id'])->getUsername();
						
						foreach ($paragraph_lead_and_member->get('field_member')->getValue() as $key1 => $member) {
							$node[$i]['member'][$key][$key1] = User::load($member['target_id'])->getUsername();
						}
						$delete_url = Url::fromRoute('user.delete_paragraph',['pid'=>$value['target_id'] ,'uId' => $uId]);
						$delete_url->setOptions($delete_link_options);
						$node[$i]['lead_member_group_id'][$key]['delete_link'] = Link::fromTextAndUrl($delete_link_options['title'], $delete_url)->toString();
					}
				}

				$paragraph_billing_information_array = $paragraph_member_details->get('field_billing_information')->getValue();
				
				if(!empty($paragraph_billing_information_array)){
					foreach ($paragraph_billing_information_array as $key => $value) {
						$paragraph_billing_information = Paragraph::load($value['target_id']);
						$node[$i]['billing_information'][$key]['id'] = $value['target_id'];
						$node[$i]['billing_information'][$key]['user_name'] = User::load($paragraph_billing_information->get('field_user_name')->getValue()[0]['target_id'])->getUsername();
						$node[$i]['billing_information'][$key]['billable'] = $paragraph_billing_information->get('field_billable')->getValue()[0]['value'];
						$node[$i]['billing_information'][$key]['non_billable'] = $paragraph_billing_information->get('field_non_billable')->getValue()[0]['value'];
						$delete_url = Url::fromRoute('user.delete_paragraph',['pid'=>$value['target_id'] , 'uId' => $uId]);
						$delete_url->setOptions($delete_link_options);
						$node[$i]['billing_information'][$key]['delete_link'] = Link::fromTextAndUrl($delete_link_options['title'], $delete_url)->toString();
						if(!empty($paragraph_billing_information->get('field_end_date')->getValue())){
								$date_time_object = new DrupalDateTime();
								$start_date = $date_time_object->createFromTimestamp(strtotime($paragraph_billing_information->get('field_start_date')->getValue()[0]['value']))->format('d/M/Y');
								$end_date = $date_time_object->createFromTimestamp(strtotime($paragraph_billing_information->get('field_end_date')->getValue()[0]['value']))->format('d/M/Y');
								$node[$i]['billing_information'][$key]['start_date'] = $start_date;
								$node[$i]['billing_information'][$key]['end_date'] = $end_date;
						}
						else{
								$node[$i]['billing_information'][$key]['start_date'] = $this->t('No date given');
								$node[$i]['billing_information'][$key]['end_date'] = $this->t('No date given');					
						}
						if(User::load($uId)->getUsername() === $node[$i]['billing_information'][$key]['user_name']){
							$total_billable += $node[$i]['billing_information'][$key]['billable'];
							$total_non_billable += $node[$i]['billing_information'][$key]['non_billable'];
							$user_node_count++;
						}
					}
				}
			}

			$nId = $node_detail->get('nid')->getValue()[0]['value'];
			$url = Url::fromRoute('user.data_entry',['uId'=>$uId , 'nid'=>$nId]);
			$link_options['title'] = $this->t('Edit Information');
			$url->setOptions($link_options);
			$node[$i]['link'] = Link::fromTextAndUrl($link_options['title'], $url )->toString();
			$i++;
		}


		$markup_nodes = '';

		$markup_data =
		"<div>Name : ".$name."</div>
		<div>Specialization : ".$specialization_vals."</div>
		<div>Total billable : ".($total_billable/$user_node_count)."%</div>
		<div>Total non-billable : ".($total_non_billable/$user_node_count)."%</div>
		<div>".$link."</div>";
		$markup_form = $markup_form->jsonSerialize();

		$markup_obj = Markup::create($markup_form.$markup_data.$markup_nodes);

		$node['node_data'] = $node;

		$node['user_specific_data'] = array(
			'name' => $name,
			'specialization' => $specialization_vals,
			'total_billable' => ($total_billable/$user_node_count),
			'total_non_billable' => ($total_non_billable/$user_node_count),
			'link' => $link,
		);

		$node['form_data'] = $markup_form;

		$build = array(
			'#theme' => 'block__user_project_info',
			'#attached' => array(
				'library' => array(
					'resource_management/lib1'
				),
			),
            '#nodes' => $node,
		);	
		return $build;
	}


	public function delete($pid,$uId){
		$paragraph_id = $pid;
		$storage = \Drupal::entityTypeManager()->getStorage('paragraph');
		$paragraph = $storage->load($paragraph_id);
		$paragraph_type = $paragraph->getType();
		$paragraph_parent = $paragraph->get('parent_id')->getValue()[0]['value'];
		$paragraph_parent = Paragraph::load($paragraph_parent);
		$key = NULL;

		if(strcmp($paragraph_type, 'lead_and_member') === 0){
			$paragraph_lead_and_member_array = $paragraph_parent->get('field_lead_and_member')->getValue();
			foreach ($paragraph_lead_and_member_array as $key1 => $paragraph_lead_and_member) {
				if($paragraph_lead_and_member['target_id'] === $paragraph_id)
					$key = $key1;
			}
			array_splice($paragraph_lead_and_member_array,$key, 1);
			$paragraph_parent->set('field_lead_and_member',$paragraph_lead_and_member_array);
			$paragraph_parent->save();
		}
		else{
			$paragraph_billing_information_array = $paragraph_parent->get('field_billing_information')->getValue();
			$key = array_search($paragraph_id , $paragraph_billing_information_array);
			foreach ($paragraph_billing_information_array as $key1 => $paragraph_billing_information) {
				if($paragraph_billing_information['target_id'] === $paragraph_id)
					$key = $key1;
			}
			array_splice($paragraph_billing_information_array, $key, 1);
			$paragraph_parent->set('field_billing_information',$paragraph_billing_information_array);
			$paragraph_parent->save();
		}
		$paragraph->delete();

		return $this->redirect('user.info',['uId' => $uId]);
	}

	private function load_term(&$val,$key){
		$term =  \Drupal\taxonomy\Entity\Term::load($val);
		$val = $term->get('name')->getValue()[0]['value'];
	}

	private function get_user_billing_information_paragraphs($uId){
		$query = \Drupal::database()->select('paragraph__field_billing_information','b_info');
		$query->fields('b_info', ['entity_id']);
		$query->join('paragraph__field_user_name','uname','b_info.field_billing_information_target_id = uname.entity_id');
		$query->condition('uname.field_user_name_target_id', $uId);
		$rs = $query->execute();

		$para_ids = array();
		while ($row = $rs->fetchAssoc()) {
			$para_ids[] = $row['entity_id'];
		}

		return $para_ids;
	}

}
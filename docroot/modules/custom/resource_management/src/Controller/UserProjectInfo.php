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

		$form = \Drupal::formBuilder()->getForm('Drupal\resource_management\Form\UserNameInputForm');
		if($uId == '0'){
			$markup_form = \Drupal::service('renderer')->render($form);	
			$build = array(
				'#type' => 'markup',
				'#markup' => $markup_form,
			);	

			return $build;
		}

		$user = User::load($uId);
		$name = $user->getUsername();

		$form['user_name']['#value'] = $name;
		$markup_form = \Drupal::service('renderer')->render($form);

		$specialization_vals = '';
		$specialization = $user->get('field_specification')->getValue();
		if(!empty($specialization)){
			foreach ($specialization as $spec) {
				$spec = \Drupal\taxonomy\Entity\Term::load($spec['target_id']);
				if(!is_null($spec)){
					$spec = strip_tags($spec->get('description')->getValue()[0]['value']);
					$specialization_vals .= $spec.",";
				}
			}
			$specialization_vals = substr($specialization_vals,0,-1);
		}
		else
			$specialization_vals = 'No specialization given';

		
		$query = \Drupal::database()->select('paragraph__field_billing_information','b_info');
		$query->fields('b_info', ['entity_id']);
		$query->join('paragraph__field_user_name','uname','b_info.field_billing_information_target_id = uname.entity_id');
		$query->condition('uname.field_user_name_target_id', $uId);
		$rs = $query->execute();

		$para_ids = array();
		while($row = $rs->fetchAssoc()){
			$para_ids[] = $row['entity_id'];
		}

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
			$link_options['title'] = 'Add Information';
			$url->setOptions($link_options);
			$link = Link::fromTextAndUrl($link_options['title'], $url )->toString();
			$markup_data = "No information avalaible for user yet.";
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
			$nids = array_values($rs);  // or array_keys check
			$nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
		}
		else{
			$link_options['title'] = 'Add Information';
			$url->setOptions($link_options);
			$link = Link::fromTextAndUrl($link_options['title'], $url )->toString();
			$markup_data = "No information avalaible for user yet.";
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

		foreach ($nodes as $node_detail) {
			$node[$i]['title']  = $node_detail->getTitle();
			$paragraph_member_details =Paragraph::load($node_detail->get('field_member_details')->getValue()[0]['target_id']);
			$paragraph_lead_and_member_array = $paragraph_member_details->get('field_lead_and_member')->getValue();
			foreach ($paragraph_lead_and_member_array as $key => $value) {
				$paragraph_lead_and_member = Paragraph::load($value['target_id']);
				$node[$i]['lead'][$key] = $paragraph_lead_and_member->get('field_lead')->getValue()[0]['target_id'];
				
				foreach ($paragraph_lead_and_member->get('field_member')->getValue() as $key1 => $member) {
					$node[$i]['member'][$key][$key1] = $member['target_id'];
				}
			}

			$paragraph_billing_information_array = $paragraph_member_details->get('field_billing_information')->getValue();
			foreach ($paragraph_billing_information_array as $key => $value) {
				$paragraph_billing_information = Paragraph::load($value['target_id']);
				$node[$i]['billing_information'][$key]['user_name'] = $paragraph_billing_information->get('field_user_name')->getValue()[0]['target_id'];
				$node[$i]['billing_information'][$key]['billable'] = $paragraph_billing_information->get('field_billable')->getValue()[0]['value'];
				$node[$i]['billing_information'][$key]['non_billable'] = $paragraph_billing_information->get('field_non_billable')->getValue()[0]['value'];

				if(!empty($paragraph_billing_information->get('field_end_date')->getValue())){
						$date_time_object = new DrupalDateTime();
						$start_date = $date_time_object->createFromTimestamp(strtotime($paragraph_billing_information->get('field_start_date')->getValue()[0]['value']))->format('d/M/Y');
						$end_date = $date_time_object->createFromTimestamp(strtotime($paragraph_billing_information->get('field_end_date')->getValue()[0]['value']))->format('d/M/Y');
						$node[$i]['billing_information'][$key]['start_date'] = $start_date;
						$node[$i]['billing_information'][$key]['end_date'] = $end_date;
				}
				else{
						$node[$i]['billing_information'][$key]['start_date'] = 'No date given';
						$node[$i]['billing_information'][$key]['end_date'] = 'No date given';					
				}
				if($uId == $node[$i]['billing_information'][$key]['user_name']){
					$total_billable += $node[$i]['billing_information'][$key]['billable'];
					$total_non_billable += $node[$i]['billing_information'][$key]['non_billable'];
					$user_node_count++;
				}

			}

			$nId = $node_detail->get('nid')->getValue()[0]['value'];
			$url = Url::fromRoute('user.data_entry',['uId'=>$uId , 'nid'=>$nId]);
			$link_options['title'] = 'Edit Information';
			$url->setOptions($link_options);
			$node[$i]['link'] = Link::fromTextAndUrl($link_options['title'], $url )->toString();
			$i++;
		}


		$markup_nodes = $this->getMarkup($node);


		$markup_data =
		"<div>Name : ".$name."</div>
		<div>Specialization : ".$specialization_vals."</div>
		<div>Total billable : ".($total_billable/$user_node_count)."%</div>
		<div>Total non-billable : ".($total_non_billable/$user_node_count)."%</div>
		<div>".$link."</div>";
		$markup_form = $markup_form->jsonSerialize();

		$markup_obj = Markup::create($markup_form.$markup_data.$markup_nodes);

		$build = array(
			'#type' => 'markup',
			'#markup' => $markup_obj,
		);	
		return $build;
	}

	private function getMarkup($node_array){
		$markup_nodes = '';
		foreach ($node_array as $key => $node) {
			$markup_nodes .= '<div class = "node" style="border:5px solid #390">';

			$markup_nodes .= '<div>Project Name : '.$node['title'].'</div>';
			$markup_nodes .= '<div class = "lead_member_group">';				
			foreach ($node['lead'] as $key1 => $lead) {
				$markup_nodes .= '<div class = "lead_member_group'.$key1.'" style="border-top:1px solid #000;border-bottom:1px solid #000;">';				
				$markup_nodes .= '<div> Lead : '.User::load($lead)->getUsername().'</div>';
				foreach ($node['member'][$key1] as $key2 => $member) {
					$markup_nodes .= '<div> Member : '.User::load($member)->getUsername().'</div>';
				}
				$markup_nodes .= '</div>';
			}
			$markup_nodes .= '</div>';

			$markup_nodes .= '<div class = "billing_information_group" style="border:1px solid #000;">';				
			foreach ($node['billing_information'] as $key => $billing_information) {
				$markup_nodes .= '<div class = "billing_information_group'.$key.'" style="border-top:1px solid #913;border-bottom:1px solid #713;" >';				
				$markup_nodes .= '<div> User Name : '.User::load($billing_information['user_name'])->getUsername().'</div>';
				$markup_nodes .= '<div> Billable : '.$billing_information['billable'].'%</div>';
				$markup_nodes .= '<div> Non-Billable : '.$billing_information['non_billable'].'%</div>';
				$markup_nodes .= '<div> Start Date : '.$billing_information['start_date'].'</div>';
				$markup_nodes .= '<div> End Date : '.$billing_information['end_date'].'</div>';
				$markup_nodes .= '</div>';
			}
			$markup_nodes .= '</div>';
			$markup_nodes .= $node['link'];
			$markup_nodes .= '</div>';
		}

		return $markup_nodes;
	}


}
<?php

namespace Drupal\resource_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Url;
/**
 * {@inheritdoc}
 */

class UserProjectInfo extends ControllerBase{
	
	public function content($uId){

		$form = \Drupal::formBuilder()->getForm('Drupal\resource_management\Form\UserNameInputForm');
		// kint($uId);
		if($uId == '0'){
			$markup_form = \Drupal::service('renderer')->render($form);	
			$build = array(
				'#type' => 'markup',
				'#markup' => $markup_form,
			);	

			return $build;
		}

		$user = \Drupal\user\Entity\User::load($uId);
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

		
		// $query = \Drupal::database()->select('paragraph__field_user_name', 'uname');
		$query = \Drupal::database()->select('paragraph__field_billing_information','b_info');
		$query->fields('b_info', ['entity_id']);
		$query->join('paragraph__field_user_name','uname','b_info.field_billing_information_target_id = uname.entity_id');
		$query->condition('uname.field_user_name_target_id', $uId);
		// $query->orderBy('entity_id','DESC');
		$rs = $query->execute();

		$para_ids = array();
		while($row = $rs->fetchAssoc()){
			$para_ids[] = $row['entity_id'];
		}

		$link_options = array(
			'type' => 'link',
			'title' => $this->t('Add More Information'),
			// '#url' => \Drupal\Core\Url::fromRoute('user.info'),
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
			$link = \Drupal\Core\Link::fromTextAndUrl($link_options['title'], $url )->toString();
			$markup_data = "No information avalaible for user yet.";
			$markup_form = $markup_form->jsonSerialize();
			$markup_obj = \Drupal\Core\Render\Markup::create($markup_form.$markup_data.'<br>'.$link);
			$build = array(
				'#type' => 'markup',
				'#markup' => $markup_obj,
			);	
			return $build;
		}

		$url->setOptions($link_options);
		$link = \Drupal\Core\Link::fromTextAndUrl($link_options['title'], $url )->toString();
		$query = \Drupal::entityQuery('node')
			->condition('type','project')
			->condition('status',1)
			->condition('field_member_details.target_id',$para_ids,'IN');
			$rs = $query->execute();

		$nodes = NULL;
		if(!empty($rs)){
			$nids = array_values($rs);  // or array_keys check
			$nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
		}
		else{
			$link_options['title'] = 'Add Information';
			$url->setOptions($link_options);
			$link = \Drupal\Core\Link::fromTextAndUrl($link_options['title'], $url )->toString();
			$markup_data = "No information avalaible for user yet.";
			$markup_form = $markup_form->jsonSerialize();
			$markup_obj = \Drupal\Core\Render\Markup::create($markup_form.$markup_data.'<br>'.$link);
			$build = array(
				'#type' => 'markup',
				'#markup' => $markup_obj,
			);	
			return $build;			
		}

		// $paragraph_id = array();
		// foreach ($nodes as $node) {
		// 	$paragraph = $node->get('field_member_details')->getValue();			
		// 	$paragraph_id[] = $paragraph[0]['target_id'];
		// }

		// $paragraph = array();
		$i = 0;
		$node = array();
		$total_billable = 0.0;
		$total_non_billable = 0.0;

		foreach ($nodes as $node_detail) {
			$node[$i]['title']  = $node_detail->getTitle();
			$paragraph_single = Paragraph::load($para_ids[$i]);
			$lead_member_para_id = $paragraph_single->get('field_lead_and_member')->getValue()[0]['target_id'];
			$lead_member_para = Paragraph::load($lead_member_para_id);
			$node[$i]['lead'] = \Drupal\user\Entity\User::load($lead_member_para->get('field_lead')->getValue()[0]['target_id'])->getUsername();
			$member_group = $lead_member_para->get('field_member')->getValue();
			foreach ($member_group as $key => $member_single) {
				$node[$i]['member'][$key] = \Drupal\user\Entity\User::load($member_single['target_id'])->getUsername();
			}

			$billing_information_para_id = $paragraph_single->get('field_billing_information')->getValue()[0]['target_id'];
			$billing_information_para = Paragraph::load($billing_information_para_id);
			// $node[$i]['user_name'] = \Drupal\user\Entity\User::load($billing_information_para->get('field_user_name')->getValue()[0]['target_id'])->getUsername();
			$node[$i]['billable'] = $billing_information_para->get('field_billable')->getValue()[0]['value'];
			$total_billable += $node[$i]['billable'];
			$node[$i]['non_billable'] = $billing_information_para->get('field_non_billable')->getValue()[0]['value'];
			$total_non_billable += $node[$i]['non_billable'];
			$nId = $node_detail->get('nid')->getValue()[0]['value'];
			$url = Url::fromRoute('user.data_entry',['uId'=>$uId , 'nid'=>$nId]);
			$link_options['title'] = 'Edit Information';
			$url->setOptions($link_options);
			$node[$i]['link'] = \Drupal\Core\Link::fromTextAndUrl($link_options['title'], $url )->toString();
			// $time_duration[$i] = $billing_information_para->get('field_time_duration')->getValue()[0]['value'];
			$i++;
		}

		$total = $total_billable + $total_non_billable;

		$markup_nodes = '';
		foreach ($node as $key => $value) {
			$markup_nodes .=
			'<div style="border:1px solid #000">'. 
			'<div> Project Name : '.$value['title'].'</div>'.
			'<div> Billable : '.$value['billable'].'</div>'.
			'<div> Non-Billable : '.$value['non_billable'].'</div>'.
			'<div>'.$value['link'].'</div>'.
			'</div>';
		}



		$markup_data =
		"<div>Name : ".$name."</div>
		<div>Specialization : ".$specialization_vals."</div>
		<div>Total billable : ".$total_billable."</div>
		<div>Total non-billable : ".$total_non_billable."</div>
		<div>Total : ".$total."</div>
		<div>".$link."</div>";
		$markup_form = $markup_form->jsonSerialize();

		// $markup_nodes = $this->getUserNodes($uId);

		// $markup_obj = \Drupal\Core\Render\Markup::create($markup_form.$markup_data.$markup_nodes);
		$markup_obj = \Drupal\Core\Render\Markup::create($markup_form.$markup_data.$markup_nodes);

		$build = array(
			'#type' => 'markup',
			'#markup' => $markup_obj,
		);	
		return $build;
	}

	public function getUserNodes($uId){

		$query = \Drupal::database()->select('paragraph__field_user_name', 'uname');
		$query->fields('uname', ['entity_id']);
		$query->condition('uname.field_user_name_target_id', $uId);
		$rs = $query->execute();

		$para_ids = array();
		while($row = $rs->fetchAssoc()){
			$para_ids[] = $row['entity_id'];
		}


		if(empty($para_ids))
			return NULL;

		$query = \Drupal::entityQuery('node')
			->condition('type','project')
			->condition('status',1)
			->condition('field_member_details',$para_ids,'in')
			->sort('nid','DESC');

		$rs = $query->execute();

		if(!empty($rs)){
			$nids = array_keys($rs);
			$nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
		}

		$build = '';
		$total_billable = 0.0;
		$total_non_billable = 0.0;

		foreach ($nodes as $node) {
			// $node_link = '<a href="/node/' . $node->get('nid')->getValue()[0]['value'] . '/edit">Edit Node</a>';
			// $node_link = $node->toLink()->toString()->getGeneratedLink();
			$link_options = array(
				'type' => 'link',
				'title' => $this->t('Edit'),
				// '#url' => \Drupal\Core\Url::fromRoute('user.info'),
				'attributes' => [
					'class' => ['use-ajax'],
					'data-dialog-type' => 'modal',
					'data-dialog-options' => \Drupal\Component\Serialization\Json::encode(['width' => '700']),
				],
			);
			$nId = $node->get('nid')->getValue()[0]['value'];
			$url = Url::fromRoute('user.data_entry',['uId'=>$uId , 'nid'=>$nId]);
			$url->setOptions($link_options);
			$node_link = \Drupal\Core\Link::fromTextAndUrl($link_options['title'], $url )->toString();
			// kint($node_link);
			$title = $node->getTitle();
			$paragraph = $node->get('field_member_details')->getValue();			
			$target_id = $paragraph[0]['target_id'];
			$paragraph_single = Paragraph::load($target_id);
			// kint($paragraph_single);
			$total_billable = floatval($paragraph_single->get('field_total_billable')->getValue()[0]['value']);
			$total_non_billable = floatval($paragraph_single->get('field_total_non_billable')->getValue());
			$user_name = $paragraph_single->get('field_user_name')->getValue()[0]['target_id'];

			$leadAndMemberId = $paragraph_single->get('field_lead_and_member')->getValue()[0]['target_id'];
			$leadAndMemberPara = Paragraph::load($leadAndMemberId);
			// kint($leadAndMemberPara);
			// kint($leadAndMemberPara->get('field_lead')->getValue());
			$lead = '';
			$member = '';

			if(!empty($leadAndMemberPara->get('field_lead')->getValue())) {
				$lead = $leadAndMemberPara->get('field_lead')->getValue()[0]['target_id'];
				$lead = \Drupal\user\Entity\User::load($lead);
				$lead = $lead->getUsername();	
			} else {
				$lead = 'No lead';
			}
			
			if(!empty($leadAndMemberPara->get('field_member')->getValue())) {
				$member = $leadAndMemberPara->get('field_member')->getValue()[0]['target_id'];	
				$member = \Drupal\user\Entity\User::load($member);
				$member = $member->getUsername();	
			} else {
				$member = 'No member';
			}
			// die();

			$build .= "<div style='border:1px solid #000'>".
			"<div>Project Name : ".$title."</div>".
			"<div>Lead : ".$lead."</div>".
			"<div>Member : ".$member."</div>".
			"<div>Total billable : ".$total_billable."</div>".
			"<div>Total non-billable : ".$total_non_billable."</div>".
			"<div>Edit : ".$node_link."</div>".
			"</div>";			
		}


		return $build;
	}
}
<?php

namespace Drupal\resource_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * {@inheritdoc}
 */

class UserProjectInfo extends ControllerBase{
	
	public function content($uId){

		// $div = array(
		// 	'#type' => 'div',
		// 	'attributes' => array(
		// 		'id' => 'user-project-info',
		// 	),
		// );	
		$user = \Drupal\user\Entity\User::load($uId);
		$name = $user->getusername();
		// kint($user->getFieldDefinitions());die();
		$specialization_vals = '';
		$specialization = $user->get('field_specification')->getValue();

		if(!empty($specialization)){
			foreach ($specialization as $spec) {
				$spec = \Drupal\taxonomy\Entity\Term::load($spec['target_id']);
				$spec = strip_tags($spec->get('description')->getValue()[0]['value']);
				$specialization_vals .= $spec.",";
			}
			$specialization_vals = substr($specialization_vals,0,-1);
		}
		else
			$specialization_vals = 'No specialization given';


		$query = \Drupal::entityQuery('node')
			->condition('type','project')
			->condition('status',1)
			->condition('uid',$uId);

		$result = $query->execute();

		if(isset($result)){
			$nids = array_keys($result);
			$nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
		}

		$paragraph_id = array();
		foreach ($nodes as $node) {
			$paragraph = $node->get('field_member_details')->getValue();			
			$paragraph_id[] = $paragraph[0]['target_id'];
		}
		
		$paragraph = array();
		$total_billable = 0.0;
		$total_non_billable = 0.0;
		foreach ($paragraph_id as $target_id) {
			$paragraph_single = Paragraph::load($target_id);
			$total_billable += floatval($paragraph_single->get('field_total_billable')->getValue()[0]['value']);
			$total_non_billable += floatval($paragraph_single->get('field_total_non_billable')->getValue()[0]['value']);
			$paragraph[] = $paragraph_single;
		}

		// var_dump($total_non_billable);
		$total = $total_billable + $total_non_billable;

		$markup = 
		"<div>Name : ".$name."</div>
		<div>Specialization : ".$specialization_vals."</div>
		<div>Total billable : ".$total_billable."</div>
		<div>Total non-billable : ".$total_non_billable."</div>
		<div>Total : ".$total."</div>";
		

		$build = array(
			'#type' => 'markup',
			'#markup' => $markup,
		);

		return $build;
	}

}
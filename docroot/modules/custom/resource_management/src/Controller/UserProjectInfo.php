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
		$markup_form = \Drupal::service('renderer')->render($form);
		
		if($uId == '0'){
			$form = \Drupal::formBuilder()->getForm('Drupal\resource_management\Form\UserNameInputForm');
			$markup_form = \Drupal::service('renderer')->render($form);	
			$build = array(
				'#type' => 'markup',
				'#markup' => $markup_form,
			);	

			return $build;
		}

		$user = \Drupal\user\Entity\User::load($uId);
		$name = $user->getusername();
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

		
		$query = \Drupal::database()->select('paragraph__field_user_name', 'uname');
		$query->fields('uname', ['entity_id']);
		$query->condition('uname.field_user_name_target_id', $uId);
		$rs = $query->execute();
		$id = '';

		$para_ids = array();
		while($row = $rs->fetchAssoc()){
			$para_ids[] = $row['entity_id'];
		}

		if (empty($para_ids)) {
			kint($para_ids);
			$markup_data = "No information avalaible for user yet.";
			$markup_form = $markup_form->jsonSerialize();
			$markup_obj = \Drupal\Core\Render\Markup::create($markup_form.$markup_data);
			$build = array(
				'#type' => 'markup',
				'#markup' => $markup_obj,
			);	
			return $build;
		}

		$query = \Drupal::entityQuery('node')
			->condition('type','project')
			->condition('status',1)
			->condition('field_member_details',$para_ids,'in');

		$rs = $query->execute();

		if(isset($rs)){
			$nids = array_keys($rs);
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

		$total = $total_billable + $total_non_billable;


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
		$url->setOptions($link_options);
		$link = \Drupal\Core\Link::fromTextAndUrl($link_options['title'], $url )->toString();

		$markup_data =
		"<div>Name : ".$name."</div>
		<div>Specialization : ".$specialization_vals."</div>
		<div>Total billable : ".$total_billable."</div>
		<div>Total non-billable : ".$total_non_billable."</div>
		<div>Total : ".$total."</div>
		<div>".$link."</div>";
		$markup_form = $markup_form->jsonSerialize();
		$markup_obj = \Drupal\Core\Render\Markup::create($markup_form.$markup_data);
		$build = array(
			'#type' => 'markup',
			'#markup' => $markup_obj,
		);	

		return $build;
	}

}
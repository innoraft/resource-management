<?php
/**
 * @file
 * Contains \Drupal\form_block\Plugin\Block\MemberDetailsBlock.
 */

namespace Drupal\form_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormInterface;

/**
 * Provides a block for filling out form for member details.
 *
 * @Block(
 *   id = "member_details_block",
 *   admin_label = @Translation("Member Details Block"),
 *   category = @Translation("Custom Block")
 * )
 */
class MemberDetailsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $form = \Drupal::formBuilder()->getForm('Drupal\form_block\Form\MemberDetailsForm');

    return $form;
   }
}
(function ($, Drupal) {
  Drupal.behaviors.myModuleBehavior = {
    attach: function (context, settings) {

      $('.delete-link').on('click', function(){
      	return confirm('Are you sure you want to delete?');
      });
    }
  };
})(jQuery, Drupal);

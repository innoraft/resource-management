(function ($, Drupal) {
  Drupal.behaviors.myModuleBehavior = {
    attach: function (context, settings) {
      // $('input.myCustomBehavior', context).once('myCustomBehavior').each(function () {
      //   // Apply the myCustomBehaviour effect to the elements only once.
      // });
      $('.delete-link').on('click',function(){
      	var userConfirm = confirm('Are you sure you want to delete?');
      	if(userConfirm == true){
      		return true;
      	}
      	else{
      		return false;
      	}
      })
    }
  };
})(jQuery, Drupal);
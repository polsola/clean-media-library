jQuery( document ).on( 'click', '.fix-media-button', function(e) {
  e.preventDefault();
	var attachment_id = jQuery(this).data('id');
  var $container = jQuery('#post-' + attachment_id );
	jQuery.ajax({
		url : fixmedia.ajax_url,
		type : 'post',
		data : {
			action : 'ps_fix_attachment',
			attachment_id : attachment_id
		},
		success : function( response ) {
      $container.find('.media-icon.image-icon img').attr('src', response.src);
      $container.find('.filename').html(response.filename);
		}
	});
})

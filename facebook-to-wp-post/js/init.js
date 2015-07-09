// JavaScript Document
var $ = jQuery;
var default_error_message = 'ERROR: There was an error while processing your request. Refresh the page and try again.';

$(function() {
	
	$('#import_feed').click(function(){
		$("#cls_result").html("");
		// call ajax
		    $("#frmSetting").find('.loading').show();
			$.ajax({
					   url:ajaxurl,
					   type:'POST',
					   dataType : 'json',
					   data:'action=import_fb_feed',
					   success:function(response)
					   {
					   	    $("#frmSetting").find('.loading').hide();
					   	    $("#cls_result").html(response.message);
							//alert(response);							
					   },
					   error: function(jqXHR, textStatus, errorThrown){
							$("#frmSetting").find('.loading').hide();
							$('#cls_result').html(default_error_message);
					   }
			});
	});
});
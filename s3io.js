jQuery(document).ready(function($) {
	var s3io_error_counter = 30;
//	if (!s3io_vars.attachments) {
//	} else {
	$(function() {
		$("#s3io-delay-slider").slider({
			min: 0,
			max: 30,
			value: $("#s3io-delay").val(),
			slide: function(event, ui) {
				$("#s3io-delay").val(ui.value);
			}
		});
	});
	// cleanup the attachments array
//	var s3io_attachpost = s3io_vars.attachments.replace(/&quot;/g, '"');
//	var s3io_attachments = $.parseJSON(s3io_attachpost);
	var s3io_attachments = s3io_vars.attachments;
	var s3io_i = 0;
	var s3io_k = 0;
	var s3io_delay = 0;
	//var s3io_aux = false;
	//var s3io_main = false;
	// initialize the ajax actions for the appropriate bulk page
	var s3io_init_action = 's3io_bulk_init';
	var s3io_filename_action = 's3io_bulk_filename';
	var s3io_loop_action = 's3io_bulk_loop';
	var s3io_cleanup_action = 's3io_bulk_cleanup';
	var s3io_init_data = {
	        action: s3io_init_action,
		s3io_wpnonce: s3io_vars._wpnonce,
	};
	var s3io_table_action = 's3io_query_table';
	var s3io_table_count_action = 's3io_table_count';
/*	$('#s3io-start').submit(function() {
		s3io_init_action = 'bulk_aux_images_init';
		s3io_filename_action = 'bulk_aux_images_filename';
		s3io_loop_action = 'bulk_aux_images_loop';
		s3io_cleanup_action = 'bulk_aux_images_cleanup';
		if ($('#s3io-force:checkbox:checked').val()) {
			s3io_force = 1;
		}
		var s3io_scan_data = {
			action: s3io_scan_action,
			s3io_force: s3io_force,
			s3io_scan: true,
		};
		$('#s3io-aux-start').hide();
		$('#s3io-scanning').show();
		$.post(ajaxurl, s3io_scan_data, function(response) {
			s3io_attachpost = response.replace(/&quot;/g, '"');
			//s3io_attachments = s3io_attachpost;
			s3io_attachments = $.parseJSON(s3io_attachpost);
			s3io_init_data = {
			        action: s3io_init_action,
				s3io_wpnonce: s3io_vars._wpnonce,
			};
			if (s3io_attachments.length == 0) {
				$('#s3io-scanning').hide();
				$('#s3io-nothing').show();
			}
			else {
				s3ioStartOpt();
			}
	        })
		.fail(function() { 
			$('#s3io-scanning').html('<p style="color: red"><b>' + s3io_vars.scan_fail + '</b></p>');
		});
		return false;
	});*/
/*	$('#import-start').submit(function() {
		$('.bulk-info').hide();
		$('#import-start').hide();
	        $('#s3io-loading').show();
		var import_init_data = {
			action: import_init_action,
			_wpnonce: s3io_vars._wpnonce,
		};
		$.post(ajaxurl, import_init_data, function(response) {
			import_total = response;
			bulkImport();
		});
		return false;
	});	*/
	$('#s3io-show-table').submit(function() {
		var s3io_pointer = 0;
		var s3io_total_pages = Math.ceil(s3io_vars.image_count / 50);
		$('.s3io-table').show();
		$('#s3io-show-table').hide();
		if (s3io_vars.image_count >= 50) {
			$('.tablenav').show();
			$('#next-images').show();
			$('.last-page').show();
		}
	        var s3io_table_data = {
	                action: s3io_table_action,
			s3io_wpnonce: s3io_vars._wpnonce,
			s3io_offset: s3io_pointer,
	        };
		$('.displaying-num').text(s3io_vars.count_string);
		$.post(ajaxurl, s3io_table_data, function(response) {
			$('#s3io-bulk-table').html(response);
		});
		$('.current-page').text(s3io_pointer + 1);
		$('.total-pages').text(s3io_total_pages);
		$('#s3io-pointer').text(s3io_pointer);
		return false;
	});
	$('#next-images').click(function() {
		var s3io_pointer = $('#s3io-pointer').text();
		s3io_pointer++;
	        var s3io_table_data = {
	                action: s3io_table_action,
			s3io_wpnonce: s3io_vars._wpnonce,
			s3io_offset: s3io_pointer,
	        };
		$.post(ajaxurl, s3io_table_data, function(response) {
			$('#s3io-bulk-table').html(response);
		});
		if (s3io_vars.image_count <= ((s3io_pointer + 1) * 50)) {
			$('#next-images').hide();
			$('.last-page').hide();
		}
		$('.current-page').text(s3io_pointer + 1);
		$('#s3io-pointer').text(s3io_pointer);
		$('#prev-images').show();
		$('.first-page').show();
		return false;
	});
	$('#prev-images').click(function() {
		var s3io_pointer = $('#s3io-pointer').text();
		s3io_pointer--;
	        var s3io_table_data = {
	                action: s3io_table_action,
			s3io_wpnonce: s3io_vars._wpnonce,
			s3io_offset: s3io_pointer,
	        };
		$.post(ajaxurl, s3io_table_data, function(response) {
			$('#s3io-bulk-table').html(response);
		});
		if (!s3io_pointer) {
			$('#prev-images').hide();
			$('.first-page').hide();
		}
		$('.current-page').text(s3io_pointer + 1);
		$('#s3io-pointer').text(s3io_pointer);
		$('#next-images').show();
		$('.last-page').show();
		return false;
	});
	$('.last-page').click(function() {
		var s3io_pointer = $('.total-pages').text();
		s3io_pointer--;
	        var s3io_table_data = {
	                action: s3io_table_action,
			s3io_wpnonce: s3io_vars._wpnonce,
			s3io_offset: s3io_pointer,
	        };
		$.post(ajaxurl, s3io_table_data, function(response) {
			$('#s3io-bulk-table').html(response);
		});
		$('#next-images').hide();
		$('.last-page').hide();
		$('.current-page').text(s3io_pointer + 1);
		$('#s3io-pointer').text(s3io_pointer);
		$('#prev-images').show();
		$('.first-page').show();
		return false;
	});
	$('.first-page').click(function() {
		var s3io_pointer = 0;
	        var s3io_table_data = {
	                action: s3io_table_action,
			s3io_wpnonce: s3io_vars._wpnonce,
			s3io_offset: s3io_pointer,
	        };
		$.post(ajaxurl, s3io_table_data, function(response) {
			$('#s3io-bulk-table').html(response);
		});
		$('#prev-images').hide();
		$('.first-page').hide();
		$('.current-page').text(s3io_pointer + 1);
		$('#s3io-pointer').text(s3io_pointer);
		$('#next-images').show();
		$('.last-page').show();
		return false;
	});
	$('#s3io-start').submit(function() {
		s3ioStartOpt();
		return false;
	});
//	}
	function s3ioStartOpt () {
		s3io_k = 0;
		$('#s3io-bulk-stop').submit(function() {
			s3io_k = 9;
			$('#s3io-bulk-stop').hide();
			return false;
		});
		if ( ! $('#s3io-delay').val().match( /^[1-9][0-9]*$/) ) {
			s3io_delay = 0;
		} else {
			s3io_delay = $('#s3io-delay').val();
		}
//		$('.s3io-aux-table').hide();
		$('#s3io-bulk-stop').show();
		$('.s3io-bulk-form').hide();
		$('.s3io-bulk-info').hide();
//		$('h2').hide();
		$('#s3io-force-empty').hide();
	        $.post(ajaxurl, s3io_init_data, function(response) {
	                $('#s3io-bulk-loading').html(response);
			$('#s3io-bulk-progressbar').progressbar({ max: s3io_attachments });
			$('#s3io-bulk-counter').html(s3io_vars.optimized + ' 0/' + s3io_attachments);
			s3ioProcessImage();
	        });
	}
	function s3ioProcessImage () {
//		s3io_attachment_id = s3io_attachments[s3io_i];
	        var s3io_filename_data = {
	                action: s3io_filename_action,
			s3io_wpnonce: s3io_vars._wpnonce,
//			s3io_attachment: s3io_attachment_id,
	        };
		$.post(ajaxurl, s3io_filename_data, function(response) {
			if (s3io_k != 9) {
		        	$('#s3io-bulk-loading').html(response);
			}
		});
/*		if ($('#s3io-force:checkbox:checked').val()) {
			s3io_force = 1;
		}*/
	        var s3io_loop_data = {
	                action: s3io_loop_action,
			s3io_wpnonce: s3io_vars._wpnonce,
//			s3io_attachment: s3io_attachment_id,
			ewww_sleep: s3io_delay,
//			s3io_force: s3io_force,
	        };
	        var s3io_jqxhr = $.post(ajaxurl, s3io_loop_data, function(response) {
			s3io_i++;
			$('#s3io-bulk-progressbar').progressbar("option", "value", s3io_i );
			$('#s3io-bulk-counter').html(s3io_vars.optimized + ' ' + s3io_i + '/' + s3io_attachments);
//			var s3io_exceed=/exceeded/m;
//			if (s3io_exceed.test(response)) {
			if (response == '-9exceeded') {
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.license_exceeded + '</b></p>');
			}
			else if (s3io_k == 9) {
				s3io_jqxhr.abort();
//				s3ioAuxCleanup();
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_stopped + '</b></p>');
			}
			else if (s3io_i < s3io_attachments) {
	                	$('#s3io-bulk-status').append( response );
				s3io_error_counter = 30;
				s3ioProcessImage();
			}
			else {
	                	$('#s3io-bulk-status').append( response );
			        var s3io_cleanup_data = {
			                action: s3io_cleanup_action,
					s3io_wpnonce: s3io_vars._wpnonce,
			        };
			        $.post(ajaxurl, s3io_cleanup_data, function(response) {
			                $('#s3io-bulk-loading').html(response);
					$('#s3io-bulk-stop').hide();
			//		s3ioAuxCleanup();
			        });
			}
	        })
		.fail(function() { 
			if (s3io_error_counter == 0) {
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_interrupted + '</b></p>');
			} else {
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.temporary_failure + ' ' + s3io_error_counter + '</b></p>');
				s3io_error_counter--;
				setTimeout(function() {
					s3ioProcessImage();
				}, 1000);
			}
		});
	}
	function s3ioAuxCleanup() {
		if (s3io_main == true) {
			var s3io_table_count_data = {
				action: s3io_table_count_action,
				s3io_inline: 1,
			};
			$.post(ajaxurl, s3io_table_count_data, function(response) {
				s3io_vars.image_count = response;
			});
			$('#s3io-show-table').show();
			$('#s3io-table-info').show();
			$('.s3io-bulk-form').show();
			$('.s3io-media-info').show();
			$('h2').show();
			if (s3io_aux == true) {
				$('#s3io-aux-first').hide();
				$('#s3io-aux-again').show();
			} else {
				$('#s3io-bulk-first').hide();
				$('#s3io-bulk-again').show();
			}
			s3io_attachpost = s3io_vars.attachments.replace(/&quot;/g, '"');
			s3io_attachments = $.parseJSON(s3io_attachpost);
			s3io_init_action = 'bulk_init';
			s3io_filename_action = 'bulk_filename';
			s3io_loop_action = 'bulk_loop';
			s3io_cleanup_action = 'bulk_cleanup';
			s3io_init_data = {
			        action: s3io_init_action,
				s3io_wpnonce: s3io_vars._wpnonce,
			};
			s3io_aux = false;
			s3io_i = 0;
			s3io_force = 0;
		}
	}	
});
function s3ioRemoveImage(imageID) {
	var s3io_image_removal = {
		action: 's3io_table_remove',
		s3io_wpnonce: s3io_vars._wpnonce,
		s3io_image_id: imageID,
	};
	jQuery.post(ajaxurl, s3io_image_removal, function(response) {
		if(response == '1') {
			jQuery('#s3io-image-' + imageID).remove();
			var s3io_prev_count = s3io_vars.image_count;
			s3io_vars.image_count--;
			s3io_vars.count_string = s3io_vars.count_string.replace( s3io_prev_count, s3io_vars.image_count );
			jQuery('.displaying-num').text(s3io_vars.count_string);
		} else {
			alert(s3io_vars.remove_failed);
		}
	});
}

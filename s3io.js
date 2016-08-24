jQuery(document).ready(function($) {
	var s3io_error_counter = 30;
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
	var s3io_attachments = s3io_vars.attachments;
	var s3io_i = 0;
	var s3io_k = 0;
	var s3io_delay = 0;
	// initialize the ajax actions 
	var s3io_init_action = 's3io_bulk_init';
	var s3io_loop_action = 's3io_bulk_loop';
	var s3io_cleanup_action = 's3io_bulk_cleanup';
	var s3io_init_data = {
	        action: s3io_init_action,
		s3io_wpnonce: s3io_vars._wpnonce,
	};
	var s3io_table_action = 's3io_query_table';
	var s3io_table_count_action = 's3io_table_count';
	// get the urls from the textarea
	var s3io_queue = '';
	var s3io_url_count = 0;
	$('#s3io-bulk-stop').submit(function() {
		s3io_k = 9;
		$('#s3io-bulk-stop').hide();
		return false;
	});
	$('#s3io-url-start').submit(function() {
		s3io_k = 0;
		s3io_queue = $('#s3io-url-image-queue').val().match(/\S+/g);
		s3io_url_count = s3io_queue.length;
		s3io_loop_action = 's3io_url_images_loop';
		var s3io_url = s3io_queue.pop();
		var s3io_url_loop_data = {
			action: s3io_loop_action,
			ewww_force: true,
			s3io_url: s3io_url,
			s3io_wpnonce: s3io_vars._wpnonce,
		};
		if ( ! $('#s3io-delay').val().match( /^[1-9][0-9]*$/) ) {
			s3io_delay = 0;
		} else {
			s3io_delay = $('#s3io-delay').val();
		}
		$('#s3io-bulk-stop').show();
		$('.s3io-bulk-form').hide();
		$('.s3io-bulk-info').hide();
		$('#s3io-url-image-queue').hide();
		$('#s3io-bulk-loading').html(s3io_vars.optimizing + ' ' + s3io_url + '&nbsp;' + s3io_vars.spinner);
		$('#s3io-bulk-counter').html(s3io_vars.optimized + ' ' + s3io_i + '/' + s3io_url_count);
		$('#s3io-bulk-progressbar').progressbar({ max: s3io_url_count });
		$.post(ajaxurl, s3io_url_loop_data, function(response) {
			$('#s3io-bulk-widgets').show();
			s3io_i++;
			var s3io_response = $.parseJSON(response);
			$('#s3io-bulk-progressbar').progressbar("option", "value", s3io_i );
			$('#s3io-bulk-counter').html(s3io_vars.optimized + ' ' + s3io_i + '/' + s3io_url_count);
			if (s3io_response.error) {
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_response.error + '</b></p>');
			}
			else if (s3io_response.results) {
				$('#s3io-bulk-status .inside').append( s3io_response.results );
				if ( s3io_queue.length > 0 ) {
					setTimeout( s3ioProcessImageByURL, s3io_delay * 1000);
				} else {
					$('#s3io-bulk-loading').html(s3io_vars.finished);
					$('#s3io-bulk-stop').hide();
				}
			}
	        })
		.fail(function() { 
			$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_interrupted + '</b></p>');
		});
		return false;
	});
	function s3ioProcessImageByURL() {
		s3io_error_counter = 30;
			var s3io_url = s3io_queue.pop();
			var s3io_url_loop_data = {
				action: s3io_loop_action,
				ewww_force: true,
				s3io_url: s3io_url,
				s3io_wpnonce: s3io_vars._wpnonce,
			};
			$('#s3io-bulk-loading').html(s3io_vars.optimizing + ' ' + s3io_url + '&nbsp;' + s3io_vars.spinner);
			$.post(ajaxurl, s3io_url_loop_data, function(response) {
				s3io_i++;
				$('#s3io-bulk-progressbar').progressbar("option", "value", s3io_i );
				$('#s3io-bulk-counter').html(s3io_vars.optimized + ' ' + s3io_i + '/' + s3io_url_count);
				var s3io_response = $.parseJSON(response);
				if (s3io_response.error) {
					$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_response.error + '</b></p>');
				}
				else if (s3io_k == 9) {
					s3io_jqxhr.abort();
					$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_stopped + '</b></p>');
				}
				else if ( response == 0 ) {
					$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_stopped + '</b></p>');
				}
				else if ( s3io_response.results) {	
					$('#s3io-bulk-status .inside').append( s3io_response.results );
					if ( s3io_queue.length > 0 ) {
						setTimeout( s3ioProcessImageByURL, s3io_delay * 1000);
					} else {
						$('#s3io-bulk-loading').html(s3io_vars.finished);
						$('#s3io-bulk-stop').hide();
					}	
				}
		        })
			.fail(function() { 
				if (s3io_error_counter == 0) {
					$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_interrupted + '</b></p>');
				} else {
					$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.temporary_failure + ' ' + s3io_error_counter + '</b></p>');
					s3io_error_counter--;
					s3io_queue.push( s3io_url );
					setTimeout(function() {
						s3ioProcessImageByURL();
					}, 1000);
				}
			});
	}
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
	function s3ioStartOpt () {
		s3io_k = 0;
		/*$('#s3io-bulk-stop').submit(function() {
			s3io_k = 9;
			$('#s3io-bulk-stop').hide();
			return false;
		});*/
		if ( ! $('#s3io-delay').val().match( /^[1-9][0-9]*$/) ) {
			s3io_delay = 0;
		} else {
			s3io_delay = $('#s3io-delay').val();
		}
		$('#s3io-bulk-stop').show();
		$('.s3io-bulk-form').hide();
		$('.s3io-bulk-info').hide();
//		$('h2').hide();
		$('#s3io-force-empty').hide();
	        $.post(ajaxurl, s3io_init_data, function(response) {
			var s3io_init_response = $.parseJSON(response);
	                $('#s3io-bulk-loading').html(s3io_init_response.results);
			$('#s3io-bulk-progressbar').progressbar({ max: s3io_attachments });
			$('#s3io-bulk-counter').html(s3io_vars.optimized + ' 0/' + s3io_attachments);
			s3ioProcessImage();
	        });
	}
	function s3ioProcessImage () {
	        var s3io_loop_data = {
	                action: s3io_loop_action,
			s3io_wpnonce: s3io_vars._wpnonce,
	        };
	        var s3io_jqxhr = $.post(ajaxurl, s3io_loop_data, function(response) {
			s3io_i++;
			var s3io_response = $.parseJSON(response);
			$('#s3io-bulk-progressbar').progressbar("option", "value", s3io_i );
			$('#s3io-bulk-counter').html(s3io_vars.optimized + ' ' + s3io_i + '/' + s3io_attachments);
			if (s3io_response.error) {
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_response.error + '</b></p>');
			}
			else if (s3io_k == 9) {
				s3io_jqxhr.abort();
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_stopped + '</b></p>');
			}
			else if ( response == 0 ) {
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_stopped + '</b></p>');
			}	
			else if (s3io_i < s3io_attachments) {
				$('#s3io-bulk-widgets').show();
		//		$('#s3io-bulk-status h2').show();
		//		$('#s3io-bulk-last h2').show();
				if (s3io_response.results) {
					$('#s3io-bulk-last .inside').html( s3io_response.results );
					$('#s3io-bulk-status .inside').append( s3io_response.results );
				}
				if (s3io_response.next_file) {
					$('#s3io-bulk-loading').html(s3io_response.next_file);
				}
				if (s3io_response.new_nonce) {
					s3io_vars._wpnonce = s3io_response.new_nonce;
				}
				s3io_error_counter = 30;
				setTimeout(s3ioProcessImage, s3io_delay * 1000);
			}
			else {
				if ( s3io_response.results ) {
					$('#s3io-bulk-status .inside').append( s3io_response.results );
				}
			        var s3io_cleanup_data = {
			                action: s3io_cleanup_action,
					s3io_wpnonce: s3io_vars._wpnonce,
			        };
			        $.post(ajaxurl, s3io_cleanup_data, function(response) {
			                $('#s3io-bulk-loading').html(response);
					$('#s3io-bulk-stop').hide();
					$('#s3io-bulk-last').hide();
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

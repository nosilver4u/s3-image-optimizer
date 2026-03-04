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
	var s3io_pointer = 0;
	var s3io_table_skew = 0;
	var s3io_checked = 0;
	var s3io_i = 0;
	var s3io_k = 0;
	var s3io_delay = 0;
	var s3io_force = 0;
	var s3io_webp_only = 0;
	// This is where we store image records as they are returned, and then we copy/clone from here to the image table if they are on page 1 (index 0).
	var s3io_bulk_first_page = $('<tbody>');
	var s3io_total_pages = Math.ceil(s3io_vars.image_count / 50);
	var s3ioTimeoutHandler;
	var s3ioNumberFormat = new Intl.NumberFormat();
	// initialize the ajax actions
	var s3io_init_action = 's3io_bulk_init';
	var s3io_scan_action = 's3io_image_scan';
	var s3io_loop_action = 's3io_bulk_loop';
	var s3io_cleanup_action = 's3io_bulk_cleanup';
	var s3io_init_data = {
		action: s3io_init_action,
		s3io_wpnonce: s3io_vars._wpnonce,
	};
	var s3io_table_action = 's3io_query_table';
	// get the urls from the textarea
	var s3io_queue = '';
	var s3io_url_count = 0;
	$('.s3io-hndle').click(function() {
		$(this).next('.inside').toggle();
		var button = $(this).prev('.button-link');
		if ('true' == button.attr('aria-expanded')) {
			button.attr('aria-expanded', 'false');
			button.closest('.postbox').addClass('closed');
			button.children('.toggle-indicator').attr('aria-hidden', 'true');
		} else {
			button.attr('aria-expanded', 'true');
			button.closest('.postbox').removeClass('closed');
			button.children('.toggle-indicator').attr('aria-hidden', 'false');
		}
	});
	$('.s3io-handlediv').click(function() {
		$(this).parent().children('.inside').toggle();
		if ('true' == $(this).attr('aria-expanded')) {
			$(this).attr('aria-expanded', 'false');
			$(this).closest('.postbox').addClass('closed');
			$(this).children('.toggle-indicator').attr('aria-hidden', 'true');
		} else {
			$(this).attr('aria-expanded', 'true');
			$(this).closest('.postbox').removeClass('closed');
			$(this).children('.toggle-indicator').attr('aria-hidden', 'false');
		}
	});
	$('#s3io-bulk-stop').submit(function() {
		s3io_k = 9;
		$('#s3io-bulk-stop').hide();
		$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_stopped + '</b></p>');
		clearTimeout(s3ioTimeoutHandler);
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
			var s3io_response = JSON.parse(response);
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
		var s3io_jqxhr = $.post(ajaxurl, s3io_url_loop_data, function(response) {
			s3io_i++;
			$('#s3io-bulk-progressbar').progressbar("option", "value", s3io_i );
			$('#s3io-bulk-counter').html(s3io_vars.optimized + ' ' + s3io_i + '/' + s3io_url_count);
			var s3io_response = JSON.parse(response);
			if (s3io_response.error) {
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_response.error + '</b></p>');
			}
			else if (s3io_k == 9) {
				s3io_jqxhr.abort();
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_stopped + '</b></p>');
				if ( s3io_response.results) {
					$('#s3io-bulk-status .inside').append( s3io_response.results );
				}
			}
			else if ( response == 0 ) {
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_stopped + '</b></p>');
			}
			else if ( s3io_response.results) {
				$('#s3io-bulk-status .inside').append( s3io_response.results );
				if ( s3io_queue.length > 0 ) {
					s3ioTimeoutHandler = setTimeout( s3ioProcessImageByURL, s3io_delay * 1000);
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
				s3ioTimeoutHandler = setTimeout(function() {
					s3ioProcessImageByURL();
				}, 1000);
			}
		});
	}
	$('#s3io-show-table').submit(function() {
		$('.s3io-table').show();
		$('#s3io-show-table').hide();
		$('.prev-page').addClass('disabled');
		$('.first-page').addClass('disabled');
		if (s3io_vars.image_count >= 50) {
			$('.tablenav').show();
		} else {
			$('.next-page').addClass('disabled');
			$('.last-page').addClass('disabled');
		}
		var s3io_table_data = {
			action: s3io_table_action,
			s3io_wpnonce: s3io_vars._wpnonce,
			s3io_offset: s3io_pointer,
			s3io_skew: s3io_table_skew,
		};
		$.post(ajaxurl, s3io_table_data, function(response) {
			$('#s3io-bulk-table tbody').html(s3ioParseTableResponse(response));
			s3io_bulk_first_page = $('#s3io-bulk-table tbody').clone();
		});
		$('.current-page').text(s3io_pointer + 1);
		$('.total-pages').text(s3ioNumberFormat.format(s3io_total_pages));
		return false;
	});
	$('.next-page').click(function() {
		if ($(this).hasClass('disabled')) {
			return false;
		}
		s3io_pointer++;
		var s3io_table_data = {
			action: s3io_table_action,
			s3io_wpnonce: s3io_vars._wpnonce,
			s3io_offset: s3io_pointer,
			s3io_skew: s3io_table_skew,
		};
		$.post(ajaxurl, s3io_table_data, function(response) {
			$('#s3io-bulk-table tbody').html(s3ioParseTableResponse(response));
		});
		if (s3io_vars.image_count <= ((s3io_pointer + 1) * 50)) {
			$('.next-page').addClass('disabled');
			$('.last-page').addClass('disabled');
		}
		$('.current-page').text(s3io_pointer + 1);
		$('.prev-page').removeClass('disabled');
		$('.first-page').removeClass('disabled');
		return false;
	});
	$('.prev-page').click(function() {
		if ($(this).hasClass('disabled')) {
			return false;
		}
		s3io_pointer--;
		if (0===s3io_pointer && s3io_bulk_first_page.children().length > 0) {
			$('.current-page').text(1);
			s3io_bulk_first_page.clone().replaceAll('#s3io-bulk-table tbody');
		} else {
			var s3io_table_data = {
				action: s3io_table_action,
				s3io_wpnonce: s3io_vars._wpnonce,
				s3io_offset: s3io_pointer,
				s3io_skew: s3io_table_skew,
			};
			$.post(ajaxurl, s3io_table_data, function(response) {
				$('#s3io-bulk-table tbody').html(s3ioParseTableResponse(response));
			});
		}
		if (!s3io_pointer) {
			$('.prev-page').addClass('disabled');
			$('.first-page').addClass('disabled');
		}
		$('.current-page').text(s3io_pointer + 1);
		$('.next-page').removeClass('disabled');
		$('.last-page').removeClass('disabled');
		return false;
	});
	$('.last-page').click(function() {
		if ($(this).hasClass('disabled')) {
			return false;
		}
		s3io_pointer = s3io_total_pages - 1;
		var s3io_table_data = {
			action: s3io_table_action,
			s3io_wpnonce: s3io_vars._wpnonce,
			s3io_offset: s3io_pointer,
			s3io_skew: s3io_table_skew,
		};
		$.post(ajaxurl, s3io_table_data, function(response) {
			$('#s3io-bulk-table tbody').html(s3ioParseTableResponse(response));
		});
		$('.next-page').addClass('disabled');
		$('.last-page').addClass('disabled');
		$('.current-page').text(s3io_pointer + 1);
		$('.prev-page').removeClass('disabled');
		$('.first-page').removeClass('disabled');
		return false;
	});
	$('.first-page').click(function() {
		if ($(this).hasClass('disabled')) {
			return false;
		}
		s3io_pointer = 0;
		if (s3io_bulk_first_page.children().length > 0) {
			$('.current-page').text(1);
			s3io_bulk_first_page.clone().replaceAll('#s3io-bulk-table tbody');
		} else {
			var s3io_table_data = {
				action: s3io_table_action,
				s3io_wpnonce: s3io_vars._wpnonce,
				s3io_offset: s3io_pointer,
				s3io_skew: s3io_table_skew,
			};
			$.post(ajaxurl, s3io_table_data, function(response) {
				$('#s3io-bulk-table tbody').html(s3ioParseTableResponse(response));
			});
		}
		$('.prev-page').addClass('disabled');
		$('.first-page').addClass('disabled');
		$('.current-page').text(s3io_pointer + 1);
		$('.next-page').removeClass('disabled');
		$('.last-page').removeClass('disabled');
		return false;
	});
	$('#s3io-bulk-table').on('click', '.s3io-removeimage', function() {
		var imageID = $(this).data('s3io-id');
		var s3io_image_removal = {
			action: 's3io_table_remove',
			s3io_wpnonce: s3io_vars._wpnonce,
			s3io_image_id: imageID,
		};
		$.post(ajaxurl, s3io_image_removal, function(response) {
			if(response == '1') {
				$('.s3io-image-' + imageID).remove();
				s3io_vars.image_count--;
				$('.displaying-num .s3io-table-count').text(s3ioNumberFormat.format(s3io_vars.image_count));
			} else {
				alert(s3io_vars.remove_failed);
			}
		});
	});
	function s3ioParseTableResponse(response) {
		try {
			var s3io_response = JSON.parse(response);
		} catch (err) {
			console.log(err);
			console.log(response);
			return '<tr><td colspan="3" style="color:red;font-weight:bold">' + s3io_vars.invalid_response + '</td></tr>';
		}
		return s3io_response.output;
	}
	function s3ioUpdateBulkTable(image_row) {
		$('.s3io-table').show();
		s3io_vars.image_count++;
		if (s3io_vars.image_count >= 50) {
			s3io_total_pages = Math.ceil(s3io_vars.image_count / 50);
			$('.tablenav').show();
			$('.next-page').show();
			$('.last-page').show();
			$('.total-pages').text(s3ioNumberFormat.format(s3io_total_pages));
		}
		$('.displaying-num .s3io-table-count').text(s3ioNumberFormat.format(s3io_vars.image_count));
		// We store a copy of the last 50 image records in s3io_bulk_first_page, and need to use it here,
		// in case they are on a page other than the first one.
		var isAlternate = s3io_bulk_first_page.children().first().hasClass('alternate');
		image_row = $(image_row);
		if (! isAlternate) {
			image_row.addClass('alternate');
		}
		s3io_table_skew = 0;
		if (s3io_pointer === 0) {
			image_row.prependTo('#s3io-bulk-table tbody').hide().fadeIn(400,function() {
				if ($('#s3io-bulk-table tbody').children().length > 50) {
					// Remove the last row, as it belongs on the next page now.
					$('#s3io-bulk-table tbody').children().last().remove();
				}
				s3io_bulk_first_page = $('#s3io-bulk-table tbody').clone();
				if (s3io_bulk_first_page.children().length < 50) {
					s3io_table_skew = s3io_bulk_first_page.children().length;
				}
				$('.current-page').text(1);
			});
		} else {
			s3io_bulk_first_page.prepend(image_row);
			if (s3io_bulk_first_page.children().length > 50) {
				// Remove the last row, as it belongs on the next page now.
				s3io_bulk_first_page.children().last().remove();
			} else if (s3io_bulk_first_page.children().length < 50) {
				s3io_table_skew = s3io_bulk_first_page.children().length;
			}
		}
	}
	$('#s3io-scan').submit(function() {
		$('.s3io-bulk-form').hide();
		$('.s3io-bulk-info').hide();
		$('#s3io-bulk-loading').html(s3io_vars.starting_scan);
		if ($('#s3io-force:checkbox:checked').val()) {
			s3io_force = 1;
		}
		if ($('#s3io-webp-only:checkbox:checked').val()) {
			s3io_webp_only = 1;
		}
		s3ioScanBuckets();
		return false;
	});
	function s3ioScanBuckets() {
		var s3io_scan_data = {
			action: s3io_scan_action,
			s3io_force: s3io_force,
			s3io_webp_only: s3io_webp_only,
			s3io_wpnonce: s3io_vars._wpnonce,
		};
		$.post(ajaxurl, s3io_scan_data, function(response) {
			var s3io_response = JSON.parse(response);
			if (s3io_response.error) {
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_response.error + '</b></p>');
			} else if (s3io_response.current) {
				s3io_checked += s3io_response.completed;
				$('#s3io-bulk-loading').html('<p>' + s3io_response.current + '<br>' + s3io_vars.completed_string + '</p>');
				$('#s3io-completed-count').text(s3io_checked);
				s3io_error_counter = 30;
				s3ioScanBuckets();
			} else {
				if ( s3io_response.message ) {
					$('#s3io-bulk-loading').html('');
					if (s3io_response.pending > 0) {
						$('#s3io-delay-slider-form').show();
						$('#s3io-start').show();
					}
					// This resets the variable that we use to determine how many images are in the table. *Some images that
					// were formerly 'optimized' may now be pending, and should no longer be included in the total count.
					// As images go from pending to optimized, we will increase the image count, and (subsequently) the page count on the table.
					s3io_vars.image_count = s3io_response.opt_count;
					$('#s3io-found-images').text(s3io_response.message);
					$('#s3io-found-images').show();
					s3io_attachments = s3io_response.pending;
					s3io_error_counter = 30;
				} else {
					$('#s3io-bulk-loading').html('invalid response, check JS console');
				}
			}
		})
		.fail(function() {
			if (s3io_error_counter == 0) {
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_interrupted + '</b></p>');
			} else {
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.temporary_failure + ' ' + s3io_error_counter + '</b></p>');
				s3io_error_counter--;
				setTimeout(function() {
					s3ioScanBuckets();
				}, 1000);
			}
		});
	}
	$('#s3io-start').submit(function() {
		s3ioStartOpt();
		return false;
	});
	function s3ioStartOpt () {
		s3io_k = 0;
		if ( ! $('#s3io-delay').val().match( /^[1-9][0-9]*$/) ) {
			s3io_delay = 0;
		} else {
			s3io_delay = $('#s3io-delay').val();
		}
		$('#s3io-bulk-stop').show();
		$('.s3io-bulk-form').hide();
		$('.s3io-bulk-info').hide();
		$('#s3io-force-empty').hide();
		$.post(ajaxurl, s3io_init_data, function(response) {
			var s3io_init_response = JSON.parse(response);
			$('#s3io-bulk-loading').html(s3io_init_response.results);
			$('#s3io-bulk-progressbar').progressbar({ max: s3io_attachments });
			$('#s3io-bulk-counter').html(s3io_vars.optimized + ' 0/' + s3io_attachments);
			s3ioProcessImage();
		});
	}
	function s3ioProcessImage() {
		var s3io_loop_data = {
			action: s3io_loop_action,
			s3io_force: s3io_force,
			s3io_webp_only: s3io_webp_only,
			s3io_wpnonce: s3io_vars._wpnonce,
		};
		var s3io_jqxhr = $.post(ajaxurl, s3io_loop_data, function(response) {
			s3io_i++;
			var s3io_response = JSON.parse(response);
			$('#s3io-bulk-progressbar').progressbar("option", "value", s3io_i );
			$('#s3io-bulk-counter').html(s3io_vars.optimized + ' ' + s3io_i + '/' + s3io_attachments);
			if (s3io_response.error) {
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_response.error + '</p><p>' + s3io_vars.operation_stopped + '</b></p>');
			}
			else if (s3io_k == 9) {
				if ( s3io_response.results ) {
					s3ioUpdateBulkTable(s3io_response.results);
				}
				s3io_jqxhr.abort();
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_stopped + '</b></p>');
			}
			else if ( response == 0 ) {
				$('#s3io-bulk-loading').html('<p style="color: red"><b>' + s3io_vars.operation_stopped + '</b></p>');
			}
			else if (s3io_i < s3io_attachments) {
				if (s3io_response.results) {
					s3ioUpdateBulkTable(s3io_response.results);
				}
				if (s3io_response.next_file) {
					$('#s3io-bulk-loading').html(s3io_response.next_file);
				}
				if (s3io_response.new_nonce) {
					s3io_vars._wpnonce = s3io_response.new_nonce;
				}
				s3io_error_counter = 30;
				s3ioTimeoutHandler = setTimeout(s3ioProcessImage, s3io_delay * 1000);
			}
			else {
				if ( s3io_response.results ) {
					s3ioUpdateBulkTable(s3io_response.results);
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
				s3ioTimeoutHandler = setTimeout(function() {
					s3ioProcessImage();
				}, 1000);
			}
		});
	}
	$('#s3io-webp-rename').submit(function() {
		$('.s3io-tool-form').hide();
		$('.s3io-tool-info').hide();
		$('#s3io-tools-loading').show();
		$('#s3io-tools-counter').show();
		$('#s3io-tools-status').show();
		s3ioRenameWebPImages();
		return false;
	});
	var s3io_webp_rename_completed = 0;
	function s3ioRenameWebPImages() {
		var s3io_webp_rename_data = {
			action: 's3io_webp_rename_loop',
			completed: s3io_webp_rename_completed,
			s3io_wpnonce: s3io_vars._wpnonce,
		};
		$.post(ajaxurl, s3io_webp_rename_data, function(response) {
			try {
				var s3io_response = JSON.parse(response);
			} catch (err) {
				$('#s3io-tools-loading').html('<p style="color: red"><b>' + s3io_vars.invalid_response + '</b></p>');
				console.log(err);
				console.log(response);
				return false;
			}
			if (s3io_response.error) {
				$('#s3io-tools-loading').html('<p style="color: red"><b>' + s3io_response.error + '</b></p>');
				return false;
			} else if (s3io_response.counter_msg) {
				// A valid response will have 'output', a 'counter_msg' to show how many images have been completed,
				// and 'completed' with a count of how many images were done in the current request.
				s3io_webp_rename_completed += s3io_response.completed;
				$('#s3io-tools-counter').html(s3io_response.counter_msg);
				$('#s3io-tools-status').append('<p>' + s3io_response.output + '</p>');
				if (s3io_response.new_nonce) {
					s3io_vars._wpnonce = s3io_response.new_nonce;
				}
				s3io_error_counter = 30;
				// Keep going until a loop comes back with done = 1.
				if (s3io_response.done > 0) {
					$('#s3io-tools-loading').html('<b>' + s3io_vars.finished + '</b>');
				} else {
					s3ioRenameWebPImages();
				}
			} else {
				$('#s3io-tools-loading').html('<p style="color: red"><b>' + s3io_vars.invalid_response + '</b></p>');
				console.log(err);
				console.log(response);
			}
		})
		.fail(function() {
			if (s3io_error_counter == 0) {
				$('#s3io-tools-loading').html('<p style="color: red"><b>' + s3io_vars.operation_interrupted + '</b></p>');
			} else {
				$('#s3io-tools-loading').html('<p style="color: red"><b>' + s3io_vars.temporary_failure + ' ' + s3io_error_counter + '</b></p>');
				s3io_error_counter--;
				setTimeout(function() {
					s3ioRenameWebPImages();
				}, 1000);
			}
		});
	}
	$('#s3io-webp-delete').submit(function() {
		$('.s3io-tool-form').hide();
		$('.s3io-tool-info').hide();
		$('#s3io-tools-loading').show();
		$('#s3io-tools-counter').show();
		$('#s3io-tools-status').show();
		s3ioDeleteWebPImages();
		return false;
	});
	var s3io_webp_delete_completed = 0;
	function s3ioDeleteWebPImages() {
		var s3io_webp_delete_data = {
			action: 's3io_webp_delete_loop',
			completed: s3io_webp_delete_completed,
			s3io_wpnonce: s3io_vars._wpnonce,
		};
		$.post(ajaxurl, s3io_webp_delete_data, function(response) {
			try {
				var s3io_response = JSON.parse(response);
			} catch (err) {
				$('#s3io-tools-loading').html('<p style="color: red"><b>' + s3io_vars.invalid_response + '</b></p>');
				console.log(err);
				console.log(response);
				return false;
			}
			if (s3io_response.error) {
				$('#s3io-tools-loading').html('<p style="color: red"><b>' + s3io_response.error + '</b></p>');
				return false;
			} else if (s3io_response.counter_msg) {
				// A valid response will have 'output', a 'counter_msg' to show how many images have been completed,
				// and 'completed' with a count of how many images were done in the current request.
				s3io_webp_delete_completed += s3io_response.completed;
				$('#s3io-tools-counter').html(s3io_response.counter_msg);
				$('#s3io-tools-status').append('<p>' + s3io_response.output + '</p>');
				if (s3io_response.new_nonce) {
					s3io_vars._wpnonce = s3io_response.new_nonce;
				}
				s3io_error_counter = 30;
				// Keep going until a loop comes back with done = 1.
				if (s3io_response.done > 0) {
					$('#s3io-tools-loading').html('<b>' + s3io_vars.finished + '</b>');
				} else {
					s3ioDeleteWebPImages();
				}
			} else {
				$('#s3io-tools-loading').html('<p style="color: red"><b>' + s3io_vars.invalid_response + '</b></p>');
				console.log(err);
				console.log(response);
			}
		})
		.fail(function() {
			if (s3io_error_counter == 0) {
				$('#s3io-tools-loading').html('<p style="color: red"><b>' + s3io_vars.operation_interrupted + '</b></p>');
			} else {
				$('#s3io-tools-loading').html('<p style="color: red"><b>' + s3io_vars.temporary_failure + ' ' + s3io_error_counter + '</b></p>');
				s3io_error_counter--;
				setTimeout(function() {
					s3ioDeleteWebPImages();
				}, 1000);
			}
		});
	}
});
/**
 * Admin JavaScript for ABS Loja Protheus Connector
 *
 * @package ABSLoja\ProtheusConnector
 */

(function($) {
	'use strict';

	/**
	 * Test Connection Button
	 */
	$('#test-connection').on('click', function() {
		var $button = $(this);
		var $status = $('#connection-status');

		$button.prop('disabled', true);
		$status.removeClass('success error').addClass('loading').html(
			abslojaProtheus.strings.testing + ' <span class="absloja-loading"></span>'
		);

		$.ajax({
			url: abslojaProtheus.ajaxUrl,
			type: 'POST',
			data: {
				action: 'absloja_test_connection',
				nonce: abslojaProtheus.nonce
			},
			success: function(response) {
				if (response.success) {
					$status.removeClass('loading').addClass('success').html(
						'✓ ' + response.data.message
					);
				} else {
					$status.removeClass('loading').addClass('error').html(
						'✗ ' + response.data.message
					);
				}
			},
			error: function() {
				$status.removeClass('loading').addClass('error').html(
					'✗ ' + abslojaProtheus.strings.error + ' Connection failed'
				);
			},
			complete: function() {
				$button.prop('disabled', false);
			}
		});
	});

	/**
	 * Sync Catalog Now Button
	 */
	$('#sync-catalog-now').on('click', function() {
		var $button = $(this);
		var $result = $('#sync-result');

		$button.prop('disabled', true);
		$result.removeClass('success error').html(
			abslojaProtheus.strings.syncing + ' <span class="absloja-loading"></span>'
		);

		$.ajax({
			url: abslojaProtheus.ajaxUrl,
			type: 'POST',
			data: {
				action: 'absloja_sync_catalog',
				nonce: abslojaProtheus.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.addClass('success').html(
						'✓ ' + response.data.message
					);
					// Reload page after 2 seconds to update stats
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					$result.addClass('error').html(
						'✗ ' + response.data.message
					);
				}
			},
			error: function() {
				$result.addClass('error').html(
					'✗ ' + abslojaProtheus.strings.error + ' Sync failed'
				);
			},
			complete: function() {
				$button.prop('disabled', false);
			}
		});
	});

	/**
	 * Sync Stock Now Button
	 */
	$('#sync-stock-now').on('click', function() {
		var $button = $(this);
		var $result = $('#sync-result');

		$button.prop('disabled', true);
		$result.removeClass('success error').html(
			abslojaProtheus.strings.syncing + ' <span class="absloja-loading"></span>'
		);

		$.ajax({
			url: abslojaProtheus.ajaxUrl,
			type: 'POST',
			data: {
				action: 'absloja_sync_stock',
				nonce: abslojaProtheus.nonce
			},
			success: function(response) {
				if (response.success) {
					$result.addClass('success').html(
						'✓ ' + response.data.message
					);
					// Reload page after 2 seconds to update stats
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					$result.addClass('error').html(
						'✗ ' + response.data.message
					);
				}
			},
			error: function() {
				$result.addClass('error').html(
					'✗ ' + abslojaProtheus.strings.error + ' Sync failed'
				);
			},
			complete: function() {
				$button.prop('disabled', false);
			}
		});
	});

	/**
	 * Manual Retry Button
	 */
	$(document).on('click', '.retry-now', function() {
		if (!confirm(abslojaProtheus.strings.confirmRetry)) {
			return;
		}

		var $button = $(this);
		var retryId = $button.data('retry-id');

		$button.prop('disabled', true).text('Processing...');

		$.ajax({
			url: abslojaProtheus.ajaxUrl,
			type: 'POST',
			data: {
				action: 'absloja_manual_retry',
				nonce: abslojaProtheus.nonce,
				retry_id: retryId
			},
			success: function(response) {
				if (response.success) {
					alert(abslojaProtheus.strings.success + ' ' + response.data.message);
					location.reload();
				} else {
					alert(abslojaProtheus.strings.error + ' ' + response.data.message);
					$button.prop('disabled', false).text('Retry Now');
				}
			},
			error: function() {
				alert(abslojaProtheus.strings.error + ' Request failed');
				$button.prop('disabled', false).text('Retry Now');
			}
		});
	});

	/**
	 * Export Logs Button
	 */
	$('#export-logs').on('click', function() {
		if (!confirm(abslojaProtheus.strings.confirmExport)) {
			return;
		}

		var $button = $(this);
		var filters = {
			action: 'absloja_export_logs',
			nonce: abslojaProtheus.nonce
		};

		// Get filter values from URL
		var urlParams = new URLSearchParams(window.location.search);
		if (urlParams.get('date_from')) {
			filters.date_from = urlParams.get('date_from');
		}
		if (urlParams.get('date_to')) {
			filters.date_to = urlParams.get('date_to');
		}
		if (urlParams.get('type')) {
			filters.type = urlParams.get('type');
		}
		if (urlParams.get('status')) {
			filters.status = urlParams.get('status');
		}

		$button.prop('disabled', true).text('Exporting...');

		$.ajax({
			url: abslojaProtheus.ajaxUrl,
			type: 'POST',
			data: filters,
			success: function(response) {
				if (response.success) {
					// Create download link
					var csvContent = atob(response.data.content);
					var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
					var link = document.createElement('a');
					var url = URL.createObjectURL(blob);
					
					link.setAttribute('href', url);
					link.setAttribute('download', response.data.filename);
					link.style.visibility = 'hidden';
					document.body.appendChild(link);
					link.click();
					document.body.removeChild(link);
					
					alert(abslojaProtheus.strings.success + ' Logs exported');
				} else {
					alert(abslojaProtheus.strings.error + ' ' + response.data.message);
				}
			},
			error: function() {
				alert(abslojaProtheus.strings.error + ' Export failed');
			},
			complete: function() {
				$button.prop('disabled', false).text('Export to CSV');
			}
		});
	});

	/**
	 * View Log Details Button
	 */
	$(document).on('click', '.view-log-details', function() {
		var logId = $(this).data('log-id');
		
		// For now, just show a simple modal with log ID
		// In a full implementation, this would fetch and display full log details
		var $modal = $('#log-details-modal');
		var $body = $('#log-details-body');
		
		$body.html('<p>Loading log details for ID: ' + logId + '...</p>');
		$modal.show();
		
		// TODO: Implement AJAX call to fetch full log details
		// For MVP, we'll just show the log ID
		setTimeout(function() {
			$body.html(
				'<p><strong>Log ID:</strong> ' + logId + '</p>' +
				'<p><em>Full log details would be displayed here in production.</em></p>' +
				'<p>This would include:</p>' +
				'<ul>' +
				'<li>Complete payload</li>' +
				'<li>Full response</li>' +
				'<li>Error stack trace (if applicable)</li>' +
				'<li>Context data</li>' +
				'</ul>'
			);
		}, 500);
	});

	/**
	 * Close Log Details Modal
	 */
	$('.close-modal').on('click', function() {
		$('#log-details-modal').hide();
	});

	// Close modal when clicking outside
	$(window).on('click', function(event) {
		if (event.target.id === 'log-details-modal') {
			$('#log-details-modal').hide();
		}
	});

	/**
	 * Generate Random Token/Secret
	 */
	function generateRandomString(length) {
		var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		var result = '';
		for (var i = 0; i < length; i++) {
			result += chars.charAt(Math.floor(Math.random() * chars.length));
		}
		return result;
	}

	$('#generate-webhook-token').on('click', function() {
		var token = generateRandomString(32);
		$('#absloja_protheus_webhook_token').val(token);
	});

	$('#generate-webhook-secret').on('click', function() {
		var secret = generateRandomString(64);
		$('#absloja_protheus_webhook_secret').val(secret);
	});

	/**
	 * Toggle Auth Fields Based on Type
	 */
	$('#absloja_protheus_auth_type').on('change', function() {
		if ($(this).val() === 'basic') {
			$('#basic-auth-fields').show();
			$('#oauth2-fields').hide();
		} else {
			$('#basic-auth-fields').hide();
			$('#oauth2-fields').show();
		}
	});

	/**
	 * Add Category Mapping Row
	 */
	$('#add-category-mapping').on('click', function() {
		var $container = $('#category-mappings');
		var $firstRow = $container.find('.category-mapping-row').first();
		
		if ($firstRow.length) {
			var $newRow = $firstRow.clone();
			$newRow.find('input').val('');
			$newRow.find('select').val('');
			$container.append($newRow);
		}
	});

	/**
	 * Remove Category Mapping Row
	 */
	$(document).on('click', '.remove-mapping', function() {
		var $container = $('#category-mappings');
		if ($container.find('.category-mapping-row').length > 1) {
			$(this).closest('.category-mapping-row').remove();
		} else {
			alert('At least one mapping row must remain');
		}
	});

})(jQuery);

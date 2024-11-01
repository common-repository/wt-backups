<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}
?>
<div class="popup-overlay" id="confirm-popup">

	<div class="popup-content" style="position: relative; width: auto; margin: auto; border: 1px solid rgb(187, 187, 187); padding: 5px; border-radius: 10px;">
		<div class="confirmation-dialog">
			<h2 class="confirmation-dialog__title">Are you sure?</h2>
			<p class="confirmation-dialog__text"><?php echo esc_html($variables['message'])?></p>
			<div class="confirmation-dialog__buttons-wrapper">
				<button class="wt-button wt-button--red wt-button--size-300 wt-button--padded wt-font-700 confirmation-dialog__button" id="wt-continue"
                        data-action="<?php echo esc_html($variables['action'])?>"
                        data-value="<?php echo esc_html($variables['value'])?>"
                        data-nonce="<?php echo esc_html($variables['page_nonce'])?>"
                >Continue</button>
				<button class="wt-button wt-button--success wt-button--size-300 wt-button--padded wt-font-700 confirmation-dialog__button" id="wt-cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>
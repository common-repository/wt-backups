<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}
?>
<?php if(isset($variables) and array_key_exists('notifications', $variables) and is_array($variables['notifications'])): ?>
    <?php foreach ($variables['notifications'] as $notice): ?>
        <?php if(is_array($notice) and $notice):?>
        <div class="wt_backups_alert wt_card" id="wt_backups_alert_<?php echo esc_html($notice['id'])?>">
            <div class="wt_backups_alert__desc">
                <div class="wt_backups_alert__img">
                    <img src="<?php echo esc_html($variables['images_path']); ?><?php echo esc_html($notice['image'])?>">
                </div>
                <div class="wt_backups_alert__title <?php echo esc_html($notice['class'])?>"><?php echo esc_html($notice['type'])?>: </div>
                <p class="wt_backups_alert__text"><?php echo esc_html($notice['text'])?></p>
            </div>
            <div class="wt_backups_alert__close" ></div>
        </div>
        <?php endif; ?>
	<?php endforeach; ?>

<?php endif; ?>
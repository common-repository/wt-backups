<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}
?>
<?php if ( $variables['storages'] ) : ?>
	<?php foreach ( $variables['storages'] as $key => $storages ): ?>
		<tr class="text-base text-gray-500">
            <td class="py-2.5 pl-8 text-main">
                <input type="checkbox" value="<?php echo esc_html($key) ?>" name="storages[]" <?php if( isset($variables['backup_settings']['storages']) and in_array($key, $variables['backup_settings']['storages'])):?> checked <?php endif;?>>
            </td>
            <td class="py-2.5 pl-8 text-main"><?php echo esc_html($storages['type']) ?></td>
			<td class="py-2.5"><?php echo esc_html($storages['dest']) ?></td>
			<td class="flex justify-end gap-7 py-2.5 pr-8 text-right">
				<button class="flex items-center gap-3 remove_storage" data-key="<?php echo esc_html($key)?>" data-nonce="<?php echo esc_html(wp_create_nonce('wt_backups_remove_storage_nonce'))?>">
					<img src="<?php echo esc_html($variables['images_path']);?>trash.svg" alt=""> Delete
				</button>
			</td>
		</tr>
	<?php endforeach;  ?>
<?php endif; ?>
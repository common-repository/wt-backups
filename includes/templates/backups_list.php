<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<?php if ( $variables['available_backups'] ) : ?>

    <?php foreach ( $variables['available_backups'] as $backup ): ?>
		<tr class="text-base text-gray-500">
			<td class="py-2.5 pl-8 text-main"><?php echo esc_html( ($backup['name'] == $backup['zip_name']) ? $backup['name'] : $backup['zip_name'] . ' [ ' . $backup['name'] .' ]') ?><br>
                <span style="color: #777; font-size: 13px"><?php echo esc_html($backup['list_of_elements']) ?>
            </td>
			<td class="py-2.5"><?php echo esc_html($backup['date']) ?></td>
			<td class="py-2.5"><?php echo esc_html($backup['filesize']) ?>  <?php if($backup['files']):?>[ <?php echo esc_html($backup['files']) ?> ]<?php endif; ?></td>
			<td class="flex justify-end gap-7 py-2.5 pr-8 text-right">
				<button class="flex items-center gap-3 open-popup" data-file="<?php echo esc_html($backup['zip_name']) ?>" data-nonce="<?php echo esc_html(wp_create_nonce('wt_backups_open_popup_' . $backup['zip_name'])); ?>"  data-action="restore_page">
					<img src="<?php echo esc_html($variables['images_path']);?>recover.svg" alt="" style="width: 20px"> Recover
				</button>
				<a class="flex items-center gap-3" href="<?php echo esc_html($backup['url']) ?>" download>
					<img src="<?php echo esc_html($variables['images_path']);?>duplicate.svg" alt=""> Download
				</a>
				<button class="flex items-center gap-3 open-popup" data-file="<?php echo esc_html($backup['zip_name']) ?>" data-nonce="<?php echo esc_html(wp_create_nonce('wt_backups_open_popup_' . $backup['zip_name'])); ?>" data-action="delete_backup">
					<img src="<?php echo esc_html($variables['images_path']);?>trash.svg" alt=""> Delete
				</button>
			</td>
		</tr>
	<?php endforeach;  ?>
<?php endif; ?>
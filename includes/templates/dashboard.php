<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}
?>


<section class="mb-8 mt-10 flex items-center justify-between px-8">
    <div class="flex flex-col gap-2.5">
        <h2 class="text-2xl font-bold text-main">Backup</h2>
        <p class="text-sm text-gray-400">
            Create, edit or delete your Backups. And be calm.<br>
            Number of backups: <span id="backups_count"><?php echo esc_html($variables['backups_count'])?></span>
        </p>
    </div>
    <div>
        <a href="<?php echo esc_html($variables['menu_url'])?>_create_backup" class="rounded-md bg-main px-6 py-3 text-white">Create backup</a>
        <br>
        <label class="input-file">
            <input type="file" id="js-file" data-nonce="<?php echo esc_html(wp_create_nonce('wt_backups_upload_backup_nonce'))?>">

            <span>Upload backup</span>
            <div id="upload_file_message"></div>
        </label>
    </div>


</section>
<div class="h-96 overflow-y-scroll" style="height: 28rem;">
    <table class="w-full">
        <thead style="position: sticky; top: -1px;">
        <tr class="border-y border-y-gray-200 bg-gray-50" >
            <th class="py-2.5 pl-8 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Name</th>
            <th class="py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Creation date</th>
            <th class="py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">File size [count]</th>
            <th class="py-2.5 pr-8 text-right text-xs font-semibold uppercase tracking-wider text-gray-400">Action</th>
        </tr>
        </thead>
        <tbody class="divide-y divide-gray-200"  id="backups_list_wrap">
                <?php include 'backups_list.php'?>
        </tbody>
    </table>
</div>
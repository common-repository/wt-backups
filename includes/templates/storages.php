<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}
?>

<section id="backup-create">
	<div class="divide-x-gray-200 mb-4 flex divide-x border-b border-b-gray-200">
		<div class="flex-1 pb-7 pt-6">
			<h3 class="mb-5 text-sm font-medium text-gray-700">
				Storages
			</h3>

			<div class="mb-8">
				<table class="w-full">
					<thead>
					<tr class="border-y border-y-gray-200 bg-gray-50">
						<th class="py-2.5 pl-8 text-left text-xs font-semibold uppercase tracking-wider text-gray-400"></th>
						<th class="py-2.5 pl-8 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Type</th>
						<th class="py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-400">Destination</th>
						<th class="py-2.5 pr-8 text-right text-xs font-semibold uppercase tracking-wider text-gray-400">Action</th>
					</tr>
					</thead>
					<tbody class="divide-y divide-gray-200"  id="storages_list_wrap">
					<?php include 'storages_list.php'?>
					</tbody>
				</table>
			</div>

			<a href="<?php echo esc_html($variables['menu_url']);?>_add_storage" id="add-storage" class="flex-1  rounded-md bg-main px-10 py-2.5 text-white astro-AC7ZQQ4B">
                Add storage
			</a>

		</div>

	</div>

</section>
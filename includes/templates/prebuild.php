<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}

?>

<div class="container pb-40">

    <div class="rounded-xl bg-white shadow-card pt-8">

        <div class="flex items-center justify-between border-b border-b-gray-200 pb-8 pl-6 pr-8">
            <div class="flex flex-col gap-2.5">
                <h2 class="text-2xl font-bold text-main">PreBuild Scanning results</h2>
                <p class="text-sm text-gray-400">
                    We check the statistics to make a backup procedure
                </p>
            </div>
            <div>
                <a href="<?php echo esc_html($variables['menu_url']);?>_create_backup" class="rounded-md bg-main px-8 py-2 text-sm text-white" style="margin-right: 10px">Go back</a>
                <button class="rounded-md bg-main px-8 py-2 text-sm text-white" id="start_building" data-nonce="<?php echo esc_html($variables['page_nonce'])?>">Start building</button>
            </div>

        </div>
        <div class="divide-y-gray-200 flex flex-col divide-y astro-OQJBS5YV">
            <details class="astro-OQJBS5YV" open>
                <summary class="border-b border-b-transparent px-6 py-2.5 astro-OQJBS5YV">
                    <div class="flex justify-between astro-OQJBS5YV">
                        <span class="text-lg font-medium text-main astro-OQJBS5YV">Backup</span>
                        <div class="flex items-center astro-OQJBS5YV">
                            <img src="<?php echo esc_html($variables['images_path']);?>chevron.svg" alt="" class="chevron ml-6 astro-OQJBS5YV">
                        </div>
                    </div>
                </summary>
                <div class="bg-gray-50 px-6 pt-3 text-gray-500 astro-OQJBS5YV pb-5">
                    <?php echo wp_kses($variables['backupChecks']['backup_info'], 'post');?>
                </div>
            </details>
            <details class="astro-OQJBS5YV" open>
                <summary class="border-b border-b-transparent px-6 py-2.5 astro-OQJBS5YV">
                    <div class="flex justify-between astro-OQJBS5YV">
                        <span class="text-lg font-medium text-main astro-OQJBS5YV">Hosting</span>
                        <div class="flex items-center astro-OQJBS5YV">
                            <span class="text-sm font-medium text-gray-500 astro-OQJBS5YV"><?php echo esc_html($variables['disk_free_space'])?></span>
                            <span class="ml-4 rounded-xl bg-<?php echo esc_html($variables['hosting_status']['class'])?>-100 px-3 py-0.5 font-medium text-<?php echo esc_html($variables['hosting_status']['class'])?>-800 astro-OQJBS5YV">
                                <?php echo esc_html($variables['hosting_status']['text']);?>
                            </span>
                            <img src="<?php echo esc_html($variables['images_path']);?>chevron.svg" alt="" class="chevron ml-6 astro-OQJBS5YV">
                        </div>
                    </div>
                </summary>
                <div class="bg-gray-50 px-6 pt-3 text-gray-500 astro-OQJBS5YV pb-5">
                    <?php echo wp_kses($variables['backupChecks']['hosting_info'], 'post');?>
                </div>
            </details>

            <details class="astro-OQJBS5YV" open>
                <summary class="border-b border-b-transparent px-6 py-2.5 astro-OQJBS5YV">
                    <div class="flex justify-between astro-OQJBS5YV">
                        <span class="text-lg font-medium text-main astro-OQJBS5YV">Database</span>
                        <div class="flex items-center astro-OQJBS5YV">
                            <span class="text-sm font-medium text-gray-500 astro-OQJBS5YV"></span>
                            <span class="ml-4 rounded-xl bg-green-100 px-3 py-0.5 font-medium text-green-800 astro-OQJBS5YV">
                                Good
                            </span>
                            <img src="<?php echo esc_html($variables['images_path']);?>chevron.svg" alt="" class="chevron ml-6 astro-OQJBS5YV">
                        </div>
                    </div>
                </summary>
                <div class="bg-gray-50 px-6 pt-3 text-gray-500 astro-OQJBS5YV pb-5">
                    <?php echo wp_kses($variables['backupChecks']['db_info'], 'post')?>
                </div>
            </details>

            <?php if ($variables['backupChecks']['storages_info']['list']) :?>
            <details class="astro-OQJBS5YV" open>
                <summary class="border-b border-b-transparent px-6 py-2.5 astro-OQJBS5YV">
                    <div class="flex justify-between astro-OQJBS5YV">
                        <span class="text-lg font-medium text-main astro-OQJBS5YV">Storages</span>
                        <div class="flex items-center astro-OQJBS5YV">
                            <span class="text-sm font-medium text-gray-500 astro-OQJBS5YV"></span>
                            <span class="ml-4 rounded-xl bg-<?php echo esc_html($variables['storages_status']['class'])?>-100 px-3 py-0.5 font-medium text-<?php echo esc_html($variables['storages_status']['class'])?>-800 astro-OQJBS5YV">
                                 <?php echo esc_html($variables['storages_status']['text']);?>
                            </span>
                            <img src="<?php echo esc_html($variables['images_path']);?>chevron.svg" alt="" class="chevron ml-6 astro-OQJBS5YV">
                        </div>
                    </div>
                </summary>
                <div class="bg-gray-50 px-6 pt-3 text-gray-500 astro-OQJBS5YV pb-5">
                        <?php echo wp_kses($variables['backupChecks']['storages_info']['list'], 'post') ?>
                </div>
            </details>
            <?php endif; ?>
        </div>

    </div>

</div>
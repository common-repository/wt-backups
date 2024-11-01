<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}


?>
<div id="wt_backups_vars"
     data-maxfile="<?php echo esc_html($variables['max_file_upload_in_bytes']);?>"
     data-page="<?php echo esc_html($variables['page']);?>"
     data-notifications="<?php echo esc_html($variables['notifications_raw']);?>"
></div>
<div style="width: 1080px; margin: 20px auto;">
    <div class="wt_backups_notifications_wrapper" id="wt_backups_notifications">
        <?php include 'notifications.php'?>
    </div>
</div>

<header class="bg-gradient-to-r from-accent to-main text-white" style="margin-left: -20px; padding: 0 25px;">
    <div class="container flex items-center justify-between pb-24 pt-8">
        <h1 class="flex items-center gap-4 text-[0px]">
            WebTotem Backups
            <img src="<?php echo esc_html($variables['images_path']);?>logo.svg" alt="">
            <span class="text-sm" style="color: #fff">Version 1.0.0</span>
        </h1>
        <a href="mailto:support@wtotem.com" class="rounded-sm bg-faded px-3 py-[6px] text-sm">Need help?</a>
    </div>
</header>

<div class="container -translate-y-16 pb-40 wt-backups-wrap" id="wt-backups-wrap">

    <main style="width: calc(100% - 20px);">
        <?php if($variables['nav_tabs']):?>
        <div class="rounded-xl bg-white shadow-card pb-5 min-h-[408px]" >
            <nav class="border-b border-b-gray-200 px-8 pt-5 wt-backups-nav">
                <ul class="flex gap-10">
                    <li>
                        <a class="block pb-4 font-medium text-sm relative after:-bottom-[1px] after:h-0.5 after:left-0 after:absolute after:w-full
                        <?php if(array_key_exists('dashboard', $variables['is_active'])):;?>text-main after:bg-main <?php else: ?>text-gray-500<?php endif;?>"
                           href="<?php echo esc_html($variables['menu_url']);?>">
                            Backups
                        </a>
                    </li>
                    <li>
                        <a class="block pb-4 font-medium text-sm relative after:-bottom-[1px] after:h-0.5 after:left-0 after:absolute after:w-full
                        <?php if(array_key_exists('settings', $variables['is_active'])):;?>text-main after:bg-main <?php else: ?>text-gray-500<?php endif;?>"
                           href="<?php echo esc_html($variables['menu_url']);?>_settings">
                            Settings
                        </a>
                    </li>
                    <li>
                        <a class="block pb-4 font-medium text-sm relative after:-bottom-[1px] after:h-0.5 after:left-0 after:absolute after:w-full
                        <?php if(array_key_exists('support', $variables['is_active'])):;?>text-main after:bg-main <?php else: ?>text-gray-500<?php endif;?>"
                           href="<?php echo esc_html($variables['menu_url']);?>_support">
                            Support
                        </a>
                    </li>

                </ul>
            </nav>

            <?php  if(isset($variables) and array_key_exists('content', $variables)) { echo wp_kses($variables['content'], 'wt_backups'); } ?>
        </div>
        <?php else: ?>

            <?php  if(isset($variables) and array_key_exists('content', $variables)) { echo wp_kses($variables['content'], 'wt_backups'); } ?>

        <?php endif; ?>
    </main>

</div>

<?php include 'footer.php'?>

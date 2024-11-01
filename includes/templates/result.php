<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}
?>

<div class="rounded-xl bg-white shadow-card h-[250px] flex flex-col items-center justify-center gap-5 mb-4">

	<img src="<?php echo esc_html($variables['images_path']);?>success.svg" alt="">
	<div class="flex flex-col gap-2 text-center">
		<h2 class="text-lg font-bold leading-6 text-main">
            <?php echo esc_html($variables['title'])?>
		</h2>
		<p class="text-sm leading-5 text-gray-500">
            <?php echo esc_html($variables['text'])?>
		</p>
	</div>
	<a href="<?php echo esc_html($variables['go_home'])?>" class="rounded-[5px] bg-main px-6 py-3 text-sm leading-4 text-white">
		To the list of backups
	</a>

</div>

<div class="rounded-xl bg-white shadow-card p-4">

    <section class="rounded-xl bg-gray-50 pb-2 pl-8 pr-6 pt-6">
        <h2 class="mb-3 font-bold text-main">Process log</h2>
        <div class="log-container flex h-[300px] flex-col gap-3 text-sm" id="logger" data-autoscroll="1"><?php echo wp_kses($variables['logger'], 'post')?></div>
    </section>

</div>

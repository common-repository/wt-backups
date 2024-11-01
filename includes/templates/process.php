<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    exit(1);
}
?>

<div class="rounded-xl bg-white shadow-card h-[250px] flex items-center justify-center mb-5">

    <div class="flex w-1/2 flex-col items-center gap-2.5">
        <div role="progressbar"
             class="relative h-[18px] w-full overflow-hidden rounded-full border-2 border-main bg-main astro-JX4NC67J"
             aria-valuenow="25">
            <div class="progress absolute bottom-0 left-0 top-0 h-full w-full bg-black astro-JX4NC67J"
                 style="translate: <?php echo esc_html((int)$variables['progress'] - 100) ?>% 0">
            </div>
        </div>
        <h2 class="text-main">Waiting few minutes</h2>
    </div>

</div>
<div class="rounded-xl bg-white shadow-card p-4">

    <section class="rounded-xl bg-gray-50 pb-2 pl-8 pr-6 pt-6">
        <h2 class="mb-3 font-bold text-main">Process log</h2>
        <div class="log-container flex h-[300px] flex-col gap-3 text-sm" id="logger"
             data-nonce="<?php echo esc_html($variables['page_nonce'])?>"
             data-file="<?php echo esc_html($variables['file'])?>"
             data-process="<?php echo esc_html($variables['process'])?>"
             data-autoscroll=1><?php echo wp_kses($variables['logger'], 'post') ?></div>
    </section>

</div>

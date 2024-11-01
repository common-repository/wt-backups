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
    <div style="text-align: center; width: 100%;">
        <h2 style="font-size: 20px; font-weight: bold;">Having problems? Contact us by	</h2>
        <br>

        <div style="margin: 5px 0;">
            <a href="mailto:support@wtotem.com" class="rounded-[5px] bg-main px-6 py-3 text-sm leading-4 text-white">
                mail
            </a>
        </div>
        <br>
        <h2 style="font-size: 20px; font-weight: bold;">or</h2>

        <br>

        <div style="margin: 5px 0;">
            <a href="https://wtotem.com/faq/" class="rounded-[5px] bg-main px-6 py-3 text-sm leading-4 text-white">
                see Questions & Answers
            </a>
        </div>
    </div>
</section>
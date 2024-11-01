<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly?>
<footer class="absolute bottom-0 left-0 right-0 bg-bg pb-7" style="margin-bottom: 17px; background: #f0f0f1;">
    <div class="container">
        <div class="mb-9 flex items-center justify-between border-b border-b-gray-200 pb-6">
            <div class="flex items-center gap-3">
                <img src="<?php echo esc_html($variables['images_path']);?>logo-blue.svg" alt="WebTotem logo.">
                <p class="font-medium text-footer">
                    Your best friend in cybersecurity world
                </p>
            </div>
            <ul class="flex gap-5">
                <li>
                    <a href="https://www.linkedin.com/company/wtotem/"><img src="<?php echo esc_html($variables['images_path']);?>linked-in.svg" alt="LinkedIn."></a>
                </li>
                <li>
                    <a href="https://www.youtube.com/channel/UCD-n_NIXTOmw4Nm-LcmW1XA/featured"><img src="<?php echo esc_html($variables['images_path']);?>yt.svg" alt="YouTube."></a>
                </li>
                <li>
                    <a href="https://www.facebook.com/webtotem"><img src="<?php echo esc_html($variables['images_path']);?>fb.svg" alt="Facebook."></a>
                </li>
            </ul>
        </div>
        <div class="flex items-center justify-between">
            <p class="font-medium text-footer">
                &copy; 2017-<?php echo esc_html(gmdate('Y'));?> All rights reserved
            </p>
            <div class="flex items-center gap-4">
                <img src="<?php echo esc_html($variables['images_path']);?>visa.svg" alt="Visa.">
                <img src="<?php echo esc_html($variables['images_path']);?>mastercard.svg" alt="MasterCard.">
            </div>
        </div>
    </div>
</footer>
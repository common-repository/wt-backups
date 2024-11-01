<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    exit(1);
}
?>
<div id="vars"
     data-max_file="<?php echo esc_html($variables['max_file_upload_in_bytes']);?>"
     data-page="<?php echo esc_html($variables['page']);?>"
     data-notifications="<?php echo esc_html($variables['notifications_raw']);?>"
></div>
<div class="wt_backups_welcome-wrapper">
    <div class="wt_backups_content">
        <div class="wt_backups_container" id="wt_backups_notifications">
            <?php include 'notifications.php'?>
        </div>
    </div>
    <div class="wt_backups_welcome-wrapper__head-height">
        <div class="wt_backups_modal">
            <h2 class="h2 wt_backups_modal__subject">
                <?php esc_html__("Activate the plugin", 'wt-backups')?>
            </h2>

            <form action="" method="post" class="wt_backups_modal__window wt_card" id="wt_backups-activation-form" data-nonce="<?php echo esc_html($variables['page_nonce'])?>">
                <h3 class="wt_backups_modal__title">
                    <?php echo esc_html__("Welcome friend!", 'wt-backups')?>
                </h3>
                <p class="wt_backups_modal__text">
                    <?php echo esc_html__("Sign in to continue to WebTotem", 'wt-backups')?>
                </p>
                <div class="wt_backups_modal__block">
                    <img src="<?php echo esc_html($variables['images_path']); ?>logo-circle.svg" alt="Web Totem" class="wt_backups_modal__logo" />
                </div>
                <div class="wt_backups_modal__wrap">
                    <input name="api_key" id="api_key" type="text" class="wt_backups_modal__api-key" placeholder="<?php echo esc_html__("API-KEY code", 'wt-backups')?>" required />
                </div>
                <button class="wt_backups_modal__btn" type="submit">
                    <?php echo esc_html__("ACTIVATE", 'wt-backups')?>
                </button>
            </form>

            <p class="wt_backups_modal__desc">
                You can receive the keys in your personal account <a target="_blank" href="https://wtotem.com/cabinet/profile/keys">cabinet</a>
                 or read the activation <a target="_blank" href="https://docs.wtotem.com/plugin-for-wordpress#vy-to-activate-the-plugin">manual</a>
            </p>

        </div>
    </div>

</div>
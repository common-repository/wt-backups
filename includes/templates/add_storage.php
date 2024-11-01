<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    exit(1);
}

$link = sanitize_text_field($_SERVER['HTTP_REFERER']) ?? '';
?>

<form action="" id="backup_storage_form" method="post">

<section id="backup-create" class="pl-8">
    <div class="divide-x-gray-200 mb-4 flex divide-x border-b border-b-gray-200">
        <div class="flex-1 pb-7 pt-6">

            <h2 class="confirmation-dialog__title">Add storage</h2>

            <div class="flex gap-5 border-b border-b-gray-200 pt-8 pr-8 pb-7 astro-AC7ZQQ4B">
                <div class="flex gap-3 astro-AC7ZQQ4B">
                    <input type="radio" checked name="backup_storage" value="local" id="backup-storage-local" class="storage-picker astro-AC7ZQQ4B">
                    <div class="flex flex-col astro-AC7ZQQ4B">
                        <label class="text-sm font-medium leading-none text-gray-700 astro-AC7ZQQ4B" for="backup-storage-local">Local
                            <p class="text-sm text-gray-500 astro-AC7ZQQ4B">
                                You can save backups on your server
                            </p>
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 astro-AC7ZQQ4B">
                    <input type="radio" name="backup_storage" value="ftp/sftp" id="backup-storage-ftp-sftp" class="storage-picker astro-AC7ZQQ4B">
                    <div class="flex flex-col astro-AC7ZQQ4B">
                        <label class="text-sm font-medium leading-none text-gray-700 astro-AC7ZQQ4B" for="backup-storage-ftp-sftp">FTP/SFTP
                            <p class="text-sm text-gray-500 astro-AC7ZQQ4B">
                                You can save backups on remote servers
                            </p>
                        </label>
                    </div>
                </div>
                <div class="flex gap-3 astro-AC7ZQQ4B">
                    <input type="radio" name="backup_storage" value="cloud" id="backup-storage-cloud" class="storage-picker astro-AC7ZQQ4B">
                    <div class="flex flex-col astro-AC7ZQQ4B">
                        <label class="text-sm font-medium leading-none text-gray-700 astro-AC7ZQQ4B" for="backup-storage-cloud">
                            Cloud
                            <p class="text-sm text-gray-500 astro-AC7ZQQ4B">
                                You can save backups on every popular cloud storage
                            </p>
                        </label>
                    </div>
                </div>
            </div>
            <div class="storage mt-6 flex flex-col gap-1 pr-6 astro-AC7ZQQ4B" style="margin-bottom: 20px;">
                <label for="folder_path" class="pl-8 text-sm font-medium text-gray-700 astro-AC7ZQQ4B">Local folder</label>
                <div class="flex gap-1 pl-8 astro-AC7ZQQ4B">
                    <input type="text" name="folder_path" id="folder_path"
                           class="flex-[4] rounded-md border border-gray-300 px-3 py-2.5 text-gray-500 shadow-sm placeholder:text-gray-500 astro-AC7ZQQ4B"
                           value="<?php echo esc_html($variables['local_storage']) ?>">
                    <button id="check_folder_path" data-nonce="<?php echo esc_html(wp_create_nonce('wt_backups_check_folder_path_nonce'))?>"
                            class="flex-1  rounded-md bg-main px-10 py-2.5 text-white astro-AC7ZQQ4B">Check folder
                    </button>
                </div>

                <span class="pl-8 text-sm font-medium text-gray-700 astro-AC7ZQQ4B" id="check_path_result"></span>
            </div>
            <div class="storage mt-6 hidden items-end gap-2.5 pl-8 pr-6 astro-AC7ZQQ4B" style="margin-bottom: 20px;">
                <label class="flex flex-1 flex-col gap-1 text-sm font-medium text-gray-700 astro-AC7ZQQ4B">SFTP/FTP IP
                    <div class="flex justify-between gap-2 rounded-md border-gray-300 shadow-sm astro-AC7ZQQ4B">
                        <select class="flex-1 text-sm text-gray-500 astro-AC7ZQQ4B border border-gray-300" id="ftp_type" name="ftp_type">
                            <option value="ftp">FTP</option>
                            <option value="sftp">SFTP</option>
                        </select>
                    </div>
                </label>
                <label class="flex flex-1 flex-col gap-1 text-sm font-medium text-gray-700 astro-AC7ZQQ4B">SFTP/FTP IP
                    <div class="flex justify-between gap-2 rounded-md border-gray-300 shadow-sm astro-AC7ZQQ4B">
                        <input class="flex-1 text-sm text-gray-500 astro-AC7ZQQ4B" placeholder="1.1.1.1" type="text" id="ftp_host" name="ftp_host">
                        <input class="max-w-[60px] text-sm text-gray-500 astro-AC7ZQQ4B" placeholder="8080" type="number" id="ftp_port" name="ftp_port">
                    </div>
                </label>

                <label class="flex flex-1 flex-col gap-1 text-sm font-medium text-gray-700 astro-AC7ZQQ4B">
                    Path
                    <input type="text"  id="ftp_path" name="ftp_path" placeholder="root" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-500 shadow-sm astro-AC7ZQQ4B">
                </label>

                <label class="flex flex-1 flex-col gap-1 text-sm font-medium text-gray-700 astro-AC7ZQQ4B">
                    User
                    <input type="text"  id="ftp_user" name="ftp_user" placeholder="root" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-500 shadow-sm astro-AC7ZQQ4B">
                </label>
                <label class="flex flex-1 flex-col gap-1 text-sm font-medium text-gray-700 astro-AC7ZQQ4B">Password
                    <input type="password" id="ftp_password" name="ftp_password" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-500 shadow-sm astro-AC7ZQQ4B">
                </label>
                <div class="ml-auto flex flex-col gap-1 astro-AC7ZQQ4B">
                    <div class="astro-AC7ZQQ4B">&nbsp;</div>
                    <button class="whitespace-nowrap rounded-md border border-transparent bg-main px-4 py-2 text-sm text-white astro-AC7ZQQ4B" type="submit" id="check_ftp_connection" data-nonce="<?php echo esc_html(wp_create_nonce('wt_backups_check_ftp_nonce'))?>">Check connection</button>
                </div>
            </div>
            <div class="storage mt-6 hidden flex-col pl-8 pr-6 astro-AC7ZQQ4B" style="margin-bottom: 20px;"  id="tabs">
                    <div class="mb-8 flex gap-3 astro-AC7ZQQ4B tabs-nav" >
                        <label class="cloud-label flex items-center justify-center rounded-xl px-2.5 astro-AC7ZQQ4B" data-id="google">
                            <input type="radio" name="cloud-storage" id="" class="hidden astro-AC7ZQQ4B">
                            <img src="<?php echo esc_html($variables['images_path']);?>google-cloud.png" alt="" class="astro-AC7ZQQ4B">
                        </label>
                        <label class="cloud-label flex items-center justify-center rounded-xl astro-AC7ZQQ4B" data-id="dropbox">
                            <input type="radio" name="cloud-storage" id="" class="hidden astro-AC7ZQQ4B">
                            <img src="<?php echo esc_html($variables['images_path']);?>dropbox.png" alt="" class="astro-AC7ZQQ4B">
                        </label>
                    </div>

                <div class="flex flex-col gap-4 astro-AC7ZQQ4B tabs-items" >
                    <div class="flex astro-AC7ZQQ4B" id="google">
                        <div class="flex flex-1 gap-1 astro-AC7ZQQ4B">
                            <div class="flex flex-1 flex-col gap-1 astro-AC7ZQQ4B">
                                <span class="astro-AC7ZQQ4B">&nbsp;</span>
                                <button id="add_google_drive" data-nonce="<?php echo esc_html(wp_create_nonce('wt_backups_add_storage_google_drive'))?>" class="flex-1 rounded-md border border-transparent bg-main px-6 py-2 text-sm text-white astro-AC7ZQQ4B">Sign in with Google</button>
                            </div>
                        </div>
                        <div class="flex-1 astro-AC7ZQQ4B"></div>
                    </div>
                    <div class="flex astro-AC7ZQQ4B" id="dropbox" >
                        <div class="flex flex-1 gap-1 astro-AC7ZQQ4B">
                            <div class="flex flex-1 flex-col gap-1 astro-AC7ZQQ4B">
                                <span class="astro-AC7ZQQ4B">&nbsp;</span>
                                <button id="add_dropbox_storage" data-nonce="<?php echo esc_html(wp_create_nonce('wt_backups_add_storage_dropbox'))?>" class="flex-1 rounded-md border border-transparent bg-main px-6 py-2 text-sm text-white astro-AC7ZQQ4B">Sign in with Dropbox</button>
                            </div>
                        </div>
                        <div class="flex-1 astro-AC7ZQQ4B"></div>
                    </div>
                </div>
            </div>

            <div class="confirmation-dialog__buttons-wrapper" id="storage_buttons">
                <button class="wt-button wt-button--success wt-button--size-300 wt-button--padded wt-font-700 confirmation-dialog__button" id="save-storage"
                    data-nonce="<?php echo esc_html(wp_create_nonce('wt_backups_save_storage_nonce'))?>">Add storage</button>
                <a href="<?php echo esc_html($link) ?>" class="wt-button wt-button--red wt-button--size-300 wt-button--padded wt-font-700 confirmation-dialog__button" id="wt-cancel">Cancel</a>
            </div>
        </div>
    </div>

</form>

</section>
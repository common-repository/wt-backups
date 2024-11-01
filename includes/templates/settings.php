<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}
?>

<div class="flex flex-col gap-2.5 border-b border-b-gray-200 px-8 pb-6">
    <h2 class="text-2xl font-bold text-main" style="padding-top: 40px;">Settings</h2>
    <p class="text-gray-400">Set your backup schedule</p>
</div>
<form class="px-8 pb-10 pr-11 pt-8" id="backup_settings" data-nonce="<?php echo esc_html(wp_create_nonce('wt_backups_save_backup_settings_nonce'))?>">

    <section>
        <div class="divide-x-gray-200 mb-4 flex divide-x border-b border-b-gray-200">
            <div class="flex-1 pb-7 pt-6">
                <h3 class="mb-5 text-sm font-medium text-gray-700">
                    Backups configuration
                </h3>
                <div class="flex flex-col gap-5">

                    <label class="flex flex-1 flex-col gap-1 text-sm font-medium text-gray-700 astro-AC7ZQQ4B">
                        Number of simultaneously stored backups
                        <input type="number" value="<?php echo esc_html((array_key_exists('limit_backups',$variables['backup_settings'] )) ? $variables['backup_settings']['limit_backups'] : ''); ?>" id="limit_backups" name="limit_backups" placeholder="Number of backups" style="max-width: 270px;" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-500 shadow-sm astro-AC7ZQQ4B">
                    </label>

                    <label class="flex flex-1 flex-col gap-1 text-sm font-medium text-gray-700 astro-AC7ZQQ4B">
                        Мax file size (Mb)
                        <input type="number" value="<?php echo esc_html((array_key_exists('max_file_size',$variables['backup_settings'] )) ? $variables['backup_settings']['max_file_size'] : 100); ?>" id="max_file_size" name="max_file_size" placeholder="Мax file size" style="max-width: 270px;" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-500 shadow-sm astro-AC7ZQQ4B">
                    </label>


                </div>
            </div>

        </div>

    </section>

	<?php include 'storages.php'?>

    <section>
        <div class="divide-x-gray-200 mb-4 flex divide-x border-b border-b-gray-200">
            <div class="flex-1 pb-7 pt-6">
                <h3 class="mb-5 text-sm font-medium text-gray-700">
                    Files configuration
                </h3>
                <div class="flex flex-col gap-5">
                    <label class="flex items-start gap-3">
                        <input class="h-4 w-4 rounded-[4px] border border-gray-300" type="checkbox" name="db_only"
                               id="db_only" <?php if($variables['backup_settings']['db_only']):?>checked<?php endif;?>>
                        <span class="flex flex-col">
                            <p class="text-sm font-medium leading-none text-gray-700">Database Only:</p>
                            <span class="text-sm text-gray-500">Archive only the database</span>
                          </span>
                    </label>

                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="choose_folders" id="choose_folders"
                               class="h-4 w-4 rounded-[4px] border border-gray-300 backup_folders"
                         <?php if($variables['backup_settings']['choose_folders']):?>checked<?php endif;?>
                         <?php if($variables['backup_settings']['db_only']):?>disabled<?php endif;?>
                        >
                        <span class="flex flex-col">
                            <p class="text-sm font-medium leading-none text-gray-700">Choose folders:</p>
                            <span class="text-sm text-gray-500">Select folders that will be archived</span>
                        </span>
                    </label>
                    <div id="folders" style="padding-left: 20px; <?php if(!$variables['backup_settings']['choose_folders']):?>display: none<?php endif;?>">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="folders[plugins]" id="backup_folders_plugins" class="backup_folders"
                             <?php if($variables['backup_settings']['folders']['plugins']):?>checked<?php endif;?>> Plugins
                        </label>
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="folders[themes]" id="backup_folders_themes" class="backup_folders"
                             <?php if($variables['backup_settings']['folders']['themes']):?>checked<?php endif;?>> Themes
                        </label>
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="folders[uploads]" id="backup_folders_uploads" class="backup_folders"
                             <?php if($variables['backup_settings']['folders']['uploads']):?>checked<?php endif;?>> Uploads
                        </label>
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="folders[others]" id="backup_folders_others" class="backup_folders"
                             <?php if($variables['backup_settings']['folders']['others']):?>checked<?php endif;?>> Other folders
                        </label>
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="folders[core]" id="backup_folders_core" class="backup_folders"
                             <?php if($variables['backup_settings']['folders']['core']):?>checked<?php endif;?>> Core
                        </label>
                    </div>

                </div>
            </div>

        </div>

    </section>

    <section class="border-b border-b-gray-200 mb-5 pb-5">
        <div class="mb-7">
            <p class="mb-5 text-sm font-medium text-gray-700">
                Schedule backup options
            </p>
            <label class="flex items-start gap-3 mb-4">
                <input type="checkbox" name="enable_scheduled_backup" id="enable_scheduled_backup"
                 <?php if($variables['backup_settings']['enable_scheduled_backup']):?>checked<?php endif;?>>
                <span class="flex flex-col">
                    <p class="text-sm font-medium leading-none text-gray-700">Enable scheduled:</p>
                    <span class="text-sm text-gray-500">Enable scheduled backup creation</span>
                  </span>
            </label>

            <div class="flex-1">
                <label for="time-interval" class="block text-sm font-medium text-gray-700">Backup is created once a day, select the time:</label>
                <select id="time-interval" name="time" class="mt-2 block w-full rounded-md py-1.5 pl-3 pr-10 text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                value="<?php echo esc_html($variables['backup_settings']['time']); ?>">
                    <?php foreach($variables['time_list'] as $value):?>
                        <option <?php if($variables['backup_settings']['time'] == $value) echo esc_html('selected'); ?>><?php echo esc_html($value) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php echo esc_html('Server time: '.gmdate("H:i:s"));?>
            </div>

        </div>
    </section>

    <button type="button" id="check_settings" class="rounded-md bg-white px-6 py-3 border border-gray-300"
            data-nonce="<?php echo esc_html(wp_create_nonce('wt_backups_check_settings_nonce'))?>">Check settings</button>
    <button type="submit" id="save_settings" class="rounded-md bg-main px-6 py-3 text-white">Save settings</button>
</form>


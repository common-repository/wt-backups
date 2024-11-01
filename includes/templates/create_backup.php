<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
	if (!headers_sent()) {
		header('HTTP/1.1 403 Forbidden');
	}
	exit(1);
}
?>

<div id="wt-backups-wrap">

    <form id="backup_settings" class="astro-AC7ZQQ4B" data-nonce="<?php echo esc_html(wp_create_nonce('wt_backups_next_page'))?>">
        <div class="rounded-xl bg-white shadow-card pb-5 min-h-[408px] mb-5">
            <h2 class="mb-2.5 pl-8 text-2xl font-bold text-main astro-AC7ZQQ4B" style="padding-top: 40px;">Backup creation</h2>
            <p class="mb-9 pl-8 text-sm text-gray-400 astro-AC7ZQQ4B">
                Create, edit or delete your Backups. And be calm.
            </p>

            <label for="backup-name" class="mb-1 block pl-8 text-sm font-medium text-gray-700 astro-AC7ZQQ4B">
                Backup name
            </label>
            <div class="mb-6 flex gap-1 pl-8 astro-AC7ZQQ4B">
                <input type="text" placeholder="back_name.zip" id="backup_name" name="backup_name" data-nonce="<?php echo esc_html(wp_create_nonce('wt_backups_check_zip_exist_nonce'))?>"
                       value="<?php echo esc_html(array_key_exists('backup_name', $variables['settings']) ? $variables['settings']['backup_name'] : '') ?>"
                       class="w-80 rounded-md border border-gray-300 px-3 py-2.5 text-gray-500 shadow-sm placeholder:text-gray-500 astro-AC7ZQQ4B">
                <button id="backup_checking" class="rounded-md bg-main px-10 py-2.5 text-white astro-AC7ZQQ4B">Start build</button>
            </div>
            <p id="check_zip_exist_result" class="pl-8"></p>

            <div class="mt-6 flex flex-col gap-1 pl-8 pr-8 astro-AC7ZQQ4B">
                <?php include 'storages.php'?>
            </div>
        </div>
        <div class="rounded-xl bg-white shadow-card pt-8 pb-12 astro-AC7ZQQ4B">

            <h2 class="pl-8 text-2xl font-bold text-main astro-AC7ZQQ4B">Archive settings</h2>
            <nav data-active-tab="Files" class="border-b border-b-gray-200 px-8 pt-5 tabs pl-8 astro-AC7ZQQ4B">
                <ul class="flex gap-10">
                    <li>
                        <button class="tab-btn block pb-4 font-medium text-sm relative after:-bottom-[1px] after:h-0.5 after:left-0 after:absolute after:w-full text-main after:bg-main">
                            Files
                        </button>
                    </li>
                </ul>
            </nav>
            <section>
                <div class="divide-x-gray-200 mb-4 flex divide-x border-b border-b-gray-200 pl-8 pr-6">
                    <div class="flex-1 pb-7 pt-6">
                        <h3 class="mb-5 text-sm font-medium text-gray-700">
                            Files configuration
                        </h3>
                        <div class="flex flex-col gap-5">
                            <label class="flex items-start gap-3">
                                <input class="h-4 w-4 rounded-[4px] border border-gray-300" type="checkbox" name="db_only" id="db_only"
                                    <?php echo esc_html((array_key_exists('db_only', $variables['settings']) and $variables['settings']['db_only']) ? 'checked' : '') ?>>
                                <span class="flex flex-col">
                                <p class="text-sm font-medium leading-none text-gray-700">Database Only:</p>
                                <span class="text-sm text-gray-500">Archive only the database</span>
                              </span>
                            </label>

                            <label class="flex items-start gap-3">
                                <input type="checkbox" name="choose_folders" id="choose_folders"
                                       class="h-4 w-4 rounded-[4px] border border-gray-300 backup_folders"
                                    <?php echo esc_html((array_key_exists('choose_folders', $variables['settings']) and $variables['settings']['choose_folders']) ? 'checked' : '') ?>
                                    <?php echo esc_html((array_key_exists('db_only', $variables['settings']) and $variables['settings']['db_only']) ? 'disabled' : '') ?>>
                                <span class="flex flex-col">
                                <p class="text-sm font-medium leading-none text-gray-700">Choose folders:</p>
                                <span class="text-sm text-gray-500">Select folders that will be archived</span>
                            </span>
                            </label>
                            <div id="folders" style="padding-left: 20px; display: <?php echo esc_html((array_key_exists('choose_folders', $variables['settings']) and $variables['settings']['choose_folders']) ? 'block' : 'none') ?>">
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" name="folders[plugins]" id="backup_folders_plugins" class="backup_folders"
                                        <?php echo esc_html((array_key_exists('folders', $variables['settings']) and $variables['settings']['folders']['plugins']) ? 'checked' : '' )?>
                                        <?php echo esc_html((array_key_exists('db_only', $variables['settings']) and $variables['settings']['db_only']) ? 'disabled' : '' )?>
                                    > Plugins
                                </label>
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" name="folders[themes]" id="backup_folders_themes" class="backup_folders"
                                        <?php echo esc_html((array_key_exists('folders', $variables['settings']) and $variables['settings']['folders']['themes']) ? 'checked' : '' )?>
                                        <?php echo esc_html((array_key_exists('db_only', $variables['settings']) and $variables['settings']['db_only']) ? 'disabled' : '' )?>
                                    > Themes
                                </label>
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" name="folders[uploads]" id="backup_folders_uploads" class="backup_folders"
                                        <?php echo esc_html((array_key_exists('folders', $variables['settings']) and $variables['settings']['folders']['uploads']) ? 'checked' : '') ?>
                                        <?php echo esc_html((array_key_exists('db_only', $variables['settings']) and $variables['settings']['db_only']) ? 'disabled' : '') ?>
                                    > Uploads
                                </label>
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" name="folders[others]" id="backup_folders_others" class="backup_folders"
                                        <?php echo esc_html((array_key_exists('folders', $variables['settings']) and $variables['settings']['folders']['others']) ? 'checked' : '') ?>
                                        <?php echo esc_html((array_key_exists('db_only', $variables['settings']) and $variables['settings']['db_only']) ? 'disabled' : '') ?>
                                    > Other folders
                                </label>
                                <label class="flex items-start gap-3">
                                    <input type="checkbox" name="folders[core]" id="backup_folders_core" class="backup_folders"
                                        <?php echo esc_html((array_key_exists('folders', $variables['settings']) and $variables['settings']['folders']['core']) ? 'checked' : '') ?>
                                        <?php echo esc_html((array_key_exists('db_only', $variables['settings']) and $variables['settings']['db_only']) ? 'disabled' : '') ?>
                                    > Core
                                </label>

                            </div>

                        </div>
                    </div>

                </div>

            </section>
        </div>
    </form>
</div>
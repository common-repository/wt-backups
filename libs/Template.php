<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    die("Protected By WebTotem! Not plugin init");
}


/**
 * Read, parse and handle everything related with the templates.
 */
class WT_Backups_Template
{

    protected $images_path;
    protected $menu_url;

    function __construct()
    {
        $this->images_path = WT_Backups_Helper::getImagePath('');
        $this->menu_url = WT_Backups_Helper::adminURL('admin.php?page=wt_backups');
    }

    /**
     * Rendering a template using twig and filling in data.
     *
     * @param string $template
     * @param array $variables
     *
     * @return bool|string
     */
    public function render($template, $variables = [])
    {

        if (!file_exists(WT_BACKUPS_PLUGIN_PATH . '/includes/templates/' . $template)) {
            WT_Backups_Option::setNotification('error', __('There is no template: ', 'wt-backups') . $template);

            return false;
        }

        // Default values of some variables
        $variables['images_path'] = $this->images_path;
        $variables['menu_url'] = $this->menu_url;

        return $this->getHtml($template, $variables);
    }

    /**
     * Page rendering based on array data.
     *
     * @param $params
     *
     * @return bool|string
     */
    public function arrayRender($params)
    {

        $render = '';
        if (is_array($params)) {

            if (array_key_exists('template', $params)) {
                $template = $params['template'] . '.php';
                $variables = (isset($params['variables'])) ? $params['variables'] : [];

                $render = $this->render($template, $variables) ?: '';
            } else {
                foreach ($params as $param) {
                    $template = $param['template'] . '.php';
                    $variables = (isset($param['variables'])) ? $param['variables'] : [];

                    $render .= $this->render($template, $variables) ?: '';
                }
            }

        }

        return $render;
    }

    /**
     * Generate a page based on a basic template and content.
     *
     * @param $page_content
     *
     * @return bool|string
     */
    public function baseTemplate($page_content, $nav_tab = false)
    {

        $page = str_replace(['wt_backups', '_'], '', sanitize_text_field($_GET['page']));
        $page = $page ?: 'dashboard';
        $variables['is_active'][$page] = 'wt_backups_nav__link_active';

        $variables['menu_url'] = $this->menu_url;
        $variables['nav_tabs'] = $nav_tab;
        $variables['content'] = $page_content;
        $variables['notifications'] = WT_Backups_Ajax::notifications();

        $variables['max_file_upload_in_bytes'] = WT_Backups_Helper::max_file_upload_in_bytes() ?: '33554432';
        $variables['page'] =$page;
        $variables['notifications_raw'] = json_encode($variables['notifications']);

        return $this->render('layout.php', $variables);
    }


    /**
     * Get HTML
     *
     * @return string|bool
     */
    public function getHtml($template, $variables)
    {
        $templatePath = WT_BACKUPS_PLUGIN_PATH . '/includes/templates/' . $template;
        if (!file_exists($templatePath)) {
            return false;
        }

        ob_start();
        include $templatePath;

        return ob_get_clean();
    }

}

<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if (!defined('WT_BACKUPS_INIT') || WT_BACKUPS_INIT !== true) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    exit(1);
}

use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\DropboxFile;
use Kunnu\Dropbox\Models\AccessToken;

class WT_Backups_Dropbox {

    private $dropbox;

    function __construct($data = null, $timestamp = null, $index = null) {

        if($data and is_array($data)){
            $access_token = $data['access_token'];
        } elseif($data and is_object($data)){
            $access_token = $data->access_token;
        } else {
            $access_token = null;
        }

        //Configure Dropbox Application
        $app = new DropboxApp(base64_decode('d3d4eGlxa280YmVhZWZ4'), base64_decode('M21uc3RtbndjYnJ5ZTc2'), $access_token);

        //Configure Dropbox service
        $this->dropbox = new Dropbox($app);

        if($data and is_array($data)){
            if(time() >= ($timestamp + $data['expires_in'])){
                $token = $this->dropbox->getAuthHelper()->getRefreshedAccessToken(new AccessToken($data));
                $this->__construct($token);
                $storages = json_decode(WT_Backups_Option::getOption('storages'), true);

                $storages[$index]['params']['timestamp'] = time();
                $storages[$index]['params']['access_token'] = $token->getToken();
                $storages[$index]['params']['data'] = $token->getData();

                WT_Backups_Option::setOptions(['storages' => $storages]);
            }
        }

    }

    public function GetAuthUrl($callbackUrl, $urlState) {
        //Fetch the Authorization/Login URL
        return $this->dropbox->getAuthHelper()->getAuthUrl($callbackUrl, [], $urlState, 'offline');
    }

    public function GetAccessToken($code, $state = null, $callbackUrl = null) {
        //Fetch the AccessToken
        return $this->dropbox->getAuthHelper()->getAccessToken($code, $state, $callbackUrl);
    }

    public function UploadFileToStorage($path_to_Local_file, $backup_name) {
        $dropboxFile = new DropboxFile($path_to_Local_file);
        $this->dropbox->upload($dropboxFile, "/" . $backup_name, ['autorename' => true]);
    }

    public function getFileList() {

        $args = [
            'body' => wp_json_encode(["path" => '', "limit" => 1000]),
            'timeout' => '60',
            'sslverify' => false,
            'headers' => [
                'Content-Type:application/json',
                'Content-Type' => 'application/json',
                'Accept: application/json',
            ],
        ];

        $args['headers'] = array_merge($args['headers'], ["Authorization" => "Bearer " . $this->dropbox->getAccessToken()]);

        $response = wp_remote_post('https://api.dropboxapi.com/2/files/list_folder', $args);
        $response = wp_remote_retrieve_body($response);
        $response = json_decode($response, true);

        $list = [];
        if(isset($response['entries'])){
            foreach ($response['entries'] as $file_info){
                $list[] = $file_info['name'];
            }
        }

        return $list;
    }

}
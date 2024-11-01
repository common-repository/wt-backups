<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/** 
 * 
 * This Google Drive API handler class is a custom PHP library to handle the Google Drive API calls. 
 * 
 * @class        WT_Backups_GoogleDriveApi
 * @author        CodexWorld 
 * @link        http://www.codexworld.com 
 * @version        1.0 
 */ 
class WT_Backups_GoogleDriveApi {
    const WT_BACKUPS_DRIVE_FILE_UPLOAD_URI = 'https://www.googleapis.com/upload/drive/v3/files';
    const WT_BACKUPS_DRIVE_FILE_META_URI = 'https://www.googleapis.com/drive/v3/files';

    public $storage;
    public $access_token;

    function __construct($access_token = false, $storage = false) {
        $this->storage = $storage;
        $this->access_token = $access_token;
    }
     

    /**
     * @throws Exception
     */
    public function GetRefreshAccessToken() {
        $storages = json_decode(WT_Backups_Option::getOption( 'storages' ), true);
        $storage = $storages[$this->storage];

        $apiURL = 'https://cloud.checktotem.com/refresh_token.php?refresh_token=' . $storage['params']['data']['refresh_token'];
        $this->access_token = false;
        $data = $this->SendRequest($apiURL,'POST','' );

        $storages[$this->storage]['params']['timestamp'] = time();
        $storages[$this->storage]['params']['access_token'] = $data['access_token'];
        $storages[$this->storage]['params']['data']['expires_in'] = $data['expires_in'];

        WT_Backups_Option::setOptions(['storages' => $storages]);

        return $data['access_token'];
    }

    /**
     * @throws Exception
     */
    public function UploadFileToDrive($file_content, $mime_type) {
        $apiURL = self::WT_BACKUPS_DRIVE_FILE_UPLOAD_URI . '?uploadType=media';

        $response = $this->SendRequest($apiURL, 'POST', $file_content, $mime_type );

        return $response['id'];
    }

    /**
     * @throws Exception
     */
    public function MoveFileToFolder($file_id, $folder_id) {
        $apiURL = 'https://www.googleapis.com/drive/v2/files/' . $file_id . '/parents';

        $response = $this->SendRequest($apiURL, 'POST', wp_json_encode(['id' => $folder_id]) );

        return $response['id'];
    }


    /**
     * @throws Exception
     */
    public function GetUserData() {
        $apiURL = 'https://www.googleapis.com/oauth2/v2/userinfo';

        return $this->SendRequest($apiURL, 'GET' , '');
    }

    /**
     * @throws Exception
     */
    public function GetAllFiles($folder_id = false) {

        if($folder_id){
            $apiURL = self::WT_BACKUPS_DRIVE_FILE_META_URI . '?q="' . $folder_id .'"+in+parents';
        } else {
            $apiURL = self::WT_BACKUPS_DRIVE_FILE_META_URI;
        }

        return $this->SendRequest($apiURL, 'GET' , '');
    }

    /**
     * @throws Exception
     */
    public function CheckExistFolder($folder_id = false) {

        if($folder_id){
            $apiURL = self::WT_BACKUPS_DRIVE_FILE_META_URI . '/' . $folder_id;

            $response = $this->sendRequest($apiURL, 'GET' , '', 'application/json', false);

            if(!$response['error']){
                return [
                    'is_new' => false,
                    'folder_id' => $folder_id,
                ];
            }
        }

        $response = $this->GetAllFiles();
        foreach ($response['files'] as $file){
            if($file['name'] == 'wt-backups' and $file['mimeType'] == "application/vnd.google-apps.folder"){
                return [
                    'is_new' => true,
                    'folder_id' => $file['id'],
                ];
            }
        }

        $response = $this->CreateFolder();

        return [
            'is_new' => true,
            'folder_id' => $response['id'],
        ];

    }

    /**
     * @throws Exception
     */
    public function CreateFolder() {

        $post_fields = wp_json_encode ( array (
            // Earlier it was title changed to name
            "name" => "wt-backups",
            "mimeType" => "application/vnd.google-apps.folder"
        ));

        return $this->sendRequest(self::WT_BACKUPS_DRIVE_FILE_META_URI, 'POST', $post_fields );
    }

    /**
     * @throws Exception
     */
    public function UpdateFileMeta($file_id, $file_meatadata, $folder_id) {
        $apiURL = self::WT_BACKUPS_DRIVE_FILE_META_URI . '/' . $file_id;

        $this->MoveFileToFolder($file_id, $folder_id);

        return $this->sendRequest($apiURL, 'PATCH', wp_json_encode($file_meatadata) );
    }

    /**
     * @throws Exception
     */
    public function SendRequest($apiURL, $method, $post_fields, $content_type = 'application/json', $exception = TRUE, $repeat = FALSE) {

        $access_token = $this->access_token;

        $args = [
            'timeout' => '300',
            'method' => $method,
            'headers' => array('Content-Type' => $content_type, 'Authorization' => ' Bearer '. $access_token),
            'body' => $post_fields,
        ];

        $response = wp_remote_request($apiURL, $args);

        $response = [
            'data' => json_decode(wp_remote_retrieve_body($response), TRUE),
            'http_code' => wp_remote_retrieve_response_code($response),
            'error' => is_wp_error( $response ) ? $response->get_error_message() : false,
        ];

        if (isset($response['data']['error']) and $response['data']['error']['code'] == 401 && !$repeat) {
            $this->access_token = $this->GetRefreshAccessToken();
            return $this->sendRequest($apiURL, $method, $post_fields, $content_type, $exception, TRUE);
        }

        if ($exception and $response['http_code'] != 200) {
            $error_msg = 'Failed to execute the request' ;
            if ($response['error']) {
                $error_msg = $response['error'];
            } elseif (isset($response['data']['error'])){
                $error_msg = $response['data']['error']['message'];

            }
            throw new Exception('Google Drive error '.$response['http_code'].': '.$error_msg);
        }

        if(!$exception)
            return $response;

        return $response['data'];
    }
} 
?>
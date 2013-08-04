<?php
class MultiServerSync extends Object {

        /**
         * Posts a file to other web servers to synchronise the assets
         * @param File $file
         */
        public function syncFile(File $file){
                $servers = $this->config()->get('servers');

                if ($servers) foreach ($servers as $server) {
                        //don't sync to self
                        if ($server != $_SERVER['SERVER_ADDR']) {
                                $this->send(
                                        "http://{$server}/multisync/filereceiver",
                                        array('Host:' . $_SERVER['HTTP_HOST']),
                                        array(
                                                'Secret' => $file->Secret,
                                                'ID' => $file->ID,
                                                'SyncFile' => '@' . $file->getFullPath()
                                        )
                                );
                        }
                }
        }

        public function deleteFile(File $file) {
                $servers = $this->config()->get('servers');

                if ($servers) foreach ($servers as $server) {
                        //don't sync to self
                        if ($server != $_SERVER['SERVER_ADDR']) {
                                $this->send(
                                        "http://{$server}/multisync/filereceiver",
                                        array('Host:' . $_SERVER['HTTP_HOST']),
                                        array(
                                                'Secret' => $file->Secret,
                                                'ID' => $file->ID
                                        )
                                );
                        }
                }
        }

        public function send($url, $headers = array(), $postFields = array()) {
                // Execute CURL
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST,1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $result = curl_exec ($ch);
                curl_close ($ch);
        }

}


class MultiServerSync_Controller extends Controller {
	public static $allow_actions = array(
		'filereceiver' => '->checkIPAddress'
	);


        /**
         * Check that the request has originated from one of the other known servers
         * @return bool
         */
        public function checkIPAddress() {
                $servers = Config::inst()->get('MultiServerSync', 'servers');

                if ($servers && is_array($servers) && in_array($_SERVER['REMOTE_ADDR'], $servers)) {
                        return true;
                }

                return false;
        }

	// $Action File receiver
	public function filereceiver(){


                if (!empty($_FILES['SyncFile']) && !empty($_POST['ID']) && !empty($_POST['Secret'])) {
                        $file = File::get()->byID($_POST['ID']);

                        if ($file && $file->Secret == $_POST['Secret']) {
                                if (!file_exists($file->getFullPath())) {
                                        move_uploaded_file($_FILES['SyncFile']['tmp_name'], $file->getFullPath());
                                        return 'File Successful';
                                }
                        }
                }

		return null;
	}

        public function deletefile() {
                if (!empty($_POST['ID']) && !empty($_POST['Secret'])) {
                        $file = File::get()->byID($_POST['ID']);

                        if ($file->Secret == $_POST['Secret']) {
                                unlink($file->getFullPath());
                        }
                }
        }
}
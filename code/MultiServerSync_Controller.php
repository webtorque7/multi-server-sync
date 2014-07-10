<?php

class MultiServerSync extends Object {

	public static $max_retries = 5;

	/**
	 * Posts a file to other web servers to synchronise the assets
	 * @param File $file
	 */
	public function syncFile(File $file) {
		$servers = $this->config()->get('servers');

		if ($servers) foreach ($servers as $server) {
			//don't sync to self
			if ($server != $this->getServerIP()) {
				$this->send(
					"http://{$server}/multisync/filereceiver",
					array('Host:' . $_SERVER['HTTP_HOST']),
					array(
						'Secret' => $file->Secret,
						'ID' => $file->ID
					),
					$file->ClassName != 'Folder' ? $file->getFullPath() : ''
				);
			}
		}
	}

	public function deleteFile(File $file) {
		$servers = $this->config()->get('servers');

		if ($servers) foreach ($servers as $server) {
			//don't sync to self
			if ($server != $this->getServerIP()) {
				$this->send(
					"http://{$server}/multisync/deletefile",
					array('Host:' . $_SERVER['HTTP_HOST']),
					array(
						'Secret' => $file->Secret,
						'ID' => $file->ID
					)
				);
			}
		}
	}

	public function renameFile(File $file) {

		if (!$file->isChanged('Filename')) return;

		$changedFields = $file->getChangedFields();
		$pathBefore = $changedFields['Filename']['before'];
		$pathAfter = $changedFields['Filename']['after'];

		$servers = $this->config()->get('servers');

		if ($servers) foreach ($servers as $server) {
			//don't sync to self
			if ($server != $this->getServerIP()) {
				$this->send(
					"http://{$server}/multisync/renamefile",
					array('Host:' . $_SERVER['HTTP_HOST']),
					array(
						'Secret' => $file->Secret,
						'ID' => $file->ID,
						'PathBefore' => $pathBefore,
						'PathAfter' => $pathAfter
					)
				);
			}
		}


	}

	public function send($url, $headers = array(), $postFields = array(), $filePath = null, $retries = 0) {
		// Execute CURL
		$ch = curl_init($url);

		if ($filePath && !function_exists('curl_file_create')) {
			$postFields['SyncFile'] = '@' . $filePath;
		}
		else if ($filePath) {
			$postFields['SyncFile'] = curl_file_create($filePath, mime_content_type($filePath), 'SyncFile');
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$result = curl_exec($ch);
		$curlError = curl_error($ch);
		$statusCode = curl_getinfo($ch);

		curl_close($ch);

		//retry if failed
		if (empty($result)) {
			if ($retries < $this->config()->max_retries) {
				$this->send($url, $headers, $postFields, $filePath, ++$retries);
			}
			else {
				throw new Exception(sprintf('Failed to update file (%s) with server (%s)', $postFields['ID'], $url));
			}
		}
		else if ($curlError) {
			if ($retries < $this->config()->max_retries) {
				$this->send($url, $headers, $postFields, $filePath, ++$retries);
			}
			else {
				throw new Exception(sprintf('Failed to update file (%s) with server (%s), ' . $curlError, $postFields['ID'], $url));
			}
		}
		else if ($statusCode['http_code'] >= 400) {
			if ($retries < $this->config()->max_retries) {
				$this->send($url, $headers, $postFields, $filePath, ++$retries);
			}
			else {
				throw new Exception(sprintf('Failed to update file (%s) with server (%s), Status code ' . $statusCode['http_code'], $postFields['ID'], $url));
			}
		}

	}

	private function getServerIP() {
		if (isset($_SERVER['LOCAL_ADDR'])) {
			return $_SERVER['LOCAL_ADDR'];
		} else if (isset($_SERVER['SERVER_ADDR'])) {
			return $_SERVER['SERVER_ADDR'];
		}
		return '';
	}

}


class MultiServerSync_Controller extends Controller {
	public static $allowed_actions = array(
		'filereceiver' => '->checkIPAddress',
		'deletefile' => '->checkIPAddress',
		'renamefile' => '->checkIPAddress'
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
	public function filereceiver() {


		if (!empty($_POST['ID']) && !empty($_POST['Secret'])) {
			$file = File::get()->byID($_POST['ID']);

			if ($file && $file->Secret == $_POST['Secret']) {

				if ($file->ClassName != 'Folder' && !empty($_FILES['SyncFile'])) {
					move_uploaded_file($_FILES['SyncFile']['tmp_name'], $file->getFullPath());
					return 1;
				} else if (!file_exists($file->getFullPath()) && $file->ClassName == 'Folder') {
					Filesystem::makeFolder($file->getFullPath());
					return 1;
				}

			}
		}

		return 0;
	}

	public function deletefile() {
		if (!empty($_POST['ID']) && !empty($_POST['Secret'])) {
			$file = File::get()->byID($_POST['ID']);

			if ($file->Secret == $_POST['Secret']) {
				unlink($file->getFullPath());
				return 1;
			}
		}
		return 0;
	}

	public function renamefile() {

		if (empty($_POST['PathBefore']) || empty($_POST['PathAfter'])) return 0;

		$pathBefore = $_POST['PathBefore'];
		$pathAfter = $_POST['PathAfter'];

		// If the file or folder didn't exist before, don't rename - its created
		if (!$pathBefore) return;

		$pathBeforeAbs = Director::getAbsFile($pathBefore);
		$pathAfterAbs = Director::getAbsFile($pathAfter);

		// TODO Fix Filetest->testCreateWithFilenameWithSubfolder() to enable this
		// // Create parent folders recursively in database and filesystem
		// if(!is_a($this, 'Folder')) {
		// 	$folder = Folder::findOrMake(dirname($pathAfterAbs));
		// 	if($folder) $this->ParentID = $folder->ID;
		// }

		// Check that original file or folder exists, and rename on filesystem if required.
		// The folder of the path might've already been renamed by Folder->updateFilesystem()
		// before any filesystem update on contained file or subfolder records is triggered.
		if (!file_exists($pathAfterAbs)) {
			if (!is_a($this, 'Folder')) {
				// Only throw a fatal error if *both* before and after paths don't exist.
				if (!file_exists($pathBeforeAbs)) {
					return 0;
				}

				// Check that target directory (not the file itself) exists.
				// Only check if we're dealing with a file, otherwise the folder will need to be created
				if (!file_exists(dirname($pathAfterAbs))) {
					return 0;
				}
			}

			// Rename file or folder
			$success = rename($pathBeforeAbs, $pathAfterAbs);
			if (!$success) return 0;
			return 1;
		}
		return 0;
	}
}
<?php
class MultiServerSync extends Object {

	public static $max_retries = 5;

	/**
	 * Posts a file to other web servers to synchronise the assets
	 * @param File $file
	 */
	public function syncFile(File $file) {
		$servers = $this->config()->get('servers');

		if ($servers && count($servers) > 1) foreach ($servers as $server) {
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

	/**
	 * Sync a file which isn't a part of Assets
	 *
	 * @param $path string Relative path to file with leading slash
	 */
	public function syncHiddenFile($path) {
		$servers = $this->config()->get('servers');

		if ($servers && count($servers) > 1) foreach ($servers as $server) {
			//don't sync to self
			if ($server != $this->getServerIP()) {
				$this->send(
					"http://{$server}/multisync/hiddenfilereceiver",
					array('Host:' . $_SERVER['HTTP_HOST']),
					array(
						'Path' => $path
					),
					Director::baseFolder() .  $path
				);
			}
		}
	}

	public function deleteFile(File $file) {
		$servers = $this->config()->get('servers');

		if ($servers && count($servers) > 1) foreach ($servers as $server) {
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

		if ($servers && count($servers) > 1) foreach ($servers as $server) {
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

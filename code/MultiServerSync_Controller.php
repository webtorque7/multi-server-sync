<?php
class MultiServerSync_Controller extends Controller {
	public static $allowed_actions = array(
		'filereceiver' => '->checkIPAddress',
		'deletefile' => '->checkIPAddress',
		'renamefile' => '->checkIPAddress',
		'hiddenfilereceiver' => '->checkIPAddress'
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

	public function hiddenfilereceiver() {


		if (!empty($_POST['Path'])) {
				$filePath = str_replace(array('..', './', '`', '"', '"'), '', $_POST['Path']);
				move_uploaded_file($_FILES['SyncFile']['tmp_name'], Director::baseFolder() . $filePath);
				return 1;
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
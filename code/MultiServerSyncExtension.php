<?php

/**
 * Created by JetBrains PhpStorm.
 * User: davis
 * Date: 01/08/13
 * Time: 15:03
 * To change this template use File | Settings | File Templates.
 */
class MultiServerSyncExtension extends DataExtension
{

	/**
	 * Number of seconds difference to consider a file modified
	 * @var int
	 */
	public static $modified_difference = 60;

	public $isNewRecord = false;

	public static $db = array(
		'Secret' => 'Varchar(100)'
	);

	public function onBeforeWrite() {

		if (!$this->owner->Secret) {
			$this->owner->Secret = md5(md5($this->owner->Filename) . time());
		}

		if (!$this->owner->exists()) {
			$this->isNewRecord = true;
		}

		parent::onBeforeWrite();
	}

	public function onAfterWrite() {
		parent::onAfterWrite();

		if ($this->isNewRecord || $this->owner->isModified()) {
			MultiServerSync::create()->syncFile($this->owner);
		}

		if (!$this->isNewRecord && $this->owner->isChanged('Filename')) {
			MultiServerSync::create()->renameFile($this->owner);
		}
	}

	public function onBeforeDelete() {
		parent::onBeforeDelete();

		MultiServerSync::create()->deleteFile($this->owner);
	}

	/**
	 * Gets Filename if changed, this is only useful before onAfterWrite has moved the file
	 * This is necessary as extensions fire before the main File class has moved the file to the new Filename
	 * @return mixed
	 */
	public function getCurrentFilename() {
		if ($this->owner->isChanged('Filename')) {
			$changedFields = $this->owner->getChangedFields();
			$pathBefore = $changedFields['Filename']['before'];
			return $pathBefore;
		}

		return $this->owner->Filename;
	}

	public function getCurrentFullPath() {
		return Director::baseFolder() . '/' . $this->getCurrentFilename();
	}

	public function isModified() {
		return file_exists($this->getCurrentFullPath()) && strtotime(SS_Datetime::now()) - filemtime($this->getCurrentFullPath()) > self::$modified_difference;
	}
}
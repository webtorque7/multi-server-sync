<?php
/**
 * Created by JetBrains PhpStorm.
 * User: davis
 * Date: 01/08/13
 * Time: 15:03
 * To change this template use File | Settings | File Templates.
 */

class MultiServerSyncExtension extends DataExtension{

        public static $db = array(
                'Secret' => 'Varchar(100)'
        );

	public function onBeforeWrite(){

                if (!$this->owner->Secret) {
                        $this->owner->Secret = md5(md5($this->owner->Filename) . time());
                }

		parent::onBeforeWrite();
	}

	public function onAfterWrite(){
		parent::onAfterWrite();

                MultiServerSync::create()->syncFile($this);

	}

        public function onBeforeDelete() {
                parent::onBeforeDelete();

                MultiServerSync::create()->deleteFile($this);
        }
}
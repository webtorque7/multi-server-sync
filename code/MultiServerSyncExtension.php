<?php
/**
 * Created by JetBrains PhpStorm.
 * User: davis
 * Date: 01/08/13
 * Time: 15:03
 * To change this template use File | Settings | File Templates.
 */

class MultiServerSyncExtension extends DataExtension{
	public function onBeforeWrite(){
		parent::onBeforeWrite();
	}

	public function onAfterWrite(){
		parent::onAfterWrite();

		$fileName = $this->owner->Filename;

		$fileSenderOBJ = new MultiServerSync_Controller();

		foreach($fileSenderOBJ->getServerIPHost() as $keyIP){
			$fileSenderOBJ->FileSender("../".$fileName, $keyIP['IP'], $keyIP['HOST']);
		}

	}
}
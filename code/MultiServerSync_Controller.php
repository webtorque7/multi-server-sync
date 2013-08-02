<?php
/**
 * Created by JetBrains PhpStorm.
 * User: davis
 * Date: 01/08/13
 * Time: 13:28
 * To change this template use File | Settings | File Templates.
 */

class MultiServerSync_Controller extends Controller{
	public static $allow_actions = array(
		'filereceiver',
		'test'
	);

	// File sender method
	public function FileSender($filepath, $CurrentIP, $CurrentHost){
		$multiServerSyncOBJ = new MultiServerSync();

		$headers = array("Host: ".$CurrentHost);

		$file_name_with_full_path = realpath($filepath);

		$multiServerSyncOBJ->postFile($CurrentIP, $headers, $file_name_with_full_path);
	}

	// $Action File receiver
	public function filereceiver(){
		$test = new MultiServerSync();

		$uploaddir = realpath('../assets/Uploads') . '/';

		$uploadfile = $uploaddir . basename($_FILES['file_contents']['name']);

		if (move_uploaded_file($_FILES['file_contents']['tmp_name'], $uploadfile)) {
			$error = "File is valid, and was successfully uploaded.\n";
		} else {
			$error = "Possible file upload attack!\n";
		}

		return null;
	}

	public function getServerIPHost(){
		$ownIP = $_SERVER["REMOTE_ADDR"];

		$ipArray = $this->config()->get('Servers');
		$hostArray = $this->config()->get('Host');

		$iphostArray = array();

		if(($key = array_search($ownIP, $ipArray)) !==  false){
			unset($ipArray[$key]);
			unset($hostArray[$key]);
		}
		$ipArray = explode(",",implode(",",$ipArray));
		$hostArray = explode(",",implode(",",$hostArray));

		if($this->config()->get('IsSingleHost')){
			$hostArray = array_fill(0, count($ipArray), $this->config()->get('SingleHost'));
		}

		for($i=0;$i<count($ipArray);$i++){
			array_push($iphostArray, array('IP' => $ipArray[$i], 'HOST' => $hostArray[$i]));
		}

		return $iphostArray;
	}

	public function test(){
		return "hellooo";
	}
}

class MultiServerSync {
	public function postFile($target_ip, $headers, $file_full_path){

		$file_full_path = realpath($file_full_path);

		$post = array('file_contents'=>'@'.$file_full_path);

		$hostName = $_SERVER['HTTP_HOST'];

		$target_ip_url = "http://".$target_ip."/multisync/filereceiver";

		// Execute CURL
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $target_ip_url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POST,1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		$result=curl_exec ($ch);
		curl_close ($ch);
	}

}
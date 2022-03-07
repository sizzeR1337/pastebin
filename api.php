<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates the random string
 *
 * @params $length Length of the random string
 * @return string
 */
function quickRandom($length = 7)
{
    $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
}

/**
 * This is the route class for uploading some data
 *
 * @param string $type Type of data
 * @param string $password Password to see the data
 * @param int $time Time expiration
 * @param int $deleteAfterSeen Delete the data after seen or not
 * @param string $data Some data that can be text or photo
 */
Route::post('/v1/upload', function (Request $request) {
	
	$type 				= $request->input('type');
	$time		 		= $request->input('time');
	$password 	 		= $request->input('password');
	$deleteAfterSeen 		= $request->input('deleteAfterSeen');
	$data 				= $request->input('data');
	
	$randStr 			= quickRandom(7);
	$secretKey 			= quickRandom(11);
	
	if($time == -1)
		$time = 2147483647; // max uint32
	
	switch($type){
		case 'text':
			$result = DB::insert('INSERT INTO `notes`(`randId`, `secretKey`, `type`, `time`, `password`, `deleteAfterSeen`, `data`) VALUES ("'.$randStr.'","'.$secretKey.'","text",'.$time.',"'.$password.'",'.$deleteAfterSeen.',"'.$data.'")');
			break;
		case 'photo':
			if(strpos($data, 'data:image/') === 0){
				$result = DB::insert('INSERT INTO `notes`(`randId`, `secretKey`, `type`, `time`, `password`, `deleteAfterSeen`, `data`) VALUES ("'.$randStr.'","'.$secretKey.'","photo",'.$time.',"'.$password.'",'.$deleteAfterSeen.',"'.$data.'")');
			}else{
				return response(json_encode(["error" => "Enter the valid data"]), 400);
			}
			break;
		default:
			return response(json_encode(["error" => "Enter the valid type"]), 400);
	}
	
	if($result == 1){
		$response = json_encode(["id" => $randStr, "secretKey" => $secretKey]);
		$code = 200;
	}else{
		$response = json_encode(["error" => "something went wrong"]);
		$code = 400;
	}
	
    return response($response, $code);
});

/**
 * This is the route class for view some data
 *
 * @param int $id ID of the data
 * @param string $req_password Password to get access to the data (if require)
 */
Route::post('/v1/viewData', function (Request $request) {
	
	$id 				= $request->input('id');
	$req_password 			= $request->input('password');
	
	$dbReq				= DB::select('SELECT * FROM `notes` WHERE `randId`="'.$id.'"');
	
	if(!$dbReq)
		return response(json_encode(["error" => "not found"]), 404);
	
	if($dbReq[0]->time < time()){
		DB::table('notes')->where('randId', $id)->delete();
		return response(json_encode(["error" => "out of date"]), 400);
	}
	
	$data				= $dbReq[0]->data;
	$type				= $dbReq[0]->type;
	$password			= $dbReq[0]->password;
	$deleteAfterSeen		= $dbReq[0]->deleteAfterSeen;
	
	if(!empty($password)){
		if($req_password !== $password){
			return response(json_encode(["error" => "enter the valid password"]), 400);
		}
	}
	
	if($deleteAfterSeen == 1)
		DB::table('notes')->where('randId', $id)->delete();
	
    return response(json_encode(["type" => $type, "data" => $data]), 200);
});


/**
 * This is the route class for delete data
 *
 * @param string $secretKey SecretKey of the data
 */
Route::post('/v1/deleteData', function (Request $request) {
	
	$secretKey 	= $request->input('secretKey');
	
	$res = DB::table('notes')->where('secretKey', $secretKey)->delete();
	$code = 400;
	
	if($res == 1)
		$code = 200;
	
	return response(json_encode(["res" => $res]), $code);
});

<?php

if(!function_exists('curlHelper')) {
	function curlHelper($endpoint, $method = 'POST', $payload = [], $log_context = [])
	{
		$url = env('curl_base_url', null) . $endpoint;
		$payload = !empty($payload) ? $payload : request()->all();

		$curl = curl_init();

		curl_setopt_array($curl, array(
			// CURLOPT_URL => "http://localhost/vm-minuman-service/public",
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_POSTFIELDS => json_encode($payload),
			CURLOPT_HTTPHEADER => array(
				"content-type: application/json"
			),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		logInit();

		if ($err) {
			logError($url, $payload, $log_context, $err);
			return "cURL Error #:" . $err;
		} else {
			logInfo($url, $payload, $log_context, $response);
			return $response;
		}
	}
}

if(!function_exists('logInit')) {
	function logInit()
	{
		\Log::debug(null);

		$custom_config = [
			'curl' => [
				'driver' => 'single',
				'path' => storage_path('logs/curl/curl.log'),
				'level' => 'info',
			],
			'curl-info' => [
				'driver' => 'daily',
				'path' => storage_path('logs/curl/info/curl-info.log'),
				'level' => 'info',
			],
			'curl-error' => [
				'driver' => 'daily',
				'path' => storage_path('logs/curl/error/curl-error.log'),
				'level' => 'error',
			]
		];
		\Illuminate\Support\Facades\Config::set('logging.channels', array_merge(is_null(\Illuminate\Support\Facades\Config::get('logging.channels')) ? [] : \Illuminate\Support\Facades\Config::get('logging.channels'), $custom_config));
	}
}

if(!function_exists('logError')) {
	function logError($url, $payload, $log_context, $err)
	{
		$log = \Log::stack(['curl', 'curl-error']);
		$log->error('cURL Exec ' . $url . '.' . PHP_EOL . 'Payload:' . prettyResponse($payload) . PHP_EOL, $log_context);
		$log->error('cURL Error #:' . $err . PHP_EOL, $log_context);
		logDB($url, $payload, 'cURL Error #:' . $err);
	}
}

if(!function_exists('logInfo')) {
	function logInfo($url, $payload, $log_context, $response)
	{
		$log = \Log::stack(['curl', 'curl-info']);
		$log->info('cURL Exec ' . $url . '.' . PHP_EOL . 'Payload:' . prettyResponse($payload) . PHP_EOL, $log_context);
		$log->info('cURL Response:' . prettyResponse($response) . PHP_EOL, $log_context);
		logDB($url, $payload, $response);
	}
}

if(!function_exists('logDB')) {
	function logDB($url, $payload, $response)
	{
		checkDB();
		\DB::table('curl_logs')->insert(
			[
				'url' => $url,
				'payload' => json_encode($payload),
				'response' => $response,
				'created_at' => date('Y-m-d H:i:s'),
				'updated_at' => date('Y-m-d H:i:s'),
			]
		);
	}
}

if(!function_exists('checkDB')) {
	function checkDB()
	{
		if(!\Schema::hasTable('curl_logs'))
		{
			\Schema::create('curl_logs', function($table)
			{
				$table->increments('id');
				$table->string('url');
				$table->text('payload');
				$table->text('response');
				$table->timestamps();
			});
		}
	}
}

if(!function_exists('prettyResponse')) {
	function prettyResponse($json)
	{
		if (!is_string($json)) {
			return PHP_EOL.json_encode($json, JSON_PRETTY_PRINT);
		}
		if (isJson($json)) {
			return PHP_EOL.json_encode(json_decode($json, true), JSON_PRETTY_PRINT);
		}
		return $json;
	}
}

if(!function_exists('isJson')) {
	function isJson($string) {
		json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE);
	}
}
<?php
return [
	'api' => [
		'endpoint'  => env('API_BPJS_ANTROL','ENDPOINT-KAMU'),
		'consid'  => env('CONS_ID','CONSID-KAMU'),
		'seckey' => env('SECRET_KEY', 'SECRET-KAMU'),
		'user_key' => env('USER_KEY_ANTROL', 'SECRET-KAMU'),
	]
    ];
?>

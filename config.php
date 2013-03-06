<?php

/*
	Configuration values
	Mail2q.com
	Chris Zarate
	http://chris.zarate.org/
*/



#	Netflix API values:

	$netflix_api = Array (
		'base_uri'      => 'http://api-public.netflix.com',
		'api_key'       => '...',
		'shared_secret' => '...'
	);

	$netflix_callback_url_prefix = 'http://mail2q.com/link/';
	$netflix_success_url_prefix  = 'http://mail2q.com/done/';
	$netflix_instant_player_url  = 'http://www.netflix.com/WiPlayerCommunityAPI?movieid=';


#	Twitter API values:

	$twitter_api = Array (
		'base_uri'      => 'https://api.twitter.com/1',
		'api_key'       => '...',
		'shared_secret' => '...'
	);

	$twitter_auth_token = '...';
	$twitter_auth_token_secret = '...';
	$twitter_user_id = '...';
	$twitter_screen_name = '...';


#	Amazon API values:
	$aws_access_key = '...';
	$aws_secret_key = '...';
	$aws_request_id = '...';


#	Local configuration:
	$users_db = '/path/to/flat/db/users_data';
	$users_datastore = '/path/to/flat/db/users/';
	$oauth_datastore = '/path/to/flat/db/oauth/';
	$debug_datastore = '/path/to/flat/db/debug/';


#	Development and security:
	$hash_salt_oauth = '...';
	$hash_salt_udata = '...';
	$error_debug_mode = false;
	$api_debug_mode = false;


?>
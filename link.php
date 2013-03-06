<?php

/*
	Registration hand-off
	Mail2q.com
	Chris Zarate
	http://chris.zarate.org/
*/


##	Include

	require_once ('include_oauth.php');


##	Error handling

	set_error_handler('HandleError');


##	User registration

	if ( isset ( $_POST['handle'] ) ):

		$valid_email = ValidateEmailAddress ( $_POST['handle'] );
		$twitter_id = ( $valid_email ) ? false : ValidateTwitterHandle ( $_POST['handle'] );

		if ( $valid_email || $twitter_id ):

		#	Set user type:
			$user_type = ( $valid_email ) ? 'mail' : 'twitter';

		#	Hash user handle:
			$udata_hash = UserHash ( $_POST['handle'], $hash_salt_udata );
			$oauth_hash = UserHash ( $_POST['handle'], $hash_salt_oauth );

		#	Get Netflix user_id:
			$netflix_user_id = ( isset ( $users_data [ $udata_hash ] ) ) ? $users_data [ $udata_hash ] : false;

		#	Check for valid address and existing user registration:

			if ( NetflixIsAlreadyRegistered ( $udata_hash, $netflix_user_id ) ):
				trigger_error('001');
			endif;

		#	Begin authorization with Netflix:
			OAuthRequestToken($netflix_api, $oauth_hash, $udata_hash, $netflix_callback_url_prefix . $user_type . '/' . $oauth_hash);

		else:

			trigger_error('002');

		endif;


##	Callback request

	elseif ( isset ( $_GET['type'], $_GET['hash'], $_GET['oauth_token'] ) ):

	#	Register user:
		OAuthRegisterUser ( $netflix_api, $_GET['hash'], $netflix_success_url_prefix . $_GET['type'] );

	endif;


##	Fallback error

	trigger_error('400');


?>
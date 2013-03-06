<?php

/*
	Twitter handler
	Mail2q.com
	Chris Zarate
	http://chris.zarate.org/
*/


##	Include

	require_once ('include_oauth.php');


##	Configuration

	$mail_agent_code = '...';


##	Messages

	$snippets = Array (

		'success_tweet'   => 'We added “%NAME%” to your %QUEUE% queue at position #%POSITION%. %URL%',
		'saved_tweet'     => 'We added “%NAME%” to the “Saved” section of your %QUEUE% queue. %URL%',
		'duplicate_tweet' => '“%NAME%” is already in your %QUEUE% queue.',
		'failure_tweet'   => 'We encountered an error communicating with Netflix. Please relink your account. Sorry.',
		'not_found_tweet' => 'We could not find “%NAME%” in the Netflix database. Sorry.',
		'invalid_tweet'   => 'No valid titles were found in your message. Sorry!'

	);


##	Process e-mail

	if ( isset ( $_POST['From'], $_POST['To'], $_POST['Subject'], $_POST['Date'], $_POST['Body'], $_GET['Agent'] ) && $_GET['Agent'] == $mail_agent_code ):


	#	Extract user's twitter handle:

		$twitter_handle = ExtractTwitterHandle($_POST['Subject']);
		$udata_hash = UserHash($twitter_handle, $hash_salt_udata);


	#	Mention or direct message?
		$message_type = ( strpos ( $_POST['Subject'], ' mentioned ' ) ) ? 'mention' : 'direct';


	#	Extract titles and instructions from message:

		$title_candidates = preg_split ( "/\n\n+/", str_replace ( "\r", "\n", str_replace ( "\r\n", "\n", substr ( $_POST['Body'], 0, 2048 ) ) ), 3 );
		$title_candidates = trim ( StandardizeQuotes ( stripslashes ( urldecode ( $title_candidates[1] ) ) ) );

		if ( preg_match_all ( '/#([^ ]+) */', $title_candidates, $instructions ) ):
			$title_candidates = preg_replace ( '/#[^ ]+ */', '', $title_candidates );
			$instructions = $instructions[1];
		endif;

		if ( preg_match_all ( '/"([^"]+)"/', $title_candidates, $matches ) ):
			$title_candidates = $matches[1];
		else:
			$title_candidates = Array ( preg_replace ( '/@[^ ]+ */', '', $title_candidates ) );print 'duh';
		endif;

		$title_candidates = array_unique ( array_values ( array_filter ( $title_candidates, 'TitleCandidateFilter' ) ) );


	#	Determine if e-mail is authorized:

		if ( isset ( $users_data [ $udata_hash ] ) && file_exists ( $users_datastore . $users_data [ $udata_hash ] ) ):
			$user_data = unserialize ( file_get_contents ( $users_datastore . $users_data [ $udata_hash ] ) );
		else:
			exit;
		endif;


	#	Process title candidates:

		$messages = Array ();

		for ( $i = 0; ( $i < count($title_candidates) && $i < 5 ); $i++ ):

			$title_response = NetflixGetTitle ( $user_data, $title_candidates[$i] );

			if ( is_array($title_response) ):

			#	Set queue position request:
				$title_response['position_request'] = ( in_array ( 'top', $instructions ) ) ? '1' : '';


			#	If the movie is available instantly, set correct queue and provide "watch" links:

				if ( $title_response['instant'] ):
					$title_response['queue'] = ( in_array ( 'disc', $instructions ) ) ? 'disc' : 'instant';
					$title_response['watch'] = ( $title_response['id'] ) ? $netflix_instant_player_url . $title_response['id'] : '';
				else:
					$title_response['queue'] = 'disc';
					$title_response['watch'] = '';
				endif;


			#	Add to queue:
				$queue_response = NetflixAddToQueue ( $user_data, $title_response['ref'], $title_response['queue'], $title_response['position_request'] );


			#	Process response:

				if ( is_array($queue_response) ):
					$response_snippet = ( $queue_response['position'] ) ? 'success' : 'saved';
					$title_response = array_merge ( $title_response, $queue_response );
				elseif ( $queue_response == 'duplicate' ):
					$response_snippet = 'duplicate';
				else:
					$response_snippet = 'failure';
					file_put_contents ( $debug_datastore . 'queue_failure_' . time(), $title_candidates[$i] );
				endif;

			elseif ( $title_response ):
				$title_response = Array ( 'name' => $title_candidates[$i] );
				$response_snippet = 'not_found';
				file_put_contents ( $debug_datastore . 'not_found_' . time(), $title_candidates[$i] );
			else:
				$title_response = Array ( 'name' => $title_candidates[$i] );
				$response_snippet = 'failure';
				array_push ( $messages, PopulateMessage ( $snippets[$response_snippet . '_tweet'], $title_response ) );
				file_put_contents ( $debug_datastore . 'failure_' . time(), $title_candidates[$i] );
				break;
			endif;

			array_push ( $messages, PopulateMessage ( $snippets[$response_snippet . '_tweet'], $title_response ) );

		endfor;


	#	Finalize messages:

		if ( empty ( $messages ) ):
			$messages = Array ( $snippets['invalid_tweet'] );
			file_put_contents ( $debug_datastore . 'tweet_' . time(), $_POST['Subject'] . "\n\n" . $_POST['Body'] );
		endif;


	#	Send messages:

		if ( $message_type == 'direct' && AreTwitterFriends ( $twitter_handle ) ):

			foreach ( $messages as $message ):
				SendDM ( $message, $twitter_handle );
			endforeach;

		else:

			$status_id = ( preg_match ( '/in_reply_to_status_id=([0-9]+)/', $_POST['Body'], $matches ) ) ? $matches[1] : false;

			foreach ( $messages as $message ):
				SendTweet ( '@' . $twitter_handle . ' ' . $message, $status_id );
			endforeach;

		endif;


	endif;


?>
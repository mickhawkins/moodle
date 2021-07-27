<?php
/**
 * Test script to run multiple redirects.
 *
 * If a localhost is set, it will attempt to redirect there at step 'localstep'.
 * Otherwise, all other redirects will be to this page (until $steps is reached).
 *
 * This script forms part of filelib testing.
 * See test_curl_redirect_security for an example.
 *
 */

// Number of redirect steps to take.
$steps = !empty($_GET['steps']) ? (int) $_GET['steps'] : 0;
// Step at which to redirect to a local page.
$localstep = !empty($_GET['localstep']) ? (int) $_GET['localstep'] : 0;
// The test site's wwwroot eg https://localhost/stable_master.
$localhost = $_GET['localhost'] ?? 'https://localhost';
// The URL of this page.
$remotehost = $_GET['remotehost'] ?? '';
// Count of pages reached so far (including this one). Do not include in initial call.
$count = !empty($_GET['count']) ? (int) $_GET['count'] : 1;

// Only redirect again if we know the remote host and haven't reached the required steps.
if ($remotehost && $count < $steps) {

	$params = implode('&', [
		"steps={$steps}",
		"localstep={$localstep}",
		"localhost={$localhost}",
		"remotehost={$remotehost}",
		"count=" . ($count + 1),
	]);
error_log($params);
	// Redirect to the local page if we have reached the required step.
	if ($localstep == $count) {
		header("Location: {$localhost}/lib/test/test_redir_sec_local.php?{$params}");
	} else {
		// Redirect to this remote page again.
		header("Location: {$remotehost}?{$params}");
	}
}

echo 'done';
die();

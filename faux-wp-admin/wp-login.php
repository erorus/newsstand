<?php

$showError = false;
if (isset($_REQUEST['log']) && isset($_REQUEST['pwd'])) {
	$showError = true;

	$usr = substr($_REQUEST['log'], 0, 50);
	$pwd = substr($_REQUEST['pwd'], 0, 50);

	$parts = [
		date('Y-m-d H:i:s'),
		$_SERVER['REMOTE_ADDR'],
		substr($_SERVER['HTTP_USER_AGENT'], 0, 250),
		substr($_REQUEST['log'], 0, 50),
		substr($_REQUEST['pwd'], 0, 50),
		$_SERVER['HTTP_HOST'],
		$_SERVER['DOCUMENT_URI'],
	];

	$path = __DIR__.'/../logs/wp-admin.csv';
	if (($f = fopen($path, 'a')) !== false) {
		fputcsv($f, $parts);
		fclose($f);
	}
}

?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	
	<title>WordPress › Log In</title>
	<link rel="stylesheet" id="wp-admin-css" href="wp-admin.css" type="text/css" media="all">
<link rel="stylesheet" id="colors-fresh-css" href="colors-fresh.css" type="text/css" media="all">
<meta name="robots" content="noindex,nofollow">
	<style>
		.login h1 a {background-image: url('wordpress-logo.png');}
	</style>
	</head>
	<body class="login">
	<div id="login">
		<h1><a href="http://wordpress.org/" title="Powered by WordPress">WordPress</a></h1>
		<?php
		if ($showError) echo <<<'EOF'
		<div id="login_error">	<strong>ERROR</strong>: Invalid username. <a href="#" title="Password Lost and Found">Lost your password</a>?<br>
		</div>
EOF;
		?>
<form name="loginform" id="loginform">
	<p>
		<label for="user_login">Username<br>
		<input type="text" name="log" id="user_login" class="input" value="" size="20" tabindex="10"></label>
	</p>
	<p>
		<label for="user_pass">Password<br>
		<input type="password" name="pwd" id="user_pass" class="input" value="" size="20" tabindex="20"></label>
	</p>
	<p class="forgetmenot"><label for="rememberme"><input name="rememberme" type="checkbox" id="rememberme" value="forever" tabindex="90"> Remember Me</label></p>
	<p class="submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button-primary" value="Log In" tabindex="100">
		<input type="hidden" name="redirect_to" value="/wp-admin/">
		<input type="hidden" name="testcookie" value="1">
	</p>
</form>

<p id="nav">
<a href="#" title="Password Lost and Found">Lost your password?</a>
</p>

<script type="text/javascript">
function wp_attempt_focus(){
setTimeout( function(){ try{
d = document.getElementById('user_login');
d.focus();
d.select();
} catch(e){}
}, 200);
}

wp_attempt_focus();
if(typeof wpOnload=='function')wpOnload();
</script>

	<p id="backtoblog"><a href="/" title="Are you lost?">← Back to Home</a></p>
	
	</div>

	
		<div class="clear"></div>
	
	
	</body>
	</html>
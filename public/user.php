<?php

header('HTTP/1.1 410 Gone');
header('Expires: '.Date(DATE_RFC1123, strtotime('+1 year')));

if (isset($_GET['rss'])) {
    $self = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $date = Date(DATE_RFC2822);
    $guid = md5($date);

    $rss = <<<EOF
		<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
		<channel>
			<title>The Undermine Journal - Old User RSS Feed</title>
			<link>https://theunderminejournal.com/</link>
			<atom:link href="$self" rel="self" type="application/rss+xml"/>
			<description>THIS FEED IS NO LONGER IN USE, REMOVE IT FROM YOUR RSS READER.</description>
			<language>en-us</language>
			<lastBuildDate>$date</lastBuildDate>
			<docs>http://blogs.law.harvard.edu/tech/rss</docs>
            <item>
                <title>THIS FEED IS NO LONGER IN USE $date</title>
                <description>This feed is no longer used, remove it from your RSS reader. $date</description>
                <guid isPermaLink="false">$guid</guid>
                <link>https://theunderminejournal.com</link>
                <pubDate>$date</pubDate>
            </item>
		</channel>
		</rss>
EOF;

    if (preg_match('/Mozilla/',$_SERVER['HTTP_USER_AGENT'])>0) {
        header('Content-type: text/xml');
    } else header('Content-type: application/rss+xml');
    header('Connection: close');

    echo $rss;
    exit;
}

echo '410 Gone';
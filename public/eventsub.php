<?php

header('HTTP/1.1 410 Gone');
header('Expires: '.date(DATE_RFC1123, strtotime('+1 year')));

echo '410 Gone';
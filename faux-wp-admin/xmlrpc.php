<?php

switch ($_SERVER["REQUEST_METHOD"]) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    default:
        handleOther();
        break;
}

function handleOther() {
    header($_SERVER["SERVER_PROTOCOL"] . ' 405 Method Not Allowed');
    header('Content-Type: text/plain');
    echo '405 Method Not Allowed';
}

function handleGet() {
    header('Content-Type: text/plain');
    echo 'XML-RPC server accepts POST requests only.';
}

function handlePost() {
    $parts = [
        "--- start",
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'],
        substr($_SERVER['HTTP_USER_AGENT'], 0, 250),
        $_SERVER['HTTP_HOST'],
        $_SERVER['DOCUMENT_URI'],
    ];

    if (($h = fopen('php://input', 'r')) !== false) {
        $parts[] = fread($h, 16384);
        fclose($h);
    }

    $path = __DIR__.'/../logs/wp-xmlrpc.log';
    if (($h = fopen($path, 'a')) !== false) {
        fputs($h, implode("\n", $parts));
        fclose($h);
    }

    header('Content-Type: text/xml');
    echo <<<'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<methodResponse>
  <fault>
    <value>
      <struct>
        <member>
          <name>faultCode</name>
          <value><int>403</int></value>
        </member>
        <member>
          <name>faultString</name>
          <value><string>Incorrect username or password.</string></value>
        </member>
      </struct>
    </value>
  </fault>
</methodResponse>
EOF;

}
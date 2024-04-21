<?php

use OpenSwoole\WebSocket\{Server, Frame};
use OpenSwoole\Constant;
use OpenSwoole\Table;


$server = new Server("0.0.0.0", 9501, Server::SIMPLE_MODE, Constant::SOCK_TCP);

$fds = new Table(1024);
$fds->column('fd', Table::TYPE_INT, 4);
$fds->column('name', Table::TYPE_STRING, 16);
$fds->create();


$server->set([
    'log_level' => 0,
    'log_file' => 'logs/openswoole.log',
    'log_rotation' => OpenSwoole\Constant::LOG_ROTATION_DAILY,
    'log_date_format' => '%Y-%m-%d %H:%M:%S',
    'log_date_with_microseconds' => false,
    'trace_flags' => OpenSwoole\Constant::TRACE_ALL,
]);

$server->on("Start", function (Server $server) {
    echo "OpenSwoole WebSocket Server is started at " . $server->host . ":" . $server->port . "\n";
});

$server->on('Open', function (Server $server, OpenSwoole\Http\Request $request) use ($fds) {
    $fd = $request->fd;
    $parameters = $request->get;
    $clientName = $parameters['username'] ?? sprintf("User-%'.02d", $request->fd);
    $fds->set($request->fd, [
        'fd' => $fd,
        'name' => sprintf($clientName)
    ]);
    echo "Connection <{$fd}> open by {$clientName}. Total connections: " . $fds->count() . "\n";
    foreach ($fds as $key => $value) {
        if ($key == $fd) {
            $message = formatMessage(
                'Welcome ' . $clientName . ', there are ' . $fds->count() . ' connections',
            );
            $server->push($request->fd, $message);
        } else {
            $message = formatMessage(
                'A new client (' . $clientName . ') is joining to the party',
            );
            $server->push($key, $message);
        }
    }
});

$server->on('Message', function (Server $server, Frame $frame) use ($fds) {
    $sender = $fds->get(strval($frame->fd), "name");
    echo "Received from " . $sender . ", message: {$frame->data}" . PHP_EOL;
    foreach ($fds as $key => $value) {
        $message = formatMessage(
            $frame->data,
            $sender,
        );
        $server->push($key, $message);
    }
});

$server->on('Close', function (Server $server, int $fd) use ($fds) {
    $fds->del($fd);
    echo "Connection close: {$fd}, total connections: " . $fds->count() . "\n";
});

$server->on('Disconnect', function (Server $server, int $fd) use ($fds) {
    $fds->del($fd);
    echo "Disconnect: {$fd}, total connections: " . $fds->count() . "\n";
});

$server->start();

function formatMessage(string $message, string $from = 'Server'): string
{
    $message = [
        'message' => $message,
        'from' => $from,
        'time' => (new DateTimeImmutable())->format('Y-m-d H:i:s.u')
    ];

   return json_encode($message);
}
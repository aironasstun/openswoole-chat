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
    $clientName = sprintf("User-%'.02d", $request->fd);
    $fds->set($request->fd, [
        'fd' => $fd,
        'name' => sprintf($clientName)
    ]);
    echo "Connection <{$fd}> open by {$clientName}. Total connections: " . $fds->count() . "\n";
    foreach ($fds as $key => $value) {
        if ($key == $fd) {
            $server->push($request->fd, "Welcome {$clientName}, there are " . $fds->count() . " connections");
        } else {
            $server->push($key, "A new client ({$clientName}) is joining to the party");
        }
    }
});

$server->on('Message', function (Server $server, Frame $frame) use ($fds) {
    $sender = $fds->get(strval($frame->fd), "name");
    echo "Received from " . $sender . ", message: {$frame->data}" . PHP_EOL;
    foreach ($fds as $key => $value) {
        if ($key == $frame->fd) {
            $server->push($frame->fd, "ME: " . $frame->data);
        } else {
            $server->push($key, "{$sender}: " . $frame->data);
        }
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
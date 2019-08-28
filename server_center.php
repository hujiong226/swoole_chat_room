<?php

require_once __DIR__ . '/global.php';

//创建Server对象，监听 127.0.0.1:9501端口
$serv = new Swoole\Server(Config::get('host'), Config::get('port')); 

//监听连接进入事件
$serv->on('Connect', function ($serv, $fd) {
    Console::log("Client: {$fd} Connect.");
    $serv->send($fd, "Server Connected");
});

//监听数据接收事件
$serv->on('Receive', function ($serv, $fd, $from_id, $data) {
    
    Console::log("Receive: {$data}.");
    
    $data_arr = explode("|||", $data);
    foreach ($data_arr as $val)
    {
        if(!empty($val))
        {
            $val = json_decode($val, 1);
            if($val['type'] == 'into')
            {
                MyRedis::instance()->hset("server_center_client_list", str_replace(".", "a", $val['host']) . '_' . $val['port'], $fd);
            }
            if($val['type'] == 'forward')
            {
                $serv->send(MyRedis::instance()->hget("server_center_client_list", str_replace(".", "a", $val['host']) . '_' . $val['port']), json_encode([
                    'fd' => $val['fd'],
                    'touid' => $val['touid'],
                    'data' => $val['data']
                ]) . '|||');
            }
        }
    }
});

//监听连接关闭事件
$serv->on('Close', function ($serv, $fd) {
    Console::log("Client: Close.");
});

MyRedis::instance()->del("server_center_client_list");
//启动服务器
Console::log("启动服务.");
if($serv->start())
{
    Console::log("服务关闭.");
}
else
{
    Console::log("服务启动失败.");
}


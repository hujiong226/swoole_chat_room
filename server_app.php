<?php

require_once __DIR__ . '/global.php';

class Client
{
    private static $cli = null;
    
    public static function instance()
    {
        if(self::$cli == null)
        {
            Console::log("new swoole_client");
            self::$cli = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        }
        return self::$cli;
    }
}

class WSServer
{
    private static $sev = null;
    
    public static function instance()
    {
        if(self::$sev == null)
        {
            //创建websocket服务器对象，监听0.0.0.0:9502端口
            Console::log("new swoole_websocket_server");
            self::$sev = new swoole_websocket_server(Config::get('host'), Config::get('port'));
            self::$sev->set(array('worker_num' => 1));
        }
        return self::$sev;
    }
}

WSServer::instance()->on('WorkerStart', function ($serv, $worker_id){
    
    if($worker_id >= $serv->setting['worker_num']) {
        Console::log("task worker");
    } else {
        Console::log("event worker");
    }
    if($worker_id == 0) {
        
        //注册连接成功回调
        Client::instance()->on("connect", function($cli) {
            $cli->send(json_encode([
                'type' => 'into',
                'host' => Config::get('host'),
                'port' => Config::get('port')
            ]));
        });

        //注册数据接收回调
        Client::instance()->on("receive", function($cli, $data){
            Console::log("Received: ".$data."");
            
            $data_arr = explode("|||", $data);
            foreach ($data_arr as $val)
            {
                if(!empty($val))
                {
                    $val = json_decode($val, 1);

                    if(WSServer::instance()->exist($val['fd']))
                    {
                        WSServer::instance()->push($val['fd'], json_encode($val['data']));
                    }
                    else
                    {
                        $user_connect_info = json_decode(MyRedis::instance()->hget("room_" . $val['data']['rid'] . "_user_list", $val['touid']), 1);
                        unset($user_connect_info['connect_list'][Config::get('host') . '_' . Config::get('port') . '_' . $val['fd']]);
                        if(empty($user_connect_info['connect_list']))
                        {
                            MyRedis::instance()->hdel("room_" . $val['data']['rid'] . "_user_list", $val['touid']);
                        }
                        else
                        {
                            MyRedis::instance()->hset("room_" . $val['data']['rid'] . "_user_list", $val['touid'], json_encode($user_connect_info));
                        }
                    }
                }
            }
        });

        //注册连接失败回调
        Client::instance()->on("error", function($cli){
            Console::log("Connect failed");
        });

        //注册连接关闭回调
        Client::instance()->on("close", function($cli){
            Console::log("Connection close");
        });

        //发起连接
        Client::instance()->connect(Config::get('chost'), Config::get('cport'), 0.5);
        
    }    
    
});

function checkConnect($host, $port, $fd, $rid, $uid, $key)
{
    if($host == Config::get('host') && $port == Config::get('port'))
    {
        if(WSServer::instance()->exist($fd))
        {
            return 1;
        }
        else
        {
            $user_connect_info = json_decode(MyRedis::instance()->hget("room_" . $rid . "_user_list", $uid), 1);
            unset($user_connect_info['connect_list'][$key]);
            if(empty($user_connect_info['connect_list']))
            {
                MyRedis::instance()->hdel("room_" . $rid . "_user_list", $uid);
            }
            else
            {
                MyRedis::instance()->hset("room_" . $rid . "_user_list", $uid, json_encode($user_connect_info));
            }
        }
    }
    else
    {
        return 2;
    }

    return 0;
}

function broadcast($message)
{
    $message = json_decode($message, 1);
    
    if($message['touid'] == 'room')
    {
        // 推送给全房间的人
        $room_user_list = MyRedis::instance()->hgetall("room_" . $message['data']["rid"] . "_user_list");
        foreach ($room_user_list as $k => $v)
        {
            $v = json_decode($v, 1);
            foreach ($v['connect_list'] as $k2 => $v2)
            {
                $rs = checkConnect($v2['host'], $v2['port'], $v2['fd'], $message['data']["rid"], $k, $k2);
                if($rs == 1)
                {
                    WSServer::instance()->push($v2['fd'], json_encode($message['data']));
                }
                if($rs == 2)
                {
                    Client::instance()->send(json_encode([
                        'type' => 'forward',
                        'host' => $v2['host'],
                        'port' => $v2['port'],
                        'fd' => $v2['fd'],
                        'touid' => $k,
                        'data' => $message['data']
                    ]) . '|||');
                }
            }
        }
    }
    else
    {
        // 推送给指定人
        $user_connect_info = json_decode(MyRedis::instance()->hget("room_" . $message['data']["rid"] . "_user_list", $message['touid']), 1);
        foreach ($user_connect_info['connect_list'] as $k => $v)
        {
            $rs = checkConnect($v['host'], $v['port'], $v['fd'], $message['data']["rid"], $message['touid'], $k);
            if($rs == 1)
            {
                WSServer::instance()->push($v['fd'], json_encode($message['data']));
            }
            if($rs == 2)
            {
                    Client::instance()->send(json_encode([
                        'type' => 'forward',
                        'host' => $v['host'],
                        'port' => $v['port'],
                        'fd' => $v['fd'],
                        'touid' => $message['touid'],
                        'data' => $message['data']
                    ]) . '|||');
            }
        }
        // 推送给自己
        $user_connect_info = json_decode(MyRedis::instance()->hget("room_" . $message['data']["rid"] . "_user_list", $message['data']["uid"]), 1);
        foreach ($user_connect_info['connect_list'] as $k => $v)
        {
            $rs = checkConnect($v['host'], $v['port'], $v['fd'], $message['data']["rid"], $message['data']["uid"], $k);
            if($rs == 1)
            {
                WSServer::instance()->push($v['fd'], json_encode($message['data']));
            }
            if($rs == 2)
            {
                    Client::instance()->send(json_encode([
                        'type' => 'forward',
                        'host' => $v['host'],
                        'port' => $v['port'],
                        'fd' => $v['fd'],
                        'touid' => $message['data']["uid"],
                        'data' => $message['data']
                    ]) . '|||');
            }
        }
    }
}


//监听WebSocket连接打开事件
WSServer::instance()->on('open', function ($ws, $request) {
    
    $request = std_decode($request);
    
    Console::log("client-".$request['fd']." is open");
    
    // 记录该服务器的连接，可用于统计这个服务有多少连接数
    MyRedis::instance()->hset("server_connect_" . str_replace(".", "a", Config::get('host')) . '_' . Config::get('port'), $request['fd'], json_encode(['rid'=>$request['get']["rid"], 'uid'=>$request['get']["uid"]]));
    
    // 记录进入该房间的用户，可用于统计这个房间有多少人，一个人在同一房间开多端只算一个用户
    $user_connect_info = MyRedis::instance()->hget("room_" . $request['get']["rid"] . "_user_list", $request['get']["uid"]);
    if(empty($user_connect_info))
    {
        $user_connect_info = [
            'connect_list' => [
                Config::get('host') . '_' . Config::get('port') . '_' . $request['fd'] => [
                    'host' => Config::get('host'),
                    'port' => Config::get('port'),
                    'fd' => $request['fd']
                ]
            ]
        ];
    }
    else
    {
        $user_connect_info = json_decode($user_connect_info, 1);
        $user_connect_info['connect_list'][Config::get('host') . '_' . Config::get('port') . '_' . $request['fd']] = [
            'host' => Config::get('host'),
            'port' => Config::get('port'),
            'fd' => $request['fd']
        ];
    }
    MyRedis::instance()->hset("room_" . $request['get']["rid"] . "_user_list", $request['get']["uid"], json_encode($user_connect_info));
    
    $user_list = Config::get('user_list');
    broadcast(json_encode([
        'touid' => 'room',
        'data' => [
            'type' => 'into',
            'rid' => $request['get']["rid"],
            'uid' => $request['get']["uid"],
            'nickname' => $user_list[$request['get']["uid"]]['nickname']
        ]
    ]));
    
//    var_dump($request['fd'], $request['get'], $request['server']);
    
});

//监听WebSocket消息事件
WSServer::instance()->on('message', function ($ws, $frame) {
    
    broadcast($frame->data);
    
    Console::log("Message: {$frame->data}");
    
});

//监听WebSocket连接关闭事件
WSServer::instance()->on('close', function ($ws, $fd) {
    
    $rid_uid = json_decode(MyRedis::instance()->hget("server_connect_" . str_replace(".", "a", Config::get('host')) . '_' . Config::get('port'), $fd), 1);
    
    $user_connect_info = json_decode(MyRedis::instance()->hget("room_" . $rid_uid["rid"] . "_user_list", $rid_uid["uid"]), 1);
    unset($user_connect_info['connect_list'][Config::get('host') . '_' . Config::get('port') . '_' . $fd]);
    if(empty($user_connect_info['connect_list']))
    {
        MyRedis::instance()->hdel("room_" . $rid_uid["rid"] . "_user_list", $rid_uid["uid"]);
    }
    else
    {
        MyRedis::instance()->hset("room_" . $rid_uid["rid"] . "_user_list", $rid_uid["uid"], json_encode($user_connect_info));
    }
    
    MyRedis::instance()->hdel("server_connect_" . str_replace(".", "a", Config::get('host')) . '_' . Config::get('port'), $fd);
    
    $user_list = Config::get('user_list');
    broadcast(json_encode([
        'touid' => 'room',
        'data' => [
            'type' => 'out',
            'rid' => $rid_uid["rid"],
            'uid' => $rid_uid["uid"],
            'nickname' => $user_list[$rid_uid["uid"]]['nickname']
        ]
    ]));
    
    Console::log("client-{$fd} is closed");
    
});

MyRedis::instance()->del("server_connect_" . str_replace(".", "a", Config::get('host')) . '_' . Config::get('port'));
WSServer::instance()->start();

<?php
//phpinfo();die();
require_once __DIR__ . '/global.php';
$user_list = Config::get('user_list');
$rid = $_GET['rid'];
$uid = $_GET['uid'];
$user_info = $user_list[$uid];
if(empty($rid))
{
    echo 'rid 错误！';
    die();
}
if(empty($user_info))
{
    echo 'uid 错误！';
    die();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>综合管理后台</title>
    <script src="https://code.jquery.com/jquery-1.11.1.js"></script>
    <script src="static/js/json.js"></script>
    <style>
        #room_user_list a{ padding: 0 5px; }
    </style>
</head>
<body>
    <div>room id：<?= $rid ?></div>
    
    <div>my：<?= $user_info['nickname'] ?></div>
    <div>room user list:<span id='room_user_list'><?php
        $room_uid_list = MyRedis::instance()->hkeys("room_" . $rid . "_user_list");
        foreach ($room_uid_list as $val)
        {
            echo '<a class="uid_' . $val . '" uid="' . $val . '" href="javascript:;">' . $user_list[$val]['nickname'] . '</a>';
        }
    ?><a class="" uid="room" href="javascript:;">全房间</a></span></div>
    <form id="form">
        对
        <input id="touid" type="hidden" value="room" /><span id="tonickname">全房间</span>
        说：
        <input id="msg" type="text" value="" />
        <input type="submit" value="发送" />
    </form>
    <div id="message_list"></div>
    <script>
        (function($){
            
            function appendMessage(msg)
            {
                $('#message_list').prepend('<p>' + msg + '</p>');
            }
            
            var wsServer = "ws://39.108.64.255:<?= $user_info['id']=='1003'?'9602':'9601' ?>?rid=<?= $rid ?>&uid=<?= $user_info['id'] ?>";
            var websocket = new WebSocket(wsServer);
            websocket.onopen = function (evt) {
                console.info("connected to websocket server.");
//                appendMessage("connected to websocket server.");
            };

            websocket.onclose = function (evt) {
                appendMessage("Disconnected");
            };

            websocket.onmessage = function (evt) {
                var data = $.evalJSON(evt.data);
                console.info(data);
                if(data['type'] == 'into')
                {
                    $('#room_user_list .uid_' + data['uid']).remove();
                    $('#room_user_list').prepend('<a class="uid_' + data['uid'] + '" uid="' + data['uid'] + '" href="javascript:;">' + data['nickname'] + '</a>');
                    appendMessage(data['nickname'] + " 进入房间！");
                }
                if(data['type'] == 'out')
                {
                    $('#room_user_list .uid_' + data['uid']).remove();
                    appendMessage(data['nickname'] + " 离开房间！");
                }
                if(data['type'] == 'msg')
                {
                    appendMessage(data['nickname'] + " 说：" + data['msg']);
                }
            };

            websocket.onerror = function (evt, e) {
                appendMessage('Error occured: ' + evt.data);
            };

            $('#form').submit(function(e){
                e.preventDefault(); // prevents page reloading
                websocket.send($.toJSON({
                    'touid' : $('#touid').val(),
                    'data' : {
                        'type' : 'msg',
                        'rid' : '<?= $rid ?>',
                        'uid' : '<?= $user_info['id'] ?>',
                        'nickname' : '<?= $user_info['nickname'] ?>',
                        'msg':$('#msg').val()
                    }
                }));
                $('#msg').val('');
                return false;
            });
            
            $('#room_user_list').on('click', 'a', function(){
                var uid = $(this).attr('uid');
                var nickname = $(this).text();
                $('#touid').val(uid);
                $('#tonickname').html(nickname);
            });
            
        })(jQuery);
    </script>
</body>
</html>

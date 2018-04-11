$(function(){
    var ws = new WebSocket('ws://127.0.0.1:9527');
    ws.onopen = function()
    {
        ws.send("发送数据");
        console.log('已连接');
    };
});

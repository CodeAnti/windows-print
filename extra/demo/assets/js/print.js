$(function(){
    var ws = new WebSocket('ws://127.0.0.1:9527/print');
    ws.onopen = function(e) {

    };
    ws.onmessage = function(e) {
        var data = JSON.parse(e.data);
        if (data.code === '100000' && data.type === 'PRINTER_LIST') {
            console.log(data);
        }
        if (data.code === '100000' && data.type === 'PRINT_ORDER') {
            console.log('订单打印成功');
        }
    };

    $('#printer').click(function () {
        var data = {
            "type":"PRINT_ORDER",
            "printer": 'XP-58', // 打印机的名称
            "data":{
                "order_no":"201804121205",
                "table_number": 12,
                "price":"20.00"
            }
        };
        var content = JSON.stringify(data);
        ws.send(content);
    });
});

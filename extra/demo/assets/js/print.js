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
                "order_no":"201712081011283442",
                "pay_time":"1513060320",
                "table_num": 12,
                "total_price": 84,
                "order_products": [
                    {'title': '大煮干丝', 'num': 2, 'price': 30},
                    {'title': '大盘鸡', 'num': 1, 'price': 40},
                    {'title': '紫菜蛋汤', 'num': 1, 'price': 10},
                    {'title': '米饭', 'num': 2, 'price': 4}
                ]
            }
        };
        var content = JSON.stringify(data);
        ws.send(content);
    });
});

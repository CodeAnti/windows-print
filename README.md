### 安装innosetup_5.5.9.exe,生成安装程序
```
安装文件：extra\innosetup_5.5.9.exe
创建iss文件：File->New (根据要求填写app信息,可参考shiwanke.iss)
编译iss文件生成exe安装程序: Build->Compile

参考链接：https://blog.csdn.net/ruifangcui7758/article/details/6662646
```

### 启动Printer Server
```
src/php7.0/php-print.exe src/bootstrap/start.php
```

### demo
```
1.启动Printer Server
2.浏览器打开extra/demo/print.html
(F12查看console)
(当websocket链接成功，会打印出当前电脑可用的打印机)

注意：打印机的名字，demo中print.js是默认写死了名字，根据实际业务情况修改该值
```


<?php
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

class Dayin{
	function __construct(){
	}
	
	/**
	* 获取打印机列表
	*/
	function getPrinterList(){
		$list = [];
		$wmi = new COM('winmgmts://');
		
		$processor = $wmi->ExecQuery("SELECT * FROM Win32_Printer");
		foreach($processor as $obj){
			$list[] = $obj->Name;
		}
		
		return $list;
	}
	
	/**
	* 打印到
	*/
	function dayin($printer=[]){
		try {
			// Enter the share name for your USB printer here
			//$connector = null;
			$connector = new WindowsPrintConnector("sheng_print");
			
			/* Print a "Hello world" receipt" */
			$printer = new Printer($connector);
			$printer -> text(json_encode($printer)."Hello World!\n");
			$printer -> cut();
			
			/* Close printer */
			$printer -> close();
			
			$msg = ['status'=>1001, 'msg'=>'打印成功'];
		} catch (Exception $e) {
			$msg = ['status'=>2001, 'msg' => "Couldn't print to this printer: " . $e -> getMessage()];
		}
		
		return $msg;
	}
}
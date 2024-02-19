<?php

/**
 * Percona MongoDB3.4 HotBackup PHP Script 
 * https://github.com/hcymysql/PMongo-HotBackup
 *
 * UPDATE:
 * Modified by: hcymysql 2018/12/04
 * 1、在线热备份（物理文件拷贝）替代mongodump逻辑备份导出bson
 *
 * 环境准备: 
 * shell> yum install -y php-pear php-devel php gcc openssl openssl-devel cyrus-sasl cyrus-sasl-devel 
 * shell> pecl install mongo
 * You should add "extension=mongo.so" to php.ini
 */
 
ini_set('date.timezone','Asia/Shanghai');
//error_reporting(7);

//*************修改下面的配置信息***************//
$user = "admin"; //使用root用户权限
$pwd = '123456'; 
$host = '192.168.180.26'; //在从库上热备
$port = '27017';
$authdb = 'admin'; //权限认证数据库
$BAKDIR = "/data/bak/";
$BAKDIR .= date('Y-m-d_H-i-s')."_bak";


//*************下面的代码不用修改***************//
$m = new MongoBak($user,$pwd,$host,$port,$authdb,$BAKDIR);

Class MongoBak{

    function __construct(){ 
	     $a = func_get_args();
            $f = 'backup';
            call_user_func_array(array($this,$f),$a); 
    }

    function backup($p1,$p2,$p3,$p4,$p5,$p6){
           $this->mkdirs("{$p6}");
	    $mongo = new MongoClient("mongodb://{$p1}:{$p2}@{$p3}:{$p4}/{$p5}");
	    $mongo->setReadPreference(MongoClient::RP_SECONDARY); //如果你想在主库上备份，把这行代码注销掉即可，并把脚本部署在主库上。
           $db = $mongo->admin;
	    echo "\n".date('Y-m-d H:i:s')."    backup is running......\n";
           $r = $db->command(
  	          array(
          	       'createBackup' => 1,
                    'backupDir' => $p6),
     	          array('timeout' => -1)    
	    );

	//print_r($r); //调试备份错误日志使用，如备份失败，打开注释。

	    if($r['ok'] == 1){
  	           echo date('Y-m-d H:i:s')."    backup is success.\n";
	    } else{
      	    echo date('Y-m-d_H:i:s')."    backup is failure.\n";
	    }	
    }
    
    function mkdirs($dir, $mode = 0777){
        if (!is_dir($dir)){
            mkdir($dir, $mode, true);
            //chmod($dir, $mode);
            echo "BackupDir : ${dir} is created.\n";
    	}
    }

}

?>

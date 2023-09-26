Percona MongoDB HotBackup热备份工具

前言：

目前官方MongoDB社区版是不支持Hot Backup热备份的，我们只能通过mongodump等逻辑备份工具导出bson文件，再mongorestore导入，类似MySQL的mysqldump工具。

在备份副本集时，我们需指定--oplog选项记录备份间产生的增量数据，类似mysqldump --single-transaction --master-data=2（做一致性快照并记录当前的binlog点）。

对副本集的成员恢复，需先切成单机版，mongorestore必须指定--oplogReplay选项，以恢复到某一时刻的快照，最后还需填充oplog（增量数据以哪个位置点开始断点续传），mongorestore -d local -c oplog.rs dump/oplog.bson，最后一步再切为副本集成员重新启动。

中小型数据库备份起来简单快捷，如果过TB级的数据量，那将是痛苦的。

如果你的oplog设置过小，很有可能在备份恢复这段时间，oplog被覆盖重写，那么你将永远无法加入副本集集群里。


概述：

Percona MongoDB3.2版本默认开始支持WiredTiger引擎的在线热备份，解决了官方版只能通过mongodump逻辑备份这一缺陷。

参考文献：

https://docs.percona.com/percona-server-for-mongodb/6.0/hot-backup.html

注意事项：

1、要在当前dbpath中对数据库进行热备份，请在admin数据库上以管理员身份运行createBackup命令，并指定备份目录。

2、可以替换一台从库为Percona MongoDB，做备份使用。（我这里实测是Percona MongoDB 3.4版本）

Percona MongoDB HotBackup热备份原理：

你可以想象成xtrabackup工具

备份：

1、首先会启动一个后台检测的进程，实时检测MongoDB Oplog的变化，一旦发现oplog有新的日志写入，立刻将日志写入到日志文件WiredTiger.backup中（你可以strings WiredTiger.backup查看oplog操作日志的变化）；

2、复制MongoDB dbpath的数据文件和索引文件到指定的备份目录里。
......

恢复：

1、将WiredTiger.backup日志进行回放，将操作日志变更应用到WiredTiger引擎里，最终得到一致性快照恢复。

2、把备份目录里的数据文件直接拷贝到你的dbpath下，然后启动MongoDB即可，会自动接入副本集集群。

-----------------------------------------------------------------------------------------------------------------------------------------------------------

这里我封装了一个PHP脚本，直接在SHELL里运行即可。

1、环境准备：

shell> yum install -y php-pear php-devel php gcc openssl openssl-devel cyrus-sasl cyrus-sasl-devel 

2、php-mongo驱动安装：

shell> pecl install mongo

把extension=mongo.so加入到/etc/php.ini最后一行。

3、创建mongodb超级用户权限（备份时使用）

db.createUser({user:"admin",pwd:"123456",roles:[{role:"root",db:"admin"}]})

4、修改pmongo_bak.php配置信息

//*************修改下面的配置信息***************//

$user = "admin"; //使用root用户权限

$pwd = '123456'; 

$host = '192.168.180.26'; //在从库上热备

$port = '27017';

$authdb = 'admin'; //权限认证数据库

$BAKDIR = "/data/bak/";

$BAKDIR .= date('Y_m_d_H_i_s');

//*************下面的代码不用修改***************//

$m = new MongoBak($user,$pwd,$host,$port,$authdb,$BAKDIR);

......

5、前台运行：

shell> php pmongo_bak.php（以root权限运行）

6、写入系统crontab里

00 01 * * * /usr/bin/php /root/php_mongodb/pmongo_bak.php > /root/php_mongodb/bak_status.log 2 >&1

7、不支持远程备份，需将备份脚本部署在从库里。如果你想把数据备份到远程，可以采用NFS等文件系统mount挂载上。




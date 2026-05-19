## ITDB: IT项目无数据库IT资产管理软件  

**注意，此项目官方已不再维护，现由爱好者pyccl维护**


关于
=====

描述
-----------

ITDB是一个基于网络的资产库存管理工具，用于存储在办公环境中发现的资产信息，重点是不限于IT资产。它不是ITIL/CMDB法规遵从性的目标，但它为我服务了多年，希望它也能为您服务:-)  
ITDB附带源代码，在GNU公共许可证下发布。  

[![](itdb-overview.png)](itdb-overview.png)


安全性
--------

请不要把ITDB暴露在公众的网络上。它是不安全的，它是针对内部网的。如果您需要这样做，请至少使用https并在您的web服务器上配置一个HTTP auth密码，这样它将隐藏在密码后面。

待办事项
----
官方已经不再维护，现在由pyccl进行维护。BUG修复、功能增加等。

* 采购订单管理
* 基本票务支持
* 每个项目/软件的配置/知识/常见问题条目。(比附加文件更容易)
* RRD支持历史图表趋势
* 平面图中的项目定位，带拖放功能(WIP)
* 更好的(分析性)许可模型、SLA事件、重复事件、描述
* 自动主机和软件发现-数据库审计
* 类似ISO20000的功能

特性
--------

* **硬件**: 规格、保修、序列号、IP信息、与该硬件相关的其他硬件、项目状态、事件日志、受托人
* **软件**: 规格，许可证信息，...
* **关联**: 每个软件的安装位置、许可证数量、组件关系、与软件/硬件/发票的合同关系
* **发票**: 描述日期、供应商、价格、附加文件的购买证明
* **代理商**: 供应商、软硬件制造商、买方(针对不同的Dpt)、承包商
* **位置**: 每项资产的位置:建筑、楼层、房间、机柜、机架、深度
* **合同**: 定义自定义合同类型，如支持和维护、SLA等。跟踪合同事件。
* **标签**: 硬件和软件的多个标签。您可以根据用途、预算、所有者、重要性等使用标签进行分组。
* **文件**: 将文档附加到每个主要对象实体（项目、软件、发票、合同等）
* **用户**: 谁拥有什么或谁对什么负责（现已经只有登录功能）
* **机柜**: 显示机柜布局，包括分配给每个U的硬件。(支持多个硬件/机柜)。
* **打印标签:** 打印标签贴纸，用于标记您的所有资产，无论有无二维码，从电话、笔记本电脑、冷却装置、ups。通过GUI轻松定义新的标签纸布局（新增支持自定义纸张大小）。
* **一键式备份**: 从主菜单中获得ITDB安装和数据的完整备份。要恢复，只需提取网络服务器上的备份文件！
* **所有页面都可以打印**: 所有屏幕页面/列表/报告打印出来很好，没有菜单，滚动条和其他杂物。
* **界面翻译**: 翻译文件支持。您可以创建自己的翻译(1.3版)
* **基本的LDAP支持**: 从LDAP URL提取项目分配的用户列表。(未经active directory测试，也不用于身份验证。

下载
--------

当前官方版本:  
6/Mar/2016 version 1.23: [itdb-1.23.zip](https://github.com/sivann/itdb/archive/1.23.zip)  
4/Jul/2015 version 1.22: [itdb-1.22.zip](https://github.com/sivann/itdb/archive/1.22.zip)  
2/Jul/2015 version 1.21: [itdb-1.21.zip](https://github.com/sivann/itdb/archive/1.21.zip)  
25/Oct/2014 version 1.14: [itdb-1.14.zip](https://github.com/sivann/itdb/archive/1.14.zip)  
~20/Oct/2014 version 1.13: This version had some unreleased code by mistake.  
~23/Dec/2013 version 1.12: [itdb-1.12.tar.gz](itdb-1.12.tar.gz)  
24/Oct/2013 version 1.11: [itdb-1.11.tar.gz](itdb-1.11.tar.gz)  
~22/Oct/2013 version 1.10: (wrong db version bundled)  
~28/Sep/2013 version 1.9: [itdb-1.9.tar.gz](itdb-1.9.tar.gz)  
  
您可以在[GitHub](https://github.com/sivann/itdb)上下载当前的开发版本  
  
以前的版本在[这里](releases_old/?C=M;O=D).  

演示
----

[DEMO](demo/itdb-1.23/)是**只读**的，功能有限。演示可能有点慢，这是由于我的供应商，而不是由于itdb。

许可证
-------

该软件是在GPL下发布的。我会很高兴收到一封描述你如何使用它的电子邮件！  

链接
-----

* [GitHub](https://github.com/sivann/itdb)
* [Freshmeat/Freecode](http://freecode.com/projects/itdb)
* [ohloh](https://www.ohloh.net/p/itdb)

截图
-----------

部分截图来自之前的版本。  
一些截图已经被编辑删除私人信息。  

[![](images/itdb-home.png)  <br>首页](images/itdb-home.png)

[![](images/itdb-listitems.png)  <br>硬件搜索](images/itdb-listitems.png)

[![](images/itdb-items-edit.png)  <br>硬件编辑](images/itdb-items-edit.png)

[![](images/itdb-item-invoices.png)  <br>相关项目发票](images/itdb-item-invoices.png)

[![](images/itdb-editcontract.png)  <br>编辑合同](images/itdb-editcontract.png)

[![](images/itdb-contractevents.png)  <br>合同事件](images/itdb-contractevents.png)

[![](images/itdb-itemtypes.png)  <br>硬件类型](images/itdb-itemtypes.png)

[![](images/itdb-editagent.png)  <br>编辑代理商](images/itdb-editagent.png)

[![](images/itdb-labelprint.png)  <br>标签打印](images/itdb-labelprint.png)

[![](images/itdb-editrack.png)  <br>机柜编辑和侧视图](images/itdb-editrack.png)

[![](images/itdb-reportspie.png)  <br>报告](images/itdb-reportspie.png)

[![](images/itdb-software-list.png)  <br>软件列表](images/itdb-software-list.png)

[![](images/itdb-software-edit.png)  <br>编辑软件](images/itdb-software-edit.png)

[![](images/itdb-editlocation.png)  <br>编辑位置](images/itdb-editlocation.png)

[![](images/itdb-browse.png)  <br>树形浏览器](images/itdb-browse.png "Browse")

[![](images/itdb-addcontract-trans.png)  <br>翻译样本](images/itdb-addcontract-trans.png "希腊语翻译")

安装
============

系统需求
-------------------

* 火狐、Chrome、EDGE等或IE≥9的最新版本
* posix系统(linux、solaris等)上的Apache 2.2(Apache 2.0也可以工作)
* PHP版本 > 5.2.x，并且PHP版本 < 7.0。
* PHP SQlite PDO, SQlite >3.6.14.1
* 根据您的发行版，您可能还需要安装软件包"php-posix", "php-mbstring", "php5-gd", "php5-json" "php5-sqlite" "php-pdo"

据我所知，它也可以在Windows系统下运行，但我无法测试它。

安装说明
-------------------------

1.  将文件提取到web导出的目录中(在“DocumentRoot”下)
2.  将pure.db重命名为itdb.db (pure.db是一个空白数据库)
（**注意**：更新我的非官方版本后，需要执行/a.php进行数据库结构的更新）
3.  使data/itdb.db文件**和**data/ 目录**和**data/files/ 可由web服务器读写
4.  使 translations/ 目录对web服务器可读和可写
5.  使用**admin/admin**登录

如果您需要找到apache/php安装所使用的sqlite库，请浏览到itdb/phpinfo.php或按下itdb菜单左下方的蓝色小图标(I)。

Upgrade
-------

Instructions are inside the 00-UPGRADE.txt file

Release Notes
-------------

older CHANGELOG is [here](https://github.com/sivann/itdb/commits/master)  
For newer releases, you may see the [commit log](https://github.com/sivann/itdb/commits/master)  

Copyright © 2008-2016 Spiros Ioannou - printmail('gmail.com','sivann');
# Homepage 
http://www.sivann.gr/software/itdb/

# Contributing
Please consider that my free time is now extremely limited, and so even valid pull requests may not be addressed for a long time.

# Status
As I no longer have enough time to improve ITDB, I can only provide bug fixes for newer PHP or browser versions. Please do not ask for new features.
 
# Security
Do *NOT* expose ITDB to the public internet. It is not secure, it is aimed for intranets. If you need to do so, please configure an HTTP auth password on your web server so it will be hidden behind a password.
 
## Scope of pull requests
Thank you for your time to consider contributing. Please take into account ITDB is only an inventory software. It may offer some basic reporting by quering 
its own data because it may have access to invoices, users and equipment.
ITDB tries to adhere to the [do one thing](https://en.wikipedia.org/wiki/Unix_philosophy#Do_One_Thing_and_Do_It_Well) philisophy.
ITDB does not and should not aim to provide the functionality of other software e.g. network monitoring tools, finance software or network diagnostics software. 

## Extent of pull request 
Pull requests should fix 1 and only 1 thing. Otherwise it is extremely difficult to test and review.

### Bug fixes
Please take the time to consider the following when submitting a bug:
* how does your fix handle non-us characters? (E.g. Chinese, Greek, etc)
* how does your fix handle non-us locales ? (especially date manipulation fixes)
* does your fix use strtotime ? (don't use it)
* how does your fix handle older SQLite versions? 
* how does your fix handle older/newer PHP versions? 
* how does your fix work with Firefox/Chrome/IE ?
* how does your fix scale with lots of items?


### New UI fields pull requests:
Please take the time to consider the following when submitting a generic pull-request :
* Is your new  field universally useful? Can you think of cases where it doesn't make sense?
* Can your functionality be already addressed by the current fields?
* Does  your field have specific search needs?

if the answer is no to at least one of the above then probably you don't need that field. ITDB has a lot of fields on the "no" category, let's not add any more.

## Welcomed pull requests
Any pull requests fixing the following would be welcome. Please open a discussion before starting to code.

### Major contributions
* rewrite the DB requests using PDO (and prepared statements)
* rewrite the item associations tables using datatables with server-side AJAX
* update datatables to the most recent version
* rewrite the front controller and auth using a framework (e.g. slim)
* very simple ticketing
 
### Minor contributions
#### UI
* item user selection and possibly others: instead of pull-down select, use jqueryui's autocomplete combobox
* inplace edit/add itemtypes, agents, users. Configurable to allow edit/add for specific user and select for others.
* design PC/server layout in Locations. Assign Items to x/y over imagemap
* edit previous/next item functionality. E.g. from an item list of a search result. 
* replace file uploader with a recent one also supporting drag&drop 
* unify tab association code

### Schema
* add history (renewals) & events in software, like in items.
* list of services and relations to items
* virtual/non virtual item (e.g. VM). Parent (physical) item. Virtual may show as tooltip of rack position of parent. Also
* add knowledge area, with connections to items & software (text)
* software classes (types). E.g. O/S
* add a cron notification sample script in contrib/ for contract/warranty expiration
* license models: on inventory data:per installation, OEM and machine licensing. On external data sources: qualified desktop, CPU, user, named user, server, client access license (CAL), site, enterprise and user-defined models. TBD.
* port connectivity management (TBD if needed)
* power cable management (TBD if needed)


Thank you!

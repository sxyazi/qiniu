本插件目前的功能: 
1. 上传/删除 图片
2. 上传/删除 附件
3. 上传/更新 分类信息图片
4. 删除主题时, 同时删除上传文件
5. 原图保护, 支持水印
6. 缩略图生成
7. 下载 图片/附件
8. 远程图片下载

# 文件
存入数据库的文件名是七牛的sha1文件校验算法所生成的名字. 可以有效避免重复文件.
(七牛那边的1个文件, 可以在论坛这边被N个帖子所使用)
也方便以后开发秒传等功能. 使用原生JS, 不依赖flash上传, 同时旧版本的IE浏览器也不支持.

# 数据库
为了不改变discuz数据库结构, 使用XML做小型数据库. 所以需要你的服务器支持并启用了XML相关组件.

# 部署
使用本插件后会完全代替discuz的上传服务, 所以你的网站有很多原先使用discuz的上传的附件, 不要使用!!!

使用本插件存入数据库的附件地址是这种形式的: data/attachment/forum/FkRsIOKizjRdSb9lqUs9ri7AbDjv.png
所以需要使用重写来重定向到七牛的URL.

Apache: 
在 data/attachment/forum/ 目录下创建 .htaccess 文件, 内容如下: 
```
RewriteEngine On
RewriteBase /
RewriteRule ^(.*)$ http://七牛URL/$1
```

Nginx: 
```
rewrite data/attachment/forum/^(.*)$ http://七牛URL/$1 break;
```

IIS: 
```
<rule name="301Redirect" stopProcessing="true">
    <match url="data/attachment/forum/(.*)" />
    <action type="Redirect" url="http://七牛URL/{R:1}" redirectType="Permanent" />
</rule>
```

# 待开发
点评/评分 后图片地址替换
远程图片下载 文件名使用原文件名

```
select * from (select id,name,'table0' as t from table_0 union all select id,name,'table1' as t from table_1 union all select id,name,'table2' as t from table_2 ......) as t where t.name = ?
```
<p align="center"><img width="260px" src="https://chaz6chez.cn/images/workbunny-logo.png" alt="workbunny"></p>

**<p align="center">workbunny/webman-shared-cache</p>**

**<p align="center">🐇 A lightweight shared cache for webman plugin. 🐇</p>**

# A lightweight shared cache for webman plugin


<div align="center">
    <!--<a href="https://github.com/workbunny/webman-shared-cache/actions">
        <img src="https://github.com/workbunny/webman-shared-cache/actions/workflows/CI.yml/badge.svg" alt="Build Status">
    </a>-->
    <a href="https://github.com/workbunny/webman-shared-cache/releases">
        <img alt="Latest Stable Version" src="https://badgen.net/packagist/v/workbunny/webman-shared-cache/latest">
    </a>
    <a href="https://github.com/workbunny/webman-shared-cache/blob/main/composer.json">
        <img alt="PHP Version Require" src="http://poser.pugx.org/workbunny/webman-shared-cache/require/php">
    </a>
    <a href="https://github.com/workbunny/webman-shared-cache/blob/main/LICENSE">
        <img alt="GitHub license" src="http://poser.pugx.org/workbunny/webman-shared-cache/license">
    </a>

</div>

## 常见问题

### 1. 它与 Redis/Memcache 的区别

- shared-cache是基于APCu的本地缓存，它的底层是带有锁的MMAP共享内存；
- Redis和Memcache本质上是“分布式”缓存系统/K-V数据库，存在网络IO；
- shared-cache没有持久化，同时也无法实现“分布式”，仅可用于本地的多进程环境（进程需要有亲缘关系）；
- shared-cache是ns级别的缓存，redis是ms级别的缓存；
- 网络IO存在内核态和用户态的多次拷贝，存在较大的延迟，共享内存不存在这样的问题；

### 2. 它的使用场景

- 可以用作一些服务器的本地缓存，如页面缓存、L2-cache；
- 可以跨进程做一些计算工作，也可以跨进程通讯；
- 用在一些延迟敏感的服务下，如游戏服务器；

## 简介

- 基于APCu拓展的轻量级缓存；
- 支持具备亲缘关系的多进程内存共享；
- ns级缓存

## 安装

1. **自行安装APCu拓展**
	```shell
	# 1. pecl安装
	pecl instanll apcu
	# 2. 安装器安装【推荐】
	curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - | sh -s apcu
	```
2. 安装composer包
    ```shell
    composer require workbunny/webman-shared-cache
    ```
3. 使用命令进行php.ini的配置
    - 进入 **/config/plugin/workbunny/webman-shared-cache** 目录
    - 运行
	```shell
    # 帮助信息
    ./shard-cache-enable --help
    # 脚本默认使用sh运行
    ```

## 使用

- 支持类似Redis的Set/Get/Del/Keys HSet/HGet/HDel/HKeys等功能
- 支持通配符/正则匹配Search
- 支持单位为秒的过期时间
- 支持储存对象数据
- 支持查看cache信息
- 其他功能具体可以参看代码注释

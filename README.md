<p align="center"><img width="260px" src="https://chaz6chez.cn/images/workbunny-logo.png" alt="workbunny"></p>

**<p align="center">workbunny/webman-shared-cache</p>**

**<p align="center">🐇 A lightweight shared cache for webman plugin. 🐇</p>**

# A lightweight shared cache for webman plugin


<div align="center">
    <a href="https://github.com/workbunny/webman-shared-cache/actions">
        <img src="https://github.com/workbunny/webman-shared-cache/actions/workflows/CI.yml/badge.svg" alt="Build Status">
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

- 基于APCu的shared-cache本质上是本地缓存，它是基于共享内存和MMAP的；
- Redis和Memcache本质上是“分布式”缓存系统/K-V数据库；
- Redis/Memcache在底层都直接或间接地使用到epoll的网络处理，这里拿Redis举例，Redis会将短时间内接收到的处理放在同一个事件循环内执行，
  所以在一个非常高频次测试场景下性能会很好，因为多次执行会整合在一个事件内触发；但实际场景可能存在或多或少的间隔，比如10ms或者1ms不定，
  在这样的场景下，Redis会因为多次执行分散到多次循环事件执行而性能下降，在一些延迟敏感的场景下会没办法提高性能，而共享内存即便存在miss-hit也不存在这样的问题；
- shared-cache无须任何连接，不通过网络层，而Redis/Memcache需要走网络层，所以在读写上避免了不必要的用户态和内核态之间的拷贝，
  这样在一定程度上也提高了不少的性能，一定程度上避免了系统拷贝带来的延迟；
- shared-cache没有持久化，同时也无法实现“分布式”，仅可用于本地的多进程环境（进程需要有亲缘关系）；

### 2. 它的使用场景

- 可以用作一些服务器的本地缓存，如页面缓存、L2-cache；
- 可以跨进程做一些计算工作，也可以跨进程通讯；
- 用在一些延迟敏感的服务下，如游戏服务器；

## 简介

- 基于APCu拓展的轻量级缓存；
- 支持具备亲缘关系的多进程；

## 安装

1. 自行安装APCu拓展
2. 安装composer包
    ```shell
    composer require workbunny/webman-shared-cache
    ```
3. 使用以下命令进行php.ini的配置
    - 进入 /config/workbunny/webman-shared-cache目录
    - ```shell
      # 帮助信息
      ./shard-cache-enable --help
      # 脚本默认使用sh运行
      ```
      
## 使用

类似Redis的Set/Get Hash，具体可以参看代码注释
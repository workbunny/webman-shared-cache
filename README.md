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

- shared-cache是基于APCu的本地缓存，它的底层是带有锁的MMAP共享内存；
- Redis和Memcache本质上是“分布式”缓存系统/K-V数据库，存在网络IO；
- shared-cache没有持久化，同时也无法实现“分布式”，仅可用于本地的多进程环境（进程需要有亲缘关系）；
- shared-cache是ns级别的缓存，redis是ms级别的缓存；
- 网络IO存在内核态和用户态的多次拷贝，存在较大的延迟，共享内存不存在这样的问题；

### 2. 它的使用场景

- 可以用作一些服务器的本地缓存，如页面缓存、L2-cache；
- 可以跨进程做一些计算工作，也可以跨进程通讯；
- 用在一些延迟敏感的服务下，如游戏服务器；
- 简单的限流插件；

## 简介

- 基于APCu拓展的轻量级缓存；
- 支持具备亲缘关系的多进程内存共享；
- ns级缓存

## 安装

1. **自行安装APCu拓展**
	```shell
	# 1. pecl安装
	pecl instanll apcu
	# 2. docker中请使用安装器安装
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
    sh ./shared-cache-enable.sh --help
    # or
    bash ./shared-cache-enable.sh --help
    ```

## 使用

#### 注：\Workbunny\WebmanSharedCache\Cache::$fuse为全局阻塞保险

### 1. Cache基础使用

- **类似Redis的String【使用方法与Redis基本一致】**
  - 支持 Set/Get/Del/Keys/Exists
  - 支持 Incr/Decr，支持浮点运算
  - 支持 储存对象数据
  - 支持 XX/NX模式，支持秒级过期时间

- **类似Redis的Hash【使用方法与Redis基本一致】**
  - 支持 HSet/HGet/HDel/HKeys/HExists 
  - 支持 HIncr/HDecr，支持浮点运算
  - 支持 储存对象数据
  
- **通配符/正则匹配Search**
  ```php
  $result = [];
  # 默认正则匹配 - 以50条为一次分片查询
  \Workbunny\WebmanSharedCache\Cache::Search('/^abc.+$/', function ($key, $value) use (&$result) { 
      $result[$key] = $value;
  }, 50);
  
  # 通配符转正则
  \Workbunny\WebmanSharedCache\Cache::Search(
      \Workbunny\WebmanSharedCache\Cache::WildcardToRegex('abc*'),
      function ($key, $value) use (&$result) {
          $result[$key] = $value;
      }
  );
  ```

- **原子性执行**
  ```php
  # key-1、key-2、key-3会被当作一次原子性操作
  
  # 非阻塞执行 - 成功执行则返回true，失败返回false，锁冲突会导致执行失败
  $result = \Workbunny\WebmanSharedCache\Cache::Atomic('lock-test', function () { 
      \Workbunny\WebmanSharedCache\Cache::Set('key-1', 1);
      \Workbunny\WebmanSharedCache\Cache::Set('key-2', 2);
      \Workbunny\WebmanSharedCache\Cache::Set('key-3', 3);
  });
  # 阻塞等待执行 - 默认阻塞受Cache::$fuse阻塞保险影响
  $result = \Workbunny\WebmanSharedCache\Cache::Atomic('lock-test', function () { 
      \Workbunny\WebmanSharedCache\Cache::Set('key-1', 1);
      \Workbunny\WebmanSharedCache\Cache::Set('key-2', 2);
      \Workbunny\WebmanSharedCache\Cache::Set('key-3', 3);
  }, true);
  # 自行实现阻塞
  $result = false
  while (!$result) {
      # TODO 可以适当增加保险，以免超长阻塞
      $result = \Workbunny\WebmanSharedCache\Cache::Atomic('lock-test', function () { 
          \Workbunny\WebmanSharedCache\Cache::Set('key-1', 1);
          \Workbunny\WebmanSharedCache\Cache::Set('key-2', 2);
          \Workbunny\WebmanSharedCache\Cache::Set('key-3', 3);
      });
  }
  ```

- **查看cache信息**
  ```php
  # 全量数据
  \Workbunny\WebmanSharedCache\Cache::Info();
  
  # 不查询数据
  \Workbunny\WebmanSharedCache\Cache::Info(true);
  ```
  
- **查看锁信息**
  ```php
  # Hash数据的处理建立在写锁之上，如需调试，则使用该方法查询锁信息
  \Workbunny\WebmanSharedCache\Cache::LockInfo();
  ```

- **查看键信息**
  ```php
  # 包括键的一些基础信息
  \Workbunny\WebmanSharedCache\Cache::KeyInfo('test-key');
  ```
  
- **清空cache**
  - 使用Del多参数进行清理
  ```php
  # 接受多个参数
  \Workbunny\WebmanSharedCache\Cache::Del($a, $b, $c, $d);
  # 接受一个key的数组
  \Workbunny\WebmanSharedCache\Cache::Del(...$keysArray);
  ```
  - 使用Clear进行清理
  ```php
  \Workbunny\WebmanSharedCache\Cache::Clear();
  ```
  
### 2. RateLimiter插件

> 高效轻量的亲缘进程限流器

1. 在/config/plugin/workbbunny/webman-shared-cache/rate-limit.php中配置
2. 在使用的位置调用
  - 当没有执行限流时，返回空数组
  - 当执行但没有到达限流时，返回数组is_limit为false
  - 当执行且到达限流时，返回数组is_limit为true
  ```php
  $rate = \Workbunny\WebmanSharedCache\RateLimiter::traffic('test');
  if ($rate['is_limit'] ?? false) {
      // 限流逻辑 如可以抛出异常、返回错误信息等
      return new \support\Response(429, [
          'X-Rate-Reset' => $rate['reset'],
          'X-Rate-Limit' => $rate['limit'],
          'X-Rate-Remaining' => $rate['reset']
      ])
  }
  ```
  
### 其他功能具体可以参看代码注释和测试用例

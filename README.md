<p align="center"><img width="260px" src="https://chaz6chez.cn/images/workbunny-logo.png" alt="workbunny"></p>

**<p align="center">workbunny/webman-shared-cache</p>**

**<p align="center">🐇 A lightweight shared cache for webman plugin. 🐇</p>**

# A lightweight shared cache for webman plugin


<div align="center">
    <a href="https://github.com/workbunny/webman-shared-cache/actions">
        <img src="https://github.com/workbunny/webman-shared-cache/actions/workflows/CI.yml/badge.svg" alt="Build Status">
    </a>
    <a href="https://github.com/workbunny/webman-shared-cache/releases">
        <img alt="Latest Stable Version" src="https://badgen.net/packagist/v/workbunny/webman-shared-cache/latest">
    </a>
    <a href="https://github.com/workbunny/webman-shared-cache/blob/main/composer.json">
        <img alt="PHP Version Require" src="https://badgen.net/packagist/php/workbunny/webman-shared-cache">
    </a>
    <a href="https://github.com/workbunny/webman-shared-cache/blob/main/LICENSE">
        <img alt="GitHub license" src="https://badgen.net/packagist/license/workbunny/webman-shared-cache">
    </a>

</div>

## 常见问题

### 1. 它与 Redis/Memcache 的区别

- shared-cache是基于APCu的本地缓存，它的底层是带有锁的MMAP共享内存；
- Redis和Memcache本质上是“分布式”缓存系统/K-V数据库，存在网络IO；
- shared-cache没有持久化，同时也无法实现“分布式”，仅可用于本地的多进程环境（进程需要有亲缘关系）；
- shared-cache是μs级别的缓存，redis是ms级别的缓存；
- 网络IO存在内核态和用户态的多次拷贝，存在较大的延迟，共享内存不存在这样的问题；

### 2. 它的使用场景

- 可以用作一些服务器的本地缓存，如页面缓存、L2-cache；
- 可以跨进程做一些计算工作，也可以跨进程通讯；
- 用在一些延迟敏感的服务下，如游戏服务器；
- 简单的限流插件；

### 3. 与redis简单的比较
- 运行/tests/simple-benchmark.php
  - redis使用host.docker.internal
  - 在循环中增加不同的间隔，模拟真实使用场景
  - 结果如下：
    ```shell
    1^ "count: 100000"
    2^ "interval: 0 μs"
    ^ "redis: 73.606367111206"
    ^ "cache: 0.081215143203735"
    ^ "-----------------------------------"
    1^ "count: 100000"
    2^ "interval: 1 μs"
    ^ "redis: 78.833391904831"
    ^ "cache: 6.4423549175262"
    ^ "-----------------------------------"
	1^ "count: 100000"
	2^ "interval: 10 μs"
	^ "redis: 79.543494939804"
	^ "cache: 7.2690420150757"
	^ "-----------------------------------"
	1^ "count: 100000"
	2^ "interval: 100 μs"
	^ "redis: 88.58958697319"
	^ "cache: 17.31387090683"
	^ "-----------------------------------"
	1^ "count: 100000"
	2^ "interval: 1000 μs"
	^ "redis: 183.2620780468"
	^ "cache: 112.18278503418"
	^ "-----------------------------------"
    ```

## 简介

- 基于APCu拓展的轻量级高速缓存，读写微秒级；
- 支持具备亲缘关系的多进程内存共享；
- 支持具备亲缘关系的多进程限流；

## 安装

**`PHP7.4`不完全兼容，请勿使用`Cache::Incr` `Cache::Decr`**

1. **自行安装APCu拓展**
	```shell
	# 1. pie安装
    pie install apcu/apcu
	# 2. docker中请使用安装器安装
	curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - | sh -s apcu
    # 3. pecl安装 【不推荐】
    pecl instanll apcu
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
  - 支持 HashKey的秒级过期时间【版本 ≥ 0.5】
  
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
  **Tips：Cache::Search()本质上是个扫表匹配的过程，是O(N)的操作，如果需要对特定族群的数据进行监听，推荐使用Channel相关函数实现监听。**


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
  var_dump(\Workbunny\WebmanSharedCache\Cache::Info());
  
  # 不查询数据
  var_dump(\Workbunny\WebmanSharedCache\Cache::Info(true));
  ```
  
- **查看锁信息**
  ```php
  # Hash数据的处理建立在写锁之上，如需调试，则使用该方法查询锁信息
  var_dump(\Workbunny\WebmanSharedCache\Cache::LockInfo());
  ```

- **查看键信息**
  ```php
  # 包括键的一些基础信息
  var_dump(\Workbunny\WebmanSharedCache\Cache::KeyInfo('test-key'));
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

### 3. Cache的Channel功能

- Channel是一个类似Redis-stream、Redis-list、Redis-Pub/Sub的功能模块
- 一个通道可以被多个进程监听，每个进程只能监听一个相同通道（也就是对相同通道只能创建一个监听器）

- **向通道发布消息**
  - 临时消息
  ```php
  # 向一个名为test的通道发送临时消息；
  # 通道没有监听器时，临时消息会被忽略，只有通道存在监听器时，该消息才会被存入通道
  Cache::ChPublish('test', '这是一个测试消息'， false);
  ```
  - 暂存消息
  ```php
  # 向一个名为test的通道发送暂存消息；
  # 通道存在监听器时，该消息会被存入通道内的所有子通道
  Cache::ChPublish('test', '这是一个测试消息'， true);
  ``` 
  - 指定workerId
  ```php
  # 指定发送消息至当前通道内workerId为1的子通道
  Cache::ChPublish('test', '这是一个测试消息'， true, 1);
  ``` 

- **创建通道监听器**
  - 一个进程对相同通道仅能创建一个监听器
  - 一个进程可以同时监听多个不同的通道
  - 建议workerId使用workerman的workerId进行区分
  ```php
  # 向一个名为test的通道创建一个workerId为1的监听器；
  # 通道消息先进先出，当有消息时会触发回调
  Cache::ChCreateListener('test', '1', function(string $channelKey, string|int $workerId, mixed $message) {
      // TODO 你的业务逻辑
      dump($channelKey, $workerId, $message);
  });
  ```

- **移除通道监听器**
  - 移除监听器子通道及子通道内消息
  ```php
  # 向一个名为test的通道创建一个workerId为1的监听器；
  # 通道移除时不会移除其他子通道消息
  Cache::ChRemoveListener('test', '1', true);
  ```
  - 移除监听器子通道，但保留子通道内消息
  ```php
  # 向一个名为test的通道创建一个workerId为1的监听器；
  # 通道移除时不会移除所有子通道消息
  Cache::ChRemoveListener('test', '1', false);
  ```
  
- **实验性功能：信号通知**
  - 由于共享内存无法使用事件监听，所以底层使用Timer定时器进行轮询，实验性功能可以开启使用系统信号来监听数据的变化
  ```php
  // 设置信号
  // 因为event等事件循环库是对标准信号的监听，所以不能使用自定实时信号SIGRTMIN ~ SIGRTMAX
  // 默认暂时使用SIGPOLL，异步IO监听信号，可能影响异步文件IO相关的触发
  Future::$signal = \SIGPOLL;
  // 开启信号监听，这时候开启的监听会触发之前的回调和通道回调，不会影响之前的回调
  Cache::channelUseSignalEnable(true)
  ```
  - 当使用的监听信号存在已注册的回调产生回调冲突时，可以手动设置回调事件共享
  ```php
  // 设置信号
  // 因为event等事件循环库是对标准信号的监听，所以不能使用自定实时信号SIGRTMIN ~ SIGRTMAX
  // 默认暂时使用SIGPOLL
  Future::$signal = \SIGPOLL;
  // 假设\SIGPOLL存在一个已注册的回调，YourEventLoop::getCallback(\SIGPOLL)可以获取该事件在当前进程注册的回调响应
  // 设置回调
  Future::setSignalCallback(YourEventLoop::getCallback(\SIGPOLL));
  // 开启信号监听，这时候开启的监听会触发之前的回调和通道回调，不会影响之前的回调
  Cache::channelUseSignalEnable(true)
  ```
  > 通道信号监听维系了一个事件队列，多次触发信号时，回调只会根据事件队列是否存在事件消费标记而执行事件回调
### 其他功能具体可以参看代码注释和测试用例

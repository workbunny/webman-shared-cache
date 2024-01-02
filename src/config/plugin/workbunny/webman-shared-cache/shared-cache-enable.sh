#!/bin/bash

target=""
shm_size="1024M"
shm_segments=1
mmap_file_mask=""
gc_ttl=3600
file_name="apcu-cache.ini"

options=$(getopt -o hf:t:si:se:m:gc: --long help,file:,target:,size:,segments:,mmap:,gc_ttl: -- "$@")
eval set -- "$options"

while true; do
  case $1 in
  -h | --help)
    echo "使用: cache-enable.sh [选项]..."
    echo "选项:"
    echo " -h,  --help      显示帮助信息"
    echo " -f,  --file      [名称] 指定配置名称"
    echo " -t,  --target    [路径] 指定目标位置"
    echo " -m,  --mmap-file [取值] 配置 apcu.mmap_file_mask 【例 /tmp/apc.XXXXXX】"
    echo " -si, --size      [取值] 配置 apcu.shm_size       【例 1024M】"
    echo " -se, --segments  [取值] 配置 apcu.shm_segments"
    echo " -gc, --gc_ttl    [取值] 配置 apcu.gc_ttl"

    exit 0
    ;;
  -t | --target)
    target="$2"
    shift 2
    ;;
  -f | --file)
    file_name="$2"
    shift 2
    ;;
  -si | --size)
    shm_size="$2"
    shift 2
    ;;
  -se | --segments)
    shm_segments="$2"
    shift 2
    ;;
  -m | --mmap)
      mmap_file_mask="$2"
      shift 2
      ;;
  -gc | --gc_ttl)
        gc_ttl="$2"
        shift 2
        ;;
  --)
    shift
    break
    ;;
  *)
    echo "无效选项: $1" >&2
    exit 1
    ;;
  esac
done

if [ -z "$target" ]; then
  target="/usr/local/etc/php/conf.d"
  echo "配置将被创建至 $target，是否继续？(y/N)"
  read answer
  answer=$(echo $answer | tr [a-z] [A-Z])
  if [ "$answer" != "Y" ]; then
    echo "已放弃操作. "
    exit 0
  fi
fi

cat >$file_name <<EOF

apc.enabled=1
apc.enable_cli=1
apc.shm_segments=$shm_segments
apc.shm_size=$shm_size
apc.mmap_file_mask=$mmap_file_mask
apc.gc_ttl=$gc_ttl

EOF

if [ -e "$target/$file_name" ]; then
  echo "目标位置已经存在配置，是否覆盖？(y/N)"
  read answer
  answer=$(echo $answer | tr [a-z] [A-Z])
  if [ "$answer" != "Y" ]; then
    echo "已放弃覆盖. "
    exit 0
  fi
fi

mv $file_name $target

echo "配置文件已创建: $target/$file_name"

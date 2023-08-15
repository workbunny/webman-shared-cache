#!/bin/sh

target=""
shm_size="1024M"
shm_segments=1
file_name="apcu-cache.ini"

options=$(getopt -o hf:t:si:se: --long help,file:,target:,size:,segments: -- "$@")
eval set -- "$options"

while true; do
  case $1 in
  -h | --help)
    echo "使用: cache-enable.sh [选项]..."
    echo "选项:"
    echo " -h,  --help     显示帮助信息"
    echo " -f,  --file     [名称] 指定配置名称"
    echo " -t,  --target   [路径] 指定目标位置"
    echo " -si, --size     [取值] 配置 apcu.shm_size"
    echo " -se, --segments [取值] 配置 apcu.shm_segments"
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
  if [ "$answer" != "y" ]; then
    echo "已放弃操作. "
    exit 0
  fi
fi

cat >$file_name <<EOF

apc.enabled=1
apc.enable_cli=1
apc.shm_segments=$shm_segments
apc.shm_size=$shm_size

EOF

if [ -e "$target/$file_name" ]; then
  echo "目标位置已经存在配置，是否覆盖？(y/N)"
  read answer
  if [ "$answer" != "y" ]; then
    echo "已放弃覆盖. "
    exit 0
  fi
fi

mv $file_name $target

echo "配置文件已创建: $target/$file_name"

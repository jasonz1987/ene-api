
### 安装docker

```shell script
dnf install epel-release
yum -y install git
yum install -y yum-utils
yum-config-manager \
    --add-repo \
    https://download.docker.com/linux/centos/docker-ce.repo
yum -y install docker-ce docker-ce-cli containerd.io
systemctl enable docker
systemctl start docker
```

### 安装镜像

```shell script
docker run --name soke \
-v /www/soke-api:/www/soke-api \
-p 9504:9504 -p 9502:9502 -it \
--privileged -u root \
--entrypoint /bin/sh \
hyperf/hyperf:7.4-alpine-v3.11-swoole
```

### 安装composer 
wget https://github.com/composer/composer/releases/download/2.0.8/composer.phar
chmod u+x composer.phar
mv composer.phar /usr/local/bin/composer
composer install

### 安装组件

apk add php7-gmp

### 安装supervisor

apk add supervisor


# 新建一个应用并设置一个名称，这里设置为 hyperf
[program:martingale]
# 设置命令在指定的目录内执行
directory=/www/martingale
# 这里为您要管理的项目的启动命令
command=php ./bin/hyperf.php start
# 以哪个用户来运行该进程
user=root
# supervisor 启动时自动该应用
autostart=true
# 进程退出后自动重启进程
autorestart=true
# 进程持续运行多久才认为是启动成功
startsecs=1
# 重试次数
startretries=3
# stderr 日志输出位置
stderr_logfile=/www/martingale/runtime/stderr.log
# stdout 日志输出位置
stdout_logfile=/www/martingale/runtime/stdout.log

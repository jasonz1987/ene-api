
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

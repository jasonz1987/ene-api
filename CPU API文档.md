# CPU API文档

| 版本 | 撰写人  | 撰写时间   | 备注     |
| ---- | ------- | ---------- | -------- |
| 1.0  | Jason.z | 2021.07.01 | 初始版本 |
|      |         |            |          |



## 认证

用户登录或注册后，获取登录token，用户在请求后台`api`接口的时候需要在请求头部携带相应的`token`

**携带方式**：Authorization Brear Token

| KEY           | VALUE  |
| ------------- | ------ |
| Authorization | Bearer |

**Token时效**

默认为 1天



## 环境

| 环境     | public api |
| -------- | ---------- |
| 本地环境 |            |
| 测试环境 |            |
| 生产环境 |            |

## 返回

成功

```json
{
	"code": 200,
	"message": "success",
	"data": []
}
```

失败

```json
{
	"code": 500,
	"message": "error",
}
```

分页

```json
{
  "code":200,
  "message":"",
  "data":[],
  "page": {
    "total": 1,
    "count": 1,
    "per_page": 10,
    "current_page": 1,
    "total_pages": 1
  }
}

```



## 返回码

| 返回码 | 返回说明       |
| ------ | -------------- |
| 200    | 操作成功       |
| 500    | 操作失败       |
| 401    | 认证失败       |
| 429    | 请求过多       |
| 400    | 参数校验错误   |
| 405    | 请求方法不允许 |
|        |                |



##  API接口

### 认证模块



#### 查询地址

**请求方式：**

GET

**请求地址：**

```
http://{{host}}/api/v1/auth/info
```

**是否认证**：

否

**请求参数：**

| 参数名  | 参数类型 | 是否必须 | 参数说明 |
| ------- | -------- | -------- | -------- |
| address | String   | 是       | 钱包地址 |

**返回结果：**

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "is_bind": true,
    }
}
```

**返回参数：**

| 参数名  | 参数类型 | 参数说明 |
| ------- | -------- | -------- |
| Is_bind | boolean  | 是否绑定 |



#### 获取Nonce

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/auth/nonce
```

**是否认证**：

否

**请求参数：**

| 参数名  | 参数类型 | 是否必须 | 参数说明 |
| ------- | -------- | -------- | -------- |
| address | String   | 是       | 钱包地址 |

**返回结果：**

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "nonce": "43191d947e7a58db5e7495f1a9c7f95ed0f02bdd",
        "expired_at": 1617255023
    }
}
```

**返回参数：**

| 参数名     | 参数类型 | 参数说明       |
| ---------- | -------- | -------------- |
| nonce      | String   | 登陆随机字符串 |
| expired_at | Integer  | 过期时间       |

#### 获取Token

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/auth/token
```

**是否认证**：

否

**请求参数：**

| 参数名    | 参数类型 | 是否必须 | 参数说明     |
| --------- | -------- | -------- | ------------ |
| address   | String   | 是       | 钱包地址     |
| source    | String   | 是       | 邀请钱包地址 |
| signature | String   | 是       | 签名         |

**返回结果：**

```json
{
    "code": 200,
    "message": "认证成功",
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJpc3MiOiJodHRwOlwvXC8xMjcuMC4wLjE6OTUwMVwvYXBpXC92MVwvYXV0aFwvdG9rZW4iLCJpYXQiOjE2MTcyNTQ1NDAsImV4cCI6MTYxNzM0MDk0MCwibmJmIjoxNjE3MjU0NTQwLCJqdGkiOiJ1SXRCRWxzYk9BOEJsYmJLIiwic3ViIjoxLCJwcnYiOiJmNmI3MTU0OWRiOGMyYzQyYjc1ODI3YWE0NGYwMmI3ZWU1MjlkMjRkIn0.Iq2DSHv4IQzk2J0Y_nMcLlo28h-j5ZQaeVYRlxXCd6y5dQm2YuMZPJO2KXieW0BElxZCEWL_gZKZUe_tAjHZXQ",
        "expired_at": 1617340940
    }
}
```

**返回参数：**

| 参数名       | 参数类型 | 参数说明  |
| ------------ | -------- | --------- |
| access_token | String   | 登陆Token |
| expired_at   | Integer  | 过期时间  |



### 算力模块

#### 首页

**请求方式：**

GET

**请求地址：**

```
http://{{host}}/api/v1/power/index
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "status_code": 200,
    "message": "",
    "data": {
        "global": {
            "total_power": 0,
            "total_mine": "0xbb2225273a96cb229f8a128b1b59a3f0f6477837",
            "total_burn":"0xbb2225273a96cb229f8a128b1b59a3f0f6477837"
        },
        "my": {
            "total_power": 0,
            "mine_power": "0.111",
            "share_power": "0xbb2225273a96cb229f8a128b1b59a3f0f6477837",
            "team_power": "0.111",
            "profit": 0,
            "vip_level": 0,
            "team_nums": 0,
            "direct_nums": 0
        }
    }
}
```

**返回参数：**

| 参数名      | 参数类型 | 参数说明       |
| ----------- | -------- | -------------- |
| global      | Object   | 全局数据       |
| <Object>    |          |                |
| total_power | String   | 做市资金池     |
| total_mine  | String   | 激励资金池地址 |
| total_burn  | String   | 流动性挖矿地址 |
| </Object>   |          |                |
| my          | Object   | 个人数据       |
| <Object>    |          |                |
| total_power | String   | 做市质押       |
| mine_power  | String   | 指数账户       |
| share_power | String   | 钱包地址       |
| balance     | String   | 用户余额       |
| vip_level   | String   | 做市收益       |
| team_num    | Integer  | 做市亏损       |
| direct_num  | Integer  | 算力收益       |
| </Object>   |          |                |

#### 领取收益

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/power/profit
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "status_code": 200,
    "message": "",
    "data": {
       "tx_id": "0x12312345345fd12312f1212d12"
    }
}
```

**返回参数：**

| 参数名 | 参数类型 | 参数说明 |
| ------ | -------- | -------- |
| tx_id  | String   | 交易ID   |


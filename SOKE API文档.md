# SOKE API文档

| 版本 | 撰写人  | 撰写时间   | 备注     |
| ---- | ------- | ---------- | -------- |
| 1.0  | Jason.z | 2021.04.01 | 初始版本 |
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

| 环境     | public api                  | websocket api |
| -------- | --------------------------- | ------------- |
| 本地环境 | http://192.168.101.14:9501/ | Ws://         |
| 测试环境 |                             |               |
| 生产环境 |                             |               |

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





弹框

```json
{
    "status_code": 666,
    "message": "",
    "data": {
        "title": "更新提醒",
        "content": "发现新版本，请立即更新!",
        "is_cancel": false,
        "buttons": [
            {
                "name": "立即更新",
                "type": "url",
                "value": "https://testflight.apple.com/join/kEf897k0"
            }
        ]
    }
}
```

```json
{
    "status_code": 666,
    "message": "",
    "data": {
        "title": "封禁提醒",
        "content": "由于你的账号涉嫌违规，现已被封禁。",
        "is_cancel": false,
        "buttons": [
          
        ]
    }
}
```

## 返回参数

| 参数名       | 参数类型    | 参数说明     |
| --------- | ------- | -------- |
| title     | String  | 标题       |
| content   | String  | 内容       |
| is_cancel | boolean | 是否可以取消弹框 |
| buttons   | Array   | 按钮组      |

Buttons

| 参数名   | 参数类型   | 参数说明 |
| ----- | ------ | ---- |
| name  | String | 按钮名称 |
| type  | String | 按钮类型 |
| value | String | 按钮值  |



## Public API

### 认证模块

#### 获取Nonce

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/auth/nonce
```

**是否认证**：

否（注册，找回密码）是（充值，赠送积分）

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

| 参数名    | 参数类型 | 是否必须 | 参数说明 |
| --------- | -------- | -------- | -------- |
| address   | String   | 是       | 钱包地址 |
| signature | String   | 是       | 签名     |

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



#### 首页

**请求方式：**

GET

**请求地址：**

```
http://{{host}}/api/v1/index/index
```

**是否认证**：

否

**请求参数：**

无

**返回结果：**

```json
{
    "status_code": 200,
    "message": "",
    "data": {
        "global": {
            "market_pool": 0,
            "incentive_pool": 0
        },
        "my": {
            "market_pledge": 0,
            "balance": 0,
            "address": null,
            "power": null,
            "market_income": 0,
            "market_loss": 0,
            "power_income": 0,
            "fund_income": 0
        },
        "power": {
            "power_pool": 0,
            "power_rate": 10,
            "is_open_power": null
        },
        "index": [
            {
                "id": 383064256,
                "title": "主流指数",
                "sub_title": "SOKE-MAIN",
                "code": "sokemain",
                "quote_change": "-0.1127"
            }
        ],
        "fund": [
            {
                "id": 383064256,
                "title": "测试基金",
                "profit": "0.2"
            }
        ]
    }
}
```

**返回参数：**

| 参数名         | 参数类型 | 参数说明   |
| -------------- | -------- | ---------- |
| global         | Object   | 全局数据   |
| <Object>       |          |            |
| market_pool    | String   | 做市资金池 |
| incentive_pool | String   | 激励资金池 |
| </Object>      |          |            |
| my             | Object   | 个人数据   |
| <Object>       |          |            |
| market_pledge  | String   | 做市质押   |
| balance        | String   | 指数账户   |
| addresss       | String   | 钱包地址   |
| power          | String   | 算力       |
| market_income  | String   | 做市收益   |
| market_loss    | String   | 做市亏损   |
| power_income   | String   | 算力收益   |
| fund_income    | String   | 基金盈亏   |
| </Object>      |          |            |
| index          |          | 指数列表   |
| <Array>        |          |            |
| id             | Integer  | ID         |
| title          | String   | 标题       |
| sub_title      | String   | 副标题     |
| code           | String   | 代码       |
| quote_change   | String   | 涨跌幅     |
| </Array>       |          |            |
| fund           |          |            |
| <Array>        |          |            |
| id             | Integer  | ID         |
| title          | String   | 标题       |
| profit         | String   | 收益率     |
| </Array>       |          |            |

### 首页模块

#### 充值(预下单)

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/index/recharge/order
```

**是否认证**：

是

**请求参数：**

| 参数名 | 参数类型 | 参数说明 |
| ------ | -------- | -------- |
| amount | String   | 充值金额 |

**返回结果：**

```json
{
    "code": 200,
    "message": "预下单成功",
    "data": {
        "no": "ZHCZ161770007165029",
        "amount": "1010.000000"
    }
}
```

**返回参数：**

| 参数名 | 参数类型 | 参数说明 |
| ------ | -------- | -------- |
| no     | String   | 订单号   |
| amount | String   | 订单金额 |



#### 充值

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/index/recharge
```

**是否认证**：

是

**请求参数：**

| 参数名 | 参数类型 | 参数说明   |
| ------ | -------- | ---------- |
| no     | Integer  | 订单号     |
| id     | String   | 区块交易ID |

**返回结果：**

```json
{
    "code": 200,
    "message": "提交成功"
}
```

**返回参数：**

无



#### 提现

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/index/withdraw
```

**是否认证**：

是

**请求参数：**

| 参数名 | 参数类型 | 参数说明 |
| ------ | -------- | -------- |
| amount | String   | 提现金额 |

**返回结果：**

```json
{
    "code": 200,
    "message": "提交成功",
    "data": {
        "no": "ZHTX161770007165029",
        "amount": "1010.000000"
    }
}
```

**返回参数：**

无



### 指数市场模块

#### 全部指数

**请求方式：**

GET

**请求地址：**

```
http://{{host}}/api/v1/contract/indexes
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "code": 200,
    "message": "",
    "data": [
        {
            "id": 383064256,
            "title": "主流指数",
            "sub_title": "SOKE-MAIN",
            "lever": 20,
            "code": "sokemain",
            "quote_change": "0"
        }
    ]
}
```

**返回参数：**

| 参数名       | 参数类型 | 参数说明   |
| ------------ | -------- | ---------- |
| id           | String   | 指数ID     |
| title        | String   | 指数标题   |
| sub_title    | String   | 指数副标题 |
| lever        | Integer  | 杠杆       |
| code         | String   | 指数代码   |
| quote_change | String   | 涨跌幅     |

#### 指数行情

**请求方式：**

GET

**请求地址：**

```
http://{{host}}/api/v1/contract/index/market
```

**是否认证**：

是

**请求参数：**

| 参数名 | 参数类型 | 是否必须 | 参数说明 |
| ------ | -------- | -------- | -------- |
| id     | Integer  | 是       | 指数ID   |

**返回结果：**

```json
{
    "code": 200,
    "message": "",
    "data": {
        "last_price": "2803.371",
        "open_price": 2803.371,
        "quote_change": "0",
        "today_high": "2803.371",
        "today_low": "2803.371",
        "trade_volume": 0,
        "position_volume": 10
    }
}
```

**返回参数：**

| 参数名          | 参数类型 | 参数说明 |
| --------------- | -------- | -------- |
| last_price      | Integer  | 系统通知 |
| open_price      | String   | 开仓价   |
| quote_change    | String   | 涨跌幅   |
| today_high      | String   | 高价     |
| today_low       | String   | 低价     |
| trade_volume    | Integer  | 交易量   |
| position_volume | Integer  | 持仓量   |

#### 指数K线

**请求方式：**

GET

**请求地址：**

```
http://{{host}}/api/v1/contract/index/kline
```

**是否认证**：

是

**请求参数：**

| 参数名   | 参数类型 | 是否必须 | 参数说明                                               |
| -------- | -------- | -------- | ------------------------------------------------------ |
| id       | Integer  | 是       | 指数ID                                                 |
| interval | String   | 是       | 周期:1min,5min,15min,30min,60min,4hour,1day,1week,1mon |

**返回结果：**

```json
{
    "code": 200,
    "message": "",
    "data": [
        {
            "open": 2994.1149,
            "low": 2994.1149,
            "close": 2994.1149,
            "high": 2994.1149,
            "timestamp": 1617258420
        }
    ]
}
```

**返回参数：**

| 参数名    | 参数类型 | 参数说明 |
| --------- | -------- | -------- |
| open      | Float    | 开       |
| low       | Float    | 低       |
| close     | Float    | 收       |
| high      | Float    | 高       |
| timestamp | Integer  | 时间戳   |

#### 下单

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/contract/order/create
```

**是否认证**：

是

**请求参数：**

| 参数名     | 参数类型 | 是否必须 | 参数说明                        |
| ---------- | -------- | -------- | ------------------------------- |
| id         | String   | 是       | 指数ID                          |
| direction  | String   | 是       | 方向 buy 多  sell 空            |
| price_type | String   | 是       | 价格类型 market 市价 limit 限价 |
| volume     | Integer  | 是       | 数量（手）                      |

**返回结果：**

```json
{
    "code": 200,
    "message": "下单成功"
}
```

#### 撤单

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/contract/order/cancel
```

**是否认证**：

是

**请求参数：**

| 参数名 | 参数类型 | 是否必须 | 参数说明 |
| ------ | -------- | -------- | -------- |
| id     | Integer  | 是       | 订单ID   |

**返回结果：**

```json
{
    "code": 200,
    "message": "撤单成功"
}
```



#### 平仓

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/contract/position/close
```

**是否认证**：

是

**请求参数：**

| 参数名 | 参数类型 | 是否必须 | 参数说明 |
| ------ | -------- | -------- | -------- |
| id     | Integer  | 是       | 仓位ID   |

**返回结果：**

```json
{
    "code": 200,
    "message": "平仓成功"
}
```



#### 当前持仓

**请求方式：**

GET

**请求地址：**

```
http://{{host}}/api/v1/contract/positions
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "code": 200,
    "message": "",
    "data": [
        {
            "id": 48468653,
            "direction": "buy",
            "position_volume": 1,
            "open_price": "2041.2132",
            "liquidation_price": "663.1789",
            "position_amount": "2041.213200",
            "profit_unreal": "762.157800",
            "profit_rate": "0.3734",
            "lever": 20,
            "index": {
                "title": "主流指数",
                "sub_title": "SOKE-MAIN"
            }
        }
    ],
    "page": {
        "total": 1,
        "count": 1,
        "per_page": 10,
        "current_page": 1,
        "total_pages": 1
    }
}
```

**返回参数：**

| 参数名            | 参数类型 | 参数说明   |
| ----------------- | -------- | ---------- |
| id                | String   | 持仓ID     |
| direction         | String   | 方向       |
| position_volume   | String   | 持仓数量   |
| open_price        | Integer  | 开仓均价   |
| liquidation_price | String   | 预估强平价 |
| position_amount   | String   | 冻结保证金 |
| lever             | Integer  | 杠杆       |
| index             | Object   | 指数       |
| <Object>          |          |            |
| id                | Integer  | 指数ID     |
| title             | String   | 标题       |
| sub_title         | String   | 副标题     |
| </Object>         |          |            |

#### 当前委托

**请求方式：**

GET

**请求地址：**

```
http://{{host}}/api/v1/contract/orders
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "code": 200,
    "message": "",
    "data": [
        {
            "id": 177194047,
            "direction": "buy",
            "volume": 1,
            "trade_volume": 1,
           	"price_type":"market",
            "fee": "0.000100",
            "created_at": "2021-04-01 11:32:58",
            "lever": 20,
            "index": {
                "id": 383064256,
                "title": "主流指数",
                "sub_title": "SOKE-MAIN"
            }
        }
    ],
    "page": {
        "total": 1,
        "count": 1,
        "per_page": 10,
        "current_page": 1,
        "total_pages": 1
    }
}
```

**返回参数：**

| 参数名       | 参数类型 | 参数说明 |
| ------------ | -------- | -------- |
| id           | String   | 订单ID   |
| direction    | String   | 方向     |
| volume       | Integer  | 委托数量 |
| trade_volume | Integer  | 成交数量 |
| price_type   | String   | 价格类型 |
| fee          | String   | 手续费   |
| lever        | Integer  | 杠杆     |
| created_at   | Datetime | 委托时间 |
| index        | Object   | 指数     |
| <Object>     |          |          |
| id           | Integer  | 指数ID   |
| title        | String   | 标题     |
| sub_title    | String   | 副标题   |
| </Object>    |          |          |



### 基金模块

#### 产品首页

**请求方式：**

GET

**请求地址：**

```
http://{{host}}/api/v1/fund/products
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "code": 200,
    "message": "",
    "data": [
        {
            "id": 383064256,
            "title": "测试基金",
            "total_volume": 100,
            "remain_volume": 100,
            "periods": [
                {
                    "period": "3",
                    "profit": "0.2"
                },
                {
                    "period": "6",
                    "profit": "0.3"
                },
                {
                    "period": "12",
                    "profit": "0.4"
                }
            ],
            "unit_price": "101.000000",
            "created_at": "2021-04-02 03:38:53"
        }
    ]
}
```

**返回参数：**

| 参数名        | 参数类型 | 参数说明   |
| ------------- | -------- | ---------- |
| id            | String   | 产品ID     |
| title         | String   | 产品名称   |
| total_volume  | Integer  | 总份数     |
| remain_volume | Integer  | 剩余份数   |
| unit_price    | String   | 单价       |
| created_at    | Datetime | 委托时间   |
| periods       | Array    | 周期列表   |
| <Array>       |          |            |
| period        | Integer  | 周期（月） |
| profit        | String   | 收益率     |
| </Array>      |          |            |



#### 产品购买(预下单)

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/fund/product/buy/order
```

**是否认证**：

是

**请求参数：**

| 参数名 | 参数类型 | 参数说明 |
| ------ | -------- | -------- |
| id     | Integer  | 产品ID   |

**返回结果：**

```json
{
    "code": 200,
    "message": "预下单成功",
    "data": {
        "no": "JZGM161770007165029",
        "amount": "1010.000000"
    }
}
```

**返回参数：**

| 参数名 | 参数类型 | 参数说明 |
| ------ | -------- | -------- |
| no     | String   | 订单号   |
| amount | String   | 订单金额 |



#### 产品购买

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/fund/product/buy
```

**是否认证**：

是

**请求参数：**

| 参数名 | 参数类型 | 参数说明   |
| ------ | -------- | ---------- |
| no     | Integer  | 订单号     |
| id     | String   | 区块交易ID |

**返回结果：**

```json
{
    "code": 200,
    "message": "提交成功"
}
```

**返回参数：**

无



#### 订单赎回

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/fund/order/redeem
```

**是否认证**：

是

**请求参数：**

| 参数名 | 参数类型 | 参数说明 |
| ------ | -------- | -------- |
| id     | Integer  | 订单ID   |

**返回结果：**

```json
{
    "code": 200,
    "message": "提交成功"
}
```

**返回参数：**

无



#### 购买记录

**请求方式：**

GET

**请求地址：**

```
http://{{host}}/api/v1/fund/buy/logs
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "code": 200,
    "message": "",
    "data": [
        {
            "id": 449274139,
            "unit_price": "101.000000",
            "amount": "1010.000000",
            "period": 3,
            "volume": 10,
            "status": 0,
            "no": "JZGM161770007165029",
            "created_at": "2021-04-06 17:07:51",
            "product": {
                "id": 383064256,
                "title": "测试基金"
            }
        },
        {
            "id": 383064256,
            "unit_price": "101.000000",
            "amount": "1010.000000",
            "period": 3,
            "volume": 10,
            "status": 0,
            "no": "JZGM161733879841152",
            "created_at": "2021-04-02 12:46:38",
            "product": {
                "id": 383064256,
                "title": "测试基金"
            }
        }
    ],
    "page": {
        "total": 2,
        "count": 2,
        "per_page": 15,
        "current_page": 1,
        "total_pages": 1
    }
}
```

**返回参数：**

| 参数名     | 参数类型 | 参数说明                                 |
| ---------- | -------- | ---------------------------------------- |
| id         | String   | 订单ID                                   |
| unit_price | String   | 单价                                     |
| amount     | String   | 金额                                     |
| period     | Integer  | 周期                                     |
| volume     | String   | 成交份数                                 |
| status     | String   | 状态 0未付款 1确认中 2确认成功 3确认失败 |
| no         | Integer  | 订单号                                   |
| created_at | Datetime | 时间                                     |
| product    |          |                                          |
| <Object>   |          |                                          |
| id         | Integer  | 基金ID                                   |
| title      | String   | 基金标题                                 |
| </Object>  |          |                                          |

#### 赎回记录

**请求方式：**

GET

**请求地址：**

```
http://{{host}}/api/v1/fund/redeem/logs
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "code": 200,
    "message": "",
    "data": [
        {
            "id": 449274139,
            "unit_price": "101.000000",
            "amount": "1010.000000",
            "period": 3,
            "volume": 10,
            "status": 0,
            "no": "JZGM161770007165029",
            "created_at": "2021-04-06 17:07:51",
            "product": {
                "id": 383064256,
                "title": "测试基金"
            }
        },
        {
            "id": 383064256,
            "unit_price": "101.000000",
            "amount": "1010.000000",
            "period": 3,
            "volume": 10,
            "status": 0,
            "no": "JZGM161733879841152",
            "created_at": "2021-04-02 12:46:38",
            "product": {
                "id": 383064256,
                "title": "测试基金"
            }
        }
    ],
    "page": {
        "total": 2,
        "count": 2,
        "per_page": 15,
        "current_page": 1,
        "total_pages": 1
    }
}
```

**返回参数：**

同上

#### 盈亏记录

**请求方式：**

GET

**请求地址：**

```
http://{{host}}/api/v1/fund/reward/logs
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "code": 200,
    "message": "",
    "data": [
        {
            "id": 383064256,
            "reward": "1000.000000",
            "created_at": "2021-04-06 18:40:03",
            "product": {
                "id": 383064256,
                "title": "测试基金"
            }
        }
    ],
    "page": {
        "total": 1,
        "count": 1,
        "per_page": 15,
        "current_page": 1,
        "total_pages": 1
    }
}
```

**返回参数：**

| 参数名     | 参数类型 | 参数说明 |
| ---------- | -------- | -------- |
| id         | String   | 订单ID   |
| reward     | String   | 金额     |
| created_at | Datetime | 时间     |
| product    |          |          |
| <Object>   |          |          |
| id         | Integer  | 基金ID   |
| title      | String   | 基金标题 |
| </Object>  |          |          |

### 做市模块

#### 质押(预下单)

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/market/pledge/order
```

**是否认证**：

是

**请求参数：**

| 参数名 | 参数类型 | 参数说明 |
| ------ | -------- | -------- |
| amount | String   | 质押金额 |

**返回结果：**

```json
{
    "code": 200,
    "message": "预下单成功",
    "data": {
        "no": "ZSZY161770007165029",
        "amount": "1010.000000"
    }
}
```

**返回参数：**

| 参数名 | 参数类型 | 参数说明 |
| ------ | -------- | -------- |
| no     | String   | 订单号   |
| amount | String   | 订单金额 |



#### 质押

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/market/pledge
```

**是否认证**：

是

**请求参数：**

| 参数名 | 参数类型 | 参数说明   |
| ------ | -------- | ---------- |
| no     | Integer  | 订单号     |
| id     | String   | 区块交易ID |

**返回结果：**

```json
{
    "code": 200,
    "message": "提交成功"
}
```

**返回参数：**

无



#### 取消质押

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/market/pledge/cancel
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "code": 200,
    "message": "提交成功"
}
```

**返回参数：**

无



#### 盈利日志

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/market/income/logs
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "code": 200,
    "message": "",
    "data": [
        {
            "id": 383064256,
            "reward": "1000.000000",
            "created_at": "2021-04-06 18:40:03"  
        }
    ],
    "page": {
        "total": 1,
        "count": 1,
        "per_page": 15,
        "current_page": 1,
        "total_pages": 1
    }
}
```

**返回参数：**

| 参数名     | 参数类型 | 参数说明 |
| ---------- | -------- | -------- |
| id         | String   | 订单ID   |
| reward     | String   | 金额     |
| created_at | Datetime | 时间     |

#### 亏损日志

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/market/loss/logs
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "code": 200,
    "message": "",
    "data": [
        {
            "id": 383064256,
            "reward": "1000.000000",
            "created_at": "2021-04-06 18:40:03"  
        }
    ],
    "page": {
        "total": 1,
        "count": 1,
        "per_page": 15,
        "current_page": 1,
        "total_pages": 1
    }
}
```

**返回参数：**

同上



### 算力模块

#### 开启挖矿

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/power/start
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "code": 200,
    "message": "操作成功",
}
```

**返回参数：**

无



#### 盈利日志

**请求方式：**

POST

**请求地址：**

```
http://{{host}}/api/v1/power/reward/logs
```

**是否认证**：

是

**请求参数：**

无

**返回结果：**

```json
{
    "code": 200,
    "message": "",
    "data": [
        {
            "id": 383064256,
            "reward": "1000.000000",
            "created_at": "2021-04-06 18:40:03"  
        }
    ],
    "page": {
        "total": 1,
        "count": 1,
        "per_page": 15,
        "current_page": 1,
        "total_pages": 1
    }
}
```

**返回参数：**

无



## Websocket Api

### 指数市场模块

#### 指数K线 

```json
{
    "ch": "index.kline.sokemain", 
    "ts": 1617262568256, 
    "data": {
        "1min": {
            "open": 3027.4376, 
            "low": 3027.4376, 
            "close": 3027.4376, 
            "high": 3027.4376, 
            "timestamp": 1617262560
        }, 
        "5min": {
            "open": 3031.0649, 
            "low": 3027.4376, 
            "close": 3027.4376, 
            "high": 3031.0649, 
            "timestamp": 1617262500
        }, 
        "15min": {
            "open": 3027.5958, 
            "low": 3027.4376, 
            "close": 3027.4376, 
            "high": 3031.0649, 
            "timestamp": 1617262200
        }, 
        "30min": {
            "open": 3027.5958, 
            "low": 3027.4376, 
            "close": 3027.4376, 
            "high": 3031.0649, 
            "timestamp": 1617262200
        }, 
        "60min": {
            "open": 3020.7763, 
            "low": 3020.7763, 
            "close": 3027.4376, 
            "high": 3044.7727, 
            "timestamp": 1617260400
        }, 
        "4hour": {
            "open": 2994.1149, 
            "low": 2994.1149, 
            "close": 3027.4376, 
            "high": 3065.3245, 
            "timestamp": 1617249600
        }, 
        "1day": {
            "open": 2994.1149, 
            "low": 2994.1149, 
            "close": 3027.4376, 
            "high": 3065.3245, 
            "timestamp": 1617206400
        }, 
        "1week": {
            "open": 2994.1149, 
            "low": 2994.1149, 
            "close": 3027.4376, 
            "high": 3065.3245, 
            "timestamp": 1616947200
        }, 
        "1mon": {
            "open": 2994.1149, 
            "low": 2994.1149, 
            "close": 3027.4376, 
            "high": 3065.3245, 
            "timestamp": 1617120000
        }
    }
}
```

#### 指数行情 

```json
{
    "ch": "index.market.sokemain", 
    "ts": 1617262568257, 
    "data": {
        "last_price": 3027.4376, 
        "open_price": "2994.1149", 
        "quote_change": "0.0111", 
        "today_high": "3065.3245", 
        "today_low": "2994.1149", 
        "trade_volume": 0, 
        "position_volume": 10
    }
}
```

#### 用户持仓 

```json
{
    "ch": "index.positions", 
    "ts": 1617262568257, 
    "data":[
    	 {
            "id": 48468653,
            "direction": "buy",
            "position_volume": 1,
            "open_price": "2041.2132",
            "liquidation_price": "663.1789",
            "position_amount": "2041.213200",
            "profit_unreal": "762.157800",
            "profit_rate": "0.3734",
            "lever": 20,
            "index": {
                "title": "主流指数",
                "sub_title": "SOKE-MAIN"
            }
        }
    ]
}
```

